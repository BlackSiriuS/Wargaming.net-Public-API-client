<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Wotapi {

  private $protocol = 'https';
  private $http_method = 'POST';
  private $API_name = 'wot';
  private $standalone = 'demo';
  private $server = 'demo';
  private $token = '';
  public $use_multicall = true;

  function __construct() {
    
  }

  function setRegion($region = 'RU') {
    switch ($region) {
      case 'ASIA': $this->region = 'ASIA';
        $this->server = 'api.worldoftanks.asia';
        break;
      case 'EU': $this->region = 'EU';
        $this->server = 'api.worldoftanks.eu';
        break;
      case 'KR': $this->region = 'KR';
        $this->server = 'api.worldoftanks.kr';
        break;
      case 'NA': $this->region = 'NA';
        $this->server = 'api.worldoftanks.com';
        break;
      case 'RU':
      default: $this->region = 'RU';
        $this->server = 'api.worldoftanks.ru';
        break;
    }
  }

  function setLanguage($language = 'ru') {
    $language_list = array('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn');
    if (in_array($language, $language_list))
      $this->language = $language;
    else
      $this->language = 'ru';
  }

  function setProtocol($protocol = array('http', 'https')) {
    $protocol = (array) $protocol;
    if (count($protocol) == 0)
      return $this->protocol;
    if (in_array($this->protocol, $protocol))
      return $this->protocol;
    return $protocol[0];
  }

  function setMethod($http_method = array('GET', 'POST')) {
    $http_method = (array) $http_method;
    if (count($http_method) == 0)
      return $this->http_method;
    if (in_array($this->http_method, $http_method))
      return $this->http_method;
    return $http_method[0];
  }

  function setUser($info = array()) {
    $info = (array) $info;
    $this->token = (string) @$info['token'];
    $this->name = (string) @$info['name'];
    $this->id = (integer) @$info['user_id'];
    $this->expire = (integer) @$info['expire'];
  }

  function multiCall() {
    $this->use_multicall = true;
  }

  function singleCall() {
    $this->use_multicall = false;
  }

}
