<?php

require __DIR__.'/../vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();


$availability = (bool) file_get_contents('https://capitalhost.ru/api/site-availability/'.$_ENV['CAPITALHOST_USER_ID']);

if (!$availability) {
	require_once __DIR__.'/../pages/unavailable.php';
	exit;
}


$pages = require_once __DIR__.'/../pages.php';


$url = $_SERVER['REQUEST_URI'];
$url = explode('?', $url)[0];


foreach ($pages as $pageUrl => $page) {
	$pageUrl = preg_replace('/\/$/', '', $pageUrl);

	if ($pageUrl[0] != '/') {
		$pageUrl = '/'.$pageUrl;
	}

	if ($url == $pageUrl) {
		$file = __DIR__.'/../pages/'.$page['pageId'].'.html';

		if (file_exists($file)) {
			require_once $file;
		} else {
			require_once __DIR__.'/../pages/404.html';
		}

		exit;
	}
}

require_once __DIR__.'/../pages/404.html';