<?php

include_once 'wgapi.php';
$wgn = new Wgapi(array(
  'region' => 'RU', //регион
  'language' => 'ru', //язык ответа
  'apiStandalone' => '6caa0c86ea0c3ffb938038b70597f483', //Ключь автономного приложения
  'apiServer' => 'e3ed3fdd67fe9e33bb377281b1a3a57a', //Ключь серверного приложения
  'apiName' => 'wgn'
    ));

$output = $wgn->wargag->categories(array(
  'type' => "picture"
    ));
if (!is_null($output))
  var_dump($output);
else
  var_dump($wgn->wargag->erorr->get());