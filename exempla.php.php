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
  'account_id' => 345345
);
$wot->language('en');
var_dump($wot->account->info($params));
