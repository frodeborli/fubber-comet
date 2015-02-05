<?php
use \Fubber\Server\Controller;

class PingController extends \Fubber\Server\ForkingController {

	protected $hits = 0;

	public function get($request, $response) {

		sleep(1);

		ResponseWriter::respond($request, $response, 'pong');
	}

}
