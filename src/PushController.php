<?php
namespace Fubber\Comet;

use \Fubber\Reactor\Controller;

class PushController extends Controller {
	public function post($request, $response) {
        $headers = $request->getHeaders();
        if(!isset($headers['Content-Length']) || intval($headers['Content-Length'])==0) {
            ResponseWriter::respond($request, $response, new RequestException('No post data payload, or Content-Length header missing!'));
            return;
        }
        $request->on('data', function($data) use($request, $response) {
            if(!isset($request->postDataTmp))
                $request->postDataTmp = $data;
            else
                $request->postDataTmp .= $data;

            $headers = $request->getHeaders();
            if(isset($headers['Content-Length'])) {
                if(strlen($request->postDataTmp) >= intval($headers['Content-Length']))
                    $request->postDataFin = $request->postDataTmp;
            } else {
                $request->postDataFin = $request->postDataTmp;
            }
            if (isset($request->postDataFin)) {
                parse_str($request->postDataFin, $query);
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
        });
	}
}
