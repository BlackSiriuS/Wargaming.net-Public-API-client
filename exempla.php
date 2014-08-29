<?php

include_once 'Wot_api.php';
$params = array(
  'region' => 'RU',
  'language' => 'ru',
  'standalone' => '1b7bc64858d79aed49d1bc479248fa1a',
  'server' => '2cc044ecd33f396b48287cd155c9958d'
);
$wot = new Wgapi($params);
$params = array(
  'application_id' => '',
  'language' => '',
  'account_id' => 345345
);
$wot->language('en');
var_dump($wot);
