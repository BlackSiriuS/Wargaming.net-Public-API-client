<?php

class Wgapi extends WgapiCore {

  public $parent = true;

  function load() {
    $this->erorr = new WgApiError();
    if (count($this->load_class) == 0)
      $this->update();
    foreach ($this->load_class as $class) {
      $load_class = "api_{$this->API_name}_{$class}";
      if (class_exists($load_class)) {
        $this->$class = new $load_class($this);
      } else {
        echo "class not found '{$load_class}'; \n";
        $this->update();
      }
    }
  }

}

class WgApiError {

  public $code = 0;
  public $field = '';
  public $message = '';
  public $value = '';

  function __construct() {
    $this->dictionary = array(
      array(402, '%FIELD%_NOT_SPECIFIED', 'Не заполнено обязательное поле %FIELD%.'),
      array(404, '%FIELD%_NOT_FOUND', 'Информация не найдена.'),
      array(404, 'METHOD_NOT_FOUND', 'Указан неверный метод API.'),
      array(405, 'METHOD_DISABLED', 'Указаный метод API отключён.'),
      array(407, '%FIELD%_LIST_LIMIT_EXCEEDED', 'Превышен лимит переданных идентификаторов в поле %FIELD%.'),
      array(407, 'APPLICATION_IS_BLOCKED', 'Приложение заблокировано администрацией.'),
      array(407, 'INVALID_%FIELD%', 'Указано не валидное значение параметра %FIELD%.'),
      array(407, 'INVALID_APPLICATION_ID', 'Неверный идентификатор приложения.'),
      array(407, 'INVALID_IP_ADDRESS', 'Недопустимый IP-адрес для серверного приложения.'),
      array(407, 'REQUEST_LIMIT_EXCEEDED', 'Превышены лимиты квотирования.'),
      array(504, 'SOURCE_NOT_AVAILABLE', 'Источник данных не доступен.'),
    );
  }

  function adderror($errors = array()) {
    foreach ($errors as $error)
      $this->dictionary[] = $error;
  }

  function set($error = array(), $url = '', $params = array()) {
    foreach ($error as $key => $value) {
      $this->$key = $value;
    }
    $this->url = $url;
    $this->params = $params;
    foreach ($this->dictionary as $value)
      if ($value[0] == $this->code && str_replace('%FIELD%', strtoupper($this->field), $value[1]) == $this->message) {
        $this->value = str_replace('%FIELD%', '"' . $this->field . '"', $value[2]);
        break;
      }
  }

  function getMessage() {
    return $this->message;
  }

  function getValue() {
    return $this->value;
  }

  function get() {
    return array($this->code, $this->message, $this->value);
  }

}

class WgApiCore {

  public $protocol = 'https';
  public $serverDomain = 'api.worldoftanks.';
  public $apiName = 'wot';
  public $apiStandalone = 'demo';
  public $apiServer = 'demo';
  public $token = '';
  public $language = 'ru';
  public $load_class = array();

  function __construct($params = array()) {
    $params = (array) $params;
    foreach ($params as $key => $value)
      if (!empty($value))
        $this->$key = $value;
    $this->language((string) @$params['language']);
    $this->region((string) @$params['region']);
    $this->setuser(@$params['user']);
    $this->load();
  }

  function load() {
    $this->erorr = new WgApiError();
  }

  function language($language = 'ru') {
    $language_list = array('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn');
    if (in_array($language, $language_list))
      $this->language = $language;
    else
      $this->language = 'ru';
  }

  function region($region = 'RU') {
    $region_list = array('ASIA', 'EU', 'KR', 'NA', 'RU');
    if (in_array($region, $region_list)) {
      $this->region = $region;
    } else {
      $this->region = 'RU';
    }
    $this->server = $this->serverDomain . strtolower($this->region);
  }

  function setuser($info = array()) {
    $info = (array) $info;
    $this->user = $info;
    $this->token = (string) @$info['token'];
    $this->name = (string) @$info['name'];
    $this->id = (integer) @$info['user_id'];
    $this->expire = (integer) @$info['expire'];
  }

  function protocol($protocol = array('http', 'https'), $with_token = false) {
    $protocol = (array) $protocol;
    $_protocol = $this->protocol;
    if ($with_token)
      $_protocol = 'https';
    if (count($protocol) == 0)
      return $_protocol;
    if (in_array($_protocol, $protocol))
      return $_protocol;
    return $protocol[0];
  }

  function seturl($mblock = '', $mname = '') {
    $_params = array($this->protocol . ':/', $this->server, $this->apiName);
    if ($mblock)
      $_params[] = $mblock;
    if ($mname)
      $_params[] = $mname;
    $_params[] = '';
    $this->url = implode('/', $_params);
    return $this->url;
  }

  function send($mblock = '', $params = array()) {
    if (isset($params['application_id']) && empty($params['application_id']))
      $params['application_id'] = isset($params['access_token']) ? $this->apiStandalone : $this->apiServer;
    if (isset($params['language']) && empty($params['language']))
      $params['language'] = $this->language;

    $c = curl_init();
    if (count($params) > 0) {
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    if ($this->protocol == 'https')
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_HTTPHEADER, array(
      "X-Requested-With: XMLHttpRequest",
      "Accept: text/html, */*",
      "User-Agent: Mozilla/3.0 (compatible; easyhttp)",
      "Connection: Keep-Alive",
    ));
    curl_setopt($c, CURLOPT_TIMEOUT, 120);
    curl_setopt($c, CURLOPT_URL, $this->setURL($mblock));
    $data = curl_exec($c);
    curl_close($c);
    $result = @json_decode((string) @$data, true);

    if (!$result)
      return (string) @$data;

    if (!isset($result['status']))
      return $result;

    if ($result['status'] == 'ok')
      return $result['data'];

    if (isset($result[$result['status']])) {
      $erorr = $result[$result['status']];
      switch ((string) $erorr['message']) {
        case 'INVALID_ACCESS_TOKEN':
          unset($params['access_token']);
          return $this->send($mblock, $params);
          break;
      }
      $this->erorr->set($erorr, $mblock, $params);
    }
    return NULL;
  }

  function update() {
    $knowledgeBase = $this->send();
    $fdata = file_get_contents(__FILE__);
    $arclass = array_keys($knowledgeBase['category_names']);
    sort($arclass);
    $fdata = preg_replace("/load_class \= array\((.*)/i", "load_" . "class = array('" . implode("', '", $arclass) . "');", $fdata);
    $_pos = strpos($fdata, "// " . "After this line rewrite code");
    if ($_pos > 0)
      $fdata = substr($fdata, 0, $_pos);
    $fdata .= "// " . "After this line rewrite code" . "\n\n\n";
    $fdata .= "/**\n * {$knowledgeBase['long_name']} \n */\n";
    foreach ($knowledgeBase['category_names'] as $key => $value) {
      $fdata .= "/**\n * {$value} \n */\n";
      $fdata .= "class api_{$this->API_name}_{$key} extends Wg_api\n{\n\n}\n\n";
    }
    var_dump($fdata);
    /*
    if ($file = @fopen(__FILE__, "w")) {
      fwrite($file, $fdata);
      fclose($file);
    }*/
    die("API updated!");
  }

}
