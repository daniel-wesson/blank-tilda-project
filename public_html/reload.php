<?php

$secretKey = $_GET['secretKey'];

if (!$secretKey) {
	require_once __DIR__.'/../pages/404.html';
	exit;
}


require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

function dd($d)
{
	echo '<pre>';
	print_r($d);
	echo '</pre>';
	die;
}

class Tilda
{
	private $secretKey;

	private $publicKey;

	private $curl;

	const TILDA_API_ENDPOINT = 'http://api.tildacdn.info/v1/';

	public function __construct($publicKey, $secretKey)
	{
		$this->publicKey = $publicKey;
		$this->secretKey = $secretKey;

		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
		curl_setopt($this->curl, CURLOPT_HEADER, 0);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->curl, CURLOPT_POST, 0);
	}

	public function __destruct()
	{
		curl_close($this->curl);
	}

	public function call($method, $params = [])
	{
		curl_setopt($this->curl, CURLOPT_URL, $this->prepareRequest($method, $params));
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		$out = curl_exec($this->curl);

		return $this->prepareResponse($out);
	}

	private function prepareResponse($response)
	{
		$data = json_decode($response);

		if ($data->status === 'ERROR') {
			dd($data);
		}

		return $data->result;
	}

	private function prepareRequest($method, $params = [])
	{
		$params = array_merge($params, [
			'publickey' => $this->publicKey,
			'secretkey' => $this->secretKey
		]);
		return self::TILDA_API_ENDPOINT.$method.'?'.http_build_query($params);
	}
}


$tilda = new Tilda($_ENV['PUBLIC_KEY'], $secretKey);

$method = $_GET['method'];

if ($method) {
	$tildaMethods = [
		'getprojectslist' => [],
		'getproject' => ['projectid' => $_GET['projectid'] ?? $_ENV['PROJECT_ID']],
		'getprojectexport' => ['projectid' => $_GET['projectid'] ?? $_ENV['PROJECT_ID']],
		'getpageslist' => ['projectid' => $_GET['projectid'] ?? $_ENV['PROJECT_ID']],
		'getpage' => ['pageid' => $_GET['pageid']],
		'getpagefull' => ['pageid' => $_GET['pageid']],
		'getpageexport' => ['pageid' => $_GET['pageid']],
		'getpagefullexport' => ['pageid' => $_GET['pageid']]
	];

	if (!isset($tildaMethods[$method])) {
		dd('Unknown method "'.$method.'"');
	}

	$response = $tilda->call($method, $tildaMethods[$method]);
	dd($response);
}


class Export
{
	private $tilda;

	private $resources = [];

	public function __construct(Tilda $tilda, $pages)
	{
		$this->tilda = $tilda;

		$this->gatherBerries($pages);
		$this->exportResources();

		echo 'Export was successful';
	}

	private function gatherBerries($pages)
	{
		foreach ($pages as $page) {
			$pageId = $page['pageId'];
			$file = $pageId.'.html';

			$pageInfo = $this->tilda->call('getpagefullexport', ['pageid' => $pageId]);

			$this->exportPage($file, $pageInfo->html, $page['replaces']);

			$this->addResource('css', $pageInfo->css);
			$this->addResource('js', $pageInfo->js);
			$this->addResource('images', $pageInfo->images);
		}
	}

	private function addResource($resourceType, $files)
	{
		foreach ($files as $file) {
			$this->resources[$resourceType][$file->from] = $file->to;
		}
	}

	private function exportResources()
	{
		foreach ($this->resources as $resType => $resources) {
			foreach ($resources as $from => $to) {
				$file = __DIR__.'/'.$resType.'/'.$to;

				if (!file_exists($file)) {
					file_put_contents($file, file_get_contents($from));
				}
			}
		}
	}

	private function exportPage($file, $html, $replaces)
	{
		$filepath = __DIR__.'/../pages/'.$file;

		foreach ($replaces as $pattern => $value) {
			$html = preg_replace($pattern, $value, $html);
		}

		file_put_contents($filepath, $html);
	}
}


$export = new Export($tilda, require_once __DIR__.'/../pages.php');