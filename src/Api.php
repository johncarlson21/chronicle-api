<?php
namespace Chronicle;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use GuzzleHttp\Client;

class Api
{
	
	// constants
	public $api_url = "https://stage.careers.chronicle.com/api/v2/";
	public $auth_key = "/authkey/AC0gPi07O2oxJ2ptbHlxJm5wfDVzIEl9U0QZfg";
	public $picklist = array(
			"estatuslist" => "employmentstatuslist" // employment status list
		);
	public $pub_code = "/pubcode/CHE";
	public $format = "json"; // json or xml
	public $debug = false; // set to output debug info
	
	public $client;
	
	public function __construct(Client $client)
	{
		$this->client = $client;
	}
	
	public function getEstatusList() {
		$res = $client->request('GET', 'picklist/' . $this->picklist['estatuslist'] . $this->auth_key . $this->pub_code . "/format/" . $this->format, ['debug' => $this->debug]);
		
		return $res->getBody();
	}
}
