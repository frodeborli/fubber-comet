<?php
namespace Fubber\Comet;

class ResponseWriter {
	public static function respond($request, $response, $data) {
		$httpCode = 200;
		if(is_subclass_of($data, '\Fubber\Comet\HttpException'))
			$httpCode = $data->getCode();

        $headers = array();
        $rHeaders = $request->getHeaders();
        if(isset($rHeaders['Origin']))
            $headers['Access-Control-Allow-Origin'] = $rHeaders['Origin'];

		$query = $request->getQuery();

		if(!isset($query['format'])) $query['format'] = 'json';

		switch($query['format']) {
			case 'php' :
                $headers['Content-Type'] = 'text/plain';
				$response->writeHead($httpCode, $headers);
				$response->end(serialize($data));
				break;
			case 'json' :
			default :
				if(isset($query['cb'])) {
                    $headers['Content-Type'] = 'application/javascript';
	    			$response->writeHead($httpCode, $headers);
					$response->end($query['cb'].'('.json_encode($data).');');
				} else {
                    $headers['Content-Type'] = 'application/json';
    				$response->writeHead($httpCode, $headers);
					$response->end(json_encode($data));
				}
				break;
		}
	}
}
