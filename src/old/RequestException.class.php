<?php
class RequestException extends HttpException {
	public function __construct($message) {
		parent::__construct($message, 405);
	}
}
