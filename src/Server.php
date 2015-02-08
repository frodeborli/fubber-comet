<?php
namespace Fubber\Comet;

use \Fubber\Reactor\Host;

class Server extends \Fubber\Reactor\Server {

	protected static $instance;

	public function getInstance() {
		if(self::$instance) return self::$instance;

		return self::$instance = new self(Host::$instance->config);
	}

	public $pdo;
	public $subscribers = array();
	public $tagMap = array();
	public $readyMap = array();
    public $host;

	protected $subscriberIndex = 0;
	protected $messageIndex = 0;
	protected $buffer = array();
	protected $bufferLength = 0;
	protected $bufferFirst = NULL;
	protected $bufferLast = NULL;
    protected $insertMessageQuery, $bufferFillQuery, $pollQuery, $purgeQuery;

	public function __construct($host, $config) {
        self::$instance = $this;
        $this->host = $host;

        // Get access to the database
        if(!isset($config->database))
            $config->database = 'master';

        $this->pdo = $this->host->getDatabaseConnection($config->database);
        $this->initDatabase();

   		$this->host->getLoop()->addPeriodicTimer(0.01, array($this, 'send'));
   		$this->host->getLoop()->addPeriodicTimer(1, array($this, 'cleanup'));
        $this->host->getLoop()->addPeriodicTimer(0.02, array($this, 'pollDatabase'));
   		$this->host->getLoop()->addPeriodicTimer(600, array($this, 'purgeDatabase'));

		$this->bufferFillQuery->execute();
		foreach($this->bufferFillQuery->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$this->messageIndex = $row['id'];
			$this->queueMessage($row['id'], $row['ts'], explode(" ", $row['tags']), unserialize($row['payload']));
		}

        $this->host->addRoute('/ws/subscribe', new SubscriberController());
        $this->host->addRoute('/ws/push', new PushController());
        $this->host->addRoute('/js/comet/*', new FileController(array("root" => dirname(__DIR__).'/files/js/comet', "url" => "/js/comet"))); /* */
	}

    public function initDatabase() {
		$this->pdo->exec('SET NAMES utf8');
		$this->pdo->exec('CREATE TABLE messages (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, ts TIMESTAMP, tags TEXT, payload LONGBLOB) DEFAULT CHARACTER SET utf8');
		$this->insertMessageQuery = $this->pdo->prepare('INSERT INTO messages (ts, tags, payload) VALUES (NOW(), ?, ?)');
		$this->bufferFillQuery = $this->pdo->prepare('(SELECT id, UNIX_TIMESTAMP(ts) AS ts, tags, payload FROM messages ORDER BY id DESC LIMIT 1000) ORDER BY id');
		$this->pollQuery = $this->pdo->prepare('SELECT id, UNIX_TIMESTAMP(ts) AS ts, tags, payload FROM messages WHERE id > ? ORDER BY id LIMIT 100');
		$this->purgeQuery = $this->pdo->prepare('DELETE FROM messages WHERE ts < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    }

	public function purgeDatabase() {
		$this->purgeQuery->execute();
	}

	public function pollDatabase() {
		$this->pollQuery->execute(array($this->messageIndex));

		foreach($this->pollQuery->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$this->messageIndex = $row['id'];
			$this->queueMessage($row['id'], $row['ts'], explode(" ", $row['tags']), unserialize($row['payload']));
		}
	}

	public function cleanup() {
		foreach($this->tagMap as $tag => $subscribers) {
			if(sizeof($subscribers)==0)
				unset($this->tagMap[$tag]);
		}
	}

	public function printStats() {
		foreach($this->tagMap as $tag => $subscribers) {
			echo "Tag: ".$tag." Subscribers: ".sizeof($subscribers)."\n";
		}
	}

	/**
	* 	Send messages to at most 100 ready subscribers
	*/
	public function send() {
		$maxShipments = 500;
		foreach($this->readyMap as $receiverId => $true) {
			$this->subscribers[$receiverId]->send();
			$this->subscribers[$receiverId]->cleaned = TRUE;
			// Unmap the tags
			foreach($this->subscribers[$receiverId]->tags as $tag) {
				unset($this->tagMap[$tag][$receiverId]);
			}
			unset($this->subscribers[$receiverId]);
			unset($this->readyMap[$receiverId]);
			if($maxShipments-- < 0) return;
		}
	}

	public function addMessage($tags, $payload) {
		$params = array(implode(" ", $tags), serialize($payload));
		return $this->insertMessageQuery->execute($params);
	}

	protected function addToBuffer($id, $ts, $tags, $payload) {
		$this->bufferLength++;
		$this->bufferLast = intval($id);
		if($this->bufferFirst === NULL)
			$this->bufferFirst = $this->bufferLast;
		$this->buffer[$this->bufferLast] = array(intval($ts), array_flip($tags), $payload);
		while($this->bufferLength > 1000) {
			unset($this->buffer[$this->bufferFirst++]);
			$this->bufferLength--;
		}
	}

	/**
	*	Add messages to all subscribers that match the provided tags
	*/
	protected function queueMessage($id, $ts, $tags, $payload) {
		$this->addToBuffer($id, $ts, $tags, $payload);

		// Hold which subscribers are going to get the message
		$receiverIds = array();

		// Find each subscriber that we'll send the message to
		foreach($tags as $tag) {
			if(isset($this->tagMap[$tag]))
				foreach($this->tagMap[$tag] as $subscriberId => $true)
					$receiverIds[$subscriberId] = true;
		}

		// Send the message to each subscriber
		$hits = 0;
		foreach($receiverIds as $receiverId => $true) {
			$this->subscribers[$receiverId]->addPayload($id, $ts, $payload);
			$this->readyMap[$receiverId] = TRUE;
			$hits++;
		}

		return $hits;
	}

	/**
	*	Add a subscriber
	*/
	public function addSubscriber(MessageSubscriberInterface $subscriber) {
		$subscriber->subscriberId = $this->subscriberIndex++;
		$this->subscribers[$subscriber->subscriberId] = $subscriber;
		foreach($subscriber->tags as $tag) {
			if(!isset($this->tagMap[$tag]))
				$this->tagMap[$tag] = array();

			$this->tagMap[$tag][$subscriber->subscriberId] = TRUE;
		}
		$broker = $this;
		$subscriber->request->on('end', function() use($broker, $subscriber) {
			if($subscriber->cleaned) return;
			$subscriber->cleaned = TRUE;
			foreach($subscriber->tags as $tag) {
				unset($broker->tagMap[$tag][$subscriber->subscriberId]);
			}
			unset($this->subscribers[$subscriber->subscriberId]);
			unset($this->readyMap[$subscriber->subscriberId]);
		});
		$query = $subscriber->request->getQuery();
		if(isset($query['lastId'])) {
			$lastId = intval($query['lastId']);
			if($lastId >= $this->bufferFirst) {
				// We have the message in our buffer
				for($i = $lastId+1; $i <= $this->bufferLast; $i++) {
					foreach($subscriber->tags as $tag) {
						if(isset($this->buffer[$i][1][$tag])) {
							$subscriber->addPayload($i, $this->buffer[$i][0], $this->buffer[$i][2]);
							$this->readyMap[$subscriber->subscriberId] = TRUE;
						}
					}
				}
			} else {
				// Fetch messages from the database
				$this->pollQuery->execute(array($query['lastId']));
				foreach($this->pollQuery->fetchAll(\PDO::FETCH_ASSOC) as $row) {
					$tags = array_flip(explode(" ", $row['tags']));
					foreach($subscriber->tags as $tag) {
						if(isset($tags[$tag])) {
							$subscriber->addPayload($row['id'], $row['ts'], unserialize($row['payload']));
							$this->readyMap[$subscriber->subscriberId] = TRUE;
						}
					}
				}
			}
		}
	}
}
