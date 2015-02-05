<?php
namespace Fubber\Comet;

class SubscriptionHandler implements MessageSubscriberInterface {
	public $subscriberId;
	public $tags = array();
	public $cleaned = FALSE;

	public $request;
	public $response;
	protected $payloads = array();

	public function __construct($request, $response, &$tags) {
		$this->request = $request;
		$this->response = $response;
		$this->tags = $tags;
	}

	public function addPayload($id, $ts, $payload) {
		$this->payloads[] = array('id'=>intval($id), 'ts'=>intval($ts), 'payload'=>$payload);
	}

	public function send() {
		ResponseWriter::respond($this->request, $this->response, $this->payloads);
	}
}
