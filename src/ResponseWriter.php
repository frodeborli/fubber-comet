<?php
namespace Fubber\Comet;

class ResponseWriter {
	public static function respond($request, $response, $data) {
		$httpCode = 200;
		if(is_subclass_of($data, '\Fubber\Comet\HttpException'))
			$httpCode = $data->getCode();


		$query = $request->getQuery();

		if(!isset($query['format'])) $query['format'] = 'json';

		switch($query['format']) {
			case 'php' :
				$response->writeHead($httpCode, array('Content-Type' => 'text/plain'));
				$response->end(serialize($data));
				break;
			case 'json' :
			default :
				if(isset($query['cb'])) {
					$response->writeHead($httpCode, array('Content-Type' => 'application/javascript'));
					$response->end($query['cb'].'('.json_encode($data).');');
				} else {
					$response->writeHead($httpCode, array('Content-Type' => 'application/json'));
					$response->end(json_encode($data));
				}
				break;
		}
	}
}
