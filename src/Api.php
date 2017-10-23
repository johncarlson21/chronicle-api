<?php
namespace Chronicle;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\Client;

class Api
{
	
	// constants
	public $api_url = "https://stage.careers.chronicle.com/api/v2/";
	public $auth_key = "/authkey/AC0gPi07O2oxJ2ptbHlxJm5wfDVzIEl9U0QZfg";
	public $authkey = "AC0gPi07O2oxJ2ptbHlxJm5wfDVzIEl9U0QZfg";
	public $picklist = array(
			//"estatuslist" => "employmentstatuslist" // employment status list
		"countrylist",
		"statelist",
		"categorylist",
		//"fieldlist",
		"employmentlevellist",
		"employmentstatuslist",
		"salarydescriptionlist",
		"currencylist",
		"publicationlist",
		"organizationlist",
		"enhancementslist"
		);
	public $pub_code = "/pubcode/CHE";
	public $pubcode = "CHE";
	public $format = "json"; // json or xml
	public $debug = false; // set to output debug info
	
	public $client;
	
	public $error_code;
	public $error_message;
	
	public $logger;
	
	public function __construct()
	{
		$logger = new Logger('chronicle-api');
		$logger->pushHandler(new StreamHandler('/home/surethingweb/public_html/var/log/chron-api.log', Logger::DEBUG));
		$this->logger = $logger;
	}
	
	public function getEstatusList() {
		$client = new Client([base_uri => $this->api_url]);
		$res = $client->request('GET', 'picklist/' . $this->picklist['estatuslist'] . $this->auth_key . $this->pub_code . "/format/" . $this->format, ['debug' => $this->debug]);
		
		$this->error_code = $res->getStatusCode();
		
		$this->logger->warning('getEstatusList status code: ' . $this->error_code);
		
		return $res->getBody();
	}
	
	public function getPickLists() {
		
		$dir = $_SERVER['DOCUMENT_ROOT'] . "/var/log/";
		
		foreach($this->picklist as $list) {
		
			$client = new Client([base_uri => $this->api_url]);
			$res = $client->request('GET', 'picklist/' . $list . $this->auth_key . $this->pub_code . "/format/" . $this->format);
			
			$output = "";
			
			$response = $res->getBody();
			
			//return $response; exit;
			$response = rtrim($response, "\0");
			//var_dump($response);
			$response = json_decode($response,true);
			//var_dump($response); exit;
			foreach($response["items"] as $item) {
				if(empty($item)) { continue; }
				//print_r($item,true);
				switch($list) {
					case "countrylist":
					case "statelist":
					case "employmentlevellist":
					case "employmentstatuslist":
					case "salarydescriptionlist":
					case "currencylist":
					case "publicationlist":
						$output .= $item . " : " . $item . "\n";
					break;
					case "categorylist":
					case "fieldlist":
					case "organizationlist":
					case "enhancementslist":
						$output .= $item['id'] . " : " . trim($item['name']) . "\n";
					break;
				}
			}
			
			file_put_contents($dir . $list . ".txt", $output);
			
		}
		
	}
	
	public function saveJob($post) {
		//$this->logger->debug(print_r($post,true));
		// put together an array to create the sent parameters
		$params = [
			'submitted_by' => 'acctspayable@graystoneadv.com',
			'ad_placer' => 'jbender@graystoneadv.com',
			'billto_adplacer' => true,
			'organizationid' => $post['acf_fields']['organization'], // needed from picklist
			'apisource' => 'MBC-Api',
			'positiontitle' => $post['post_title'],
			'positiondetails' => $post['post_content'],
			'employmentlevel' => $post['acf_fields']['employment_level'],
			'employmentstatus' => $post['acf_fields']['job_type'], // from picklist
			'onlineinstruction' => false, // will not change
			'country' => 'United States of America', // will not change
			'state' => $post['acf_fields']['ad_state'],
			'zipcode' => $post['acf_fields']['ad_zipcode'],
			'salarydescription' => $post['acf_fields']['salary_description'],
			'minimumsalary' => isset($post['acf_fields']['minimum_salary']) && !empty($post['acf_fields']['minimum_salary']) ? number_format($post['acf_fields']['minimum_salary'], 2) : '',
			'maximumsalary' => isset($post['acf_fields']['maximum_salary']) && !empty($post['acf_fields']['maximum_salary']) ? number_format($post['acf_fields']['maximum_salary'], 2) : '',
			'currency' => 'USD', // will not change
			'categories' => $post['acf_fields']['careersite_categories'], // needed from picklist
			'fields' => '',
			'organizationname' => $post['acf_fields']['institution_name'], // institution
			'organizationurl' => $post['acf_fields']['organization_url'],
			'applicationemail' => $post['acf_fields']['response_email'],
			'applicationurl' => $post['acf_fields']['response_url'],
			'applicationdeadline' => $post['acf_fields']['apply_by_date'],
			'openuntilfilled' => true,
			'internaljobcode' => $post['ID'], // would be post id
			'externalid' => $post['ID'], // would be post id
			'ponumber' => $post['acf_fields']['purchase_order'],
			'enhancements' => '',
			'packageid' => '44113', // need to know what we are going to do with this one
			'donotusepkg' => true,
			'authkey' => $this->authkey,
			'pubcode' => $this->pubcode
		];
		
		$this->logger->debug('params');
		$this->logger->debug(print_r($params,true));
		
		$client = new Client(['base_uri' => $this->api_url]);
		$res = $client->request('PUT', 
					'adorders/order' . "/format/" . $this->format,
					[
						'form_params' => $params,
						'debug' => $this->debug,
						'http_errors' => false
					]
				);
		$this->error_code = $res->getStatusCode();
		$this->logger->warning('save job status code: ' . $this->error_code);
		
		$response = $res->getBody();
		$response = rtrim($response, "\0");
		$response = json_decode($response,true);
		
		if ($this->error_code >= 300) {
			$this->logger->error('post id: '.$post['ID']);
			$this->logger->error($res->getBody());
			//error_log(print_r($response,true));
		}
		
		return $response;
		
	}
	
	public function updateJob($post, $post_meta) {
		//$this->logger->debug(print_r($post,true)); return;
		
		// make sure this post has an orderid
		if (!isset($post_meta['orderid']) || empty($post_meta['orderid'])) { 
			$this->error_code = 400;
			$this->logger->error('post id: '.$post['ID']);
			$this->logger->error("No order id for post.");
			$error_message = array(
				'errorMessage' => array("No order id for post.")
			);
			return $error_message;
		}
		
		$order_id = $post_meta['orderid'];
		
		// put together an array to create the sent parameters
		$params = [
			'submitted_by' => 'acctspayable@graystoneadv.com',
			'ad_placer' => 'jbender@graystoneadv.com',
			'billto_adplacer' => true,
			'organizationid' => $post['acf_fields']['organization'], // needed from picklist
			'apisource' => 'MBC-Api',
			'positiontitle' => $post['post_title'],
			'positiondetails' => $post['post_content'],
			'employmentlevel' => $post['acf_fields']['employment_level'],
			'employmentstatus' => $post['acf_fields']['job_type'], // from picklist
			'onlineinstruction' => false, // will not change
			'country' => 'United States of America', // will not change
			'state' => $post['acf_fields']['ad_state'],
			'zipcode' => $post['acf_fields']['ad_zipcode'],
			'salarydescription' => $post['acf_fields']['salary_description'],
			'minimumsalary' => isset($post['acf_fields']['minimum_salary']) && !empty($post['acf_fields']['minimum_salary']) ? number_format($post['acf_fields']['minimum_salary'], 2) : '',
			'maximumsalary' => isset($post['acf_fields']['maximum_salary']) && !empty($post['acf_fields']['maximum_salary']) ? number_format($post['acf_fields']['maximum_salary'], 2) : '',
			'currency' => 'USD', // will not change
			'categories' => $post['acf_fields']['careersite_categories'], // needed from picklist
			'fields' => '',
			'organizationname' => $post['acf_fields']['institution_name'], // institution
			'organizationurl' => $post['acf_fields']['organization_url'],
			'applicationemail' => $post['acf_fields']['response_email'],
			'applicationurl' => $post['acf_fields']['response_url'],
			'applicationdeadline' => $post['acf_fields']['apply_by_date'],
			'openuntilfilled' => true,
			'internaljobcode' => $post['ID'], // would be post id
			'externalid' => $post['ID'], // would be post id
			'ponumber' => $post['acf_fields']['purchase_order'],
			'enhancements' => '',
			'packageid' => '44113', // need to know what we are going to do with this one
			'donotusepkg' => false,
			'authkey' => $this->authkey,
			'pubcode' => $this->pubcode
		];
		
		$this->logger->debug('params');
		$this->logger->debug(print_r($params,true));
		
		$client = new Client(['base_uri' => $this->api_url]);
		$res = $client->request('POST', 
					'adorders/order/id/' . $order_id . "/format/" . $this->format,
					[
						'form_params' => $params,
						'debug' => $this->debug,
						'http_errors' => false
					]
				);
		$this->error_code = $res->getStatusCode();
		$this->logger->warning('update job status code: ' . $this->error_code);
		
		$response = $res->getBody();
		$response = rtrim($response, "\0");
		$response = json_decode($response,true);
		
		if ($this->error_code >= 300) {
			$this->logger->error('post id: '.$post['ID']);
			$this->logger->error($res->getBody());
			//error_log(print_r($response,true));
		}
		
		return $response;
		
	}
	
	public function removeJob() {
		
	}
	
	public function getErrorCode() {
		return $this->error_code;
	}
}
