<?php
namespace Fubber\Comet;

class HttpException extends \Exception implements \JsonSerializable {
	public function jsonSerialize() {
		return array(
			'error' => $this->getCode(),
			'message' => $this->getMessage()
		);
	}
}
