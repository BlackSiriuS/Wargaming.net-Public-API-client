<?php

include_once 'wgapi.php.php';
$params = array(
  'region' => 'RU',
  'language' => 'ru',
  'apiStandalone' => '1b7bc64858d79aed49d1bc479248fa1a',
  'apiServer' => '2cc044ecd33f396b48287cd155c9958d'
);
$wot = new Wgapi($params);
$params = array(
  'account_id' => 1000000
);
$wot->language('en');

$output = $wot->account->info($params);
unset($output[1000000]['achievements']);
var_dump($output[1000000]);
/*
$params = array(
  'redirect_uri' =>'http://localhost/World-of-Tanks-API-client/exempla.php.php'
);
$output = $wot->auth->login($params);
var_dump($output);
*/