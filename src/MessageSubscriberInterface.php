<?php
namespace Fubber\Comet;

interface MessageSubscriberInterface {
	public function addPayload($id, $ts, $payload);
	public function send();
}
