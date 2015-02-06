<?php
namespace Fubber\Comet;

use \Fubber\Reactor\Controller;

class PushController extends Controller {
	public function get($request, $response) {
		$query = $request->getQuery();
		if(!isset($query['c']) || !is_array($query['c'])) {
			// This is not a valid request, so we kill it off immediately!
			ResponseWriter::respond($request, $response, new RequestException('No channels specified. Specify them using the c[] query parameter!'));
		} else if(!isset($query['p'])) {
			// This is not a valid request, so we kill it off immediately!
			ResponseWriter::respond($request, $response, new RequestException('No payload specified. Specify it using the p query parameter!'));
		} else {
			$hits = Server::getInstance()->addMessage($query['c'], $query['p']);
			ResponseWriter::respond($request, $response, $hits);
		}
	}
}
