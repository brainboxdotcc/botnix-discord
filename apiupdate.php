<?php

class WebsiteUpdatePostThread {

	var $url;
	var $payload;
	var $authorization;
	var $pid;
	var $procstatus;

	public function __construct($url, $payload, $authorization)
	{
		$this->url = $url;
		$this->payload = $payload;
		$this->authorization = $authorization;
	}

	public function run()
	{
		if ($this->authorization == "") {
			echo "Skipping update for $url, not configured\n";
			return;
		}
		system("nohup nice -n 10 curl -X POST -H \"Content-Type: application/json\" -H \"Authorization: $this->authorization\" -d  ".escapeshellarg(json_encode($this->payload))." $this->url >/dev/null 2>/dev/null &");
	}
}
