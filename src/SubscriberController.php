<?php
namespace Fubber\Comet;

use \Fubber\Reactor\Controller;

class SubscriberController extends Controller {
	public function get($request, $response) {
		// We're getting a new subscriber! Yay!

		$query = $request->getQuery();
		if(!isset($query['c']) || !is_array($query['c'])) {
			// This is not a valid request, so we kill it off immediately!
			$response->writeHead(200, array('Content-Type' => 'text/plain'));
			$response->end('No channels specified. Specify them using the c[] query parameter!');
		} else {
			// Spawn off a Subscriber instance. It will be registered in Server and disposed off from there.
			$subscriptionHandler = new SubscriptionHandler($request, $response, $query['c']);
			Server::getInstance()->addSubscriber($subscriptionHandler);
		}
	}
}
