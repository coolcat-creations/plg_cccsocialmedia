<?php

class Application
{
	public $client;

	public function __construct()
	{
		$this->client = (object)[
			'userAgent' => '',
		];
	}

	public function set($key, $value): void
	{
	}

	public function setUserAgent($userAgent): void
	{
		$this->client->userAgent = $userAgent;
	}
}
