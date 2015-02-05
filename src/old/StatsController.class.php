<?php
use \Fubber\Server\Host;
use \Fubber\Server\ForkingController;

class StatsController extends ForkingController {
	public function get($request, $response) {
		$stats = array();

		$stats['memory'] = memory_get_usage();
		$stats['memory_real'] = memory_get_usage(TRUE);
		$stats['memory_peak'] = memory_get_peak_usage();
		$stats['memory_peak_real'] = memory_get_peak_usage(TRUE);
		$stats['hits'] = Host::$app->hits;

		ResponseWriter::respond($request, $response, $stats);
	}
}
