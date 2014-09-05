<?php

class Wgapi extends WgapiCore {

  public $parent = true;

  function load() {
    $this->erorr = new WgApiError();
    if (count($this->load_class) == 0)
      return $this->update();
    foreach ($this->load_class as $class) {
      $load_class = "wgapi_{$this->apiName}_{$class}";
      if (class_exists($load_class)) {
        $this->$class = new $load_class($this);
      } else {
        echo "class not found '{$load_class}'; \n";
        $this->update();
      }
    }
    return true;
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
    return $this->dictionary;
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
    return true;
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
  public $load_class = array('account', 'auth', 'clan', 'encyclopedia', 'globalwar', 'ratings', 'tanks');

  function __construct($params = array()) {
    $params = (array) $params;
    foreach ($params as $key => $value)
      if (!empty($value) && $key != 'parent' && !in_array($key, $this->load_class))
        $this->$key = $value;
    $this->language((string) @$params['language']);
    $this->region((string) @$params['region']);
    $this->setuser(@$params['user']);
    $this->load();
  }

  function load() {
    $this->erorr = new WgApiError();
    return true;
  }

  function language($language = 'ru') {
    $language_list = array('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn');
    if (in_array($language, $language_list))
      $this->language = $language;
    else
      $this->language = 'ru';
    $this->updatevar();
    return $this->language;
  }

  function region($region = 'RU') {
    $region_list = array('ASIA', 'EU', 'KR', 'NA', 'RU');
    if (in_array($region, $region_list)) {
      $this->region = $region;
    } else {
      $this->region = 'RU';
    }
    $this->server = $this->serverDomain . strtolower($this->region);
    $this->updatevar();
    return $this->server;
  }

  function setuser($info = array()) {
    $info = (array) $info;
    if (count($info) == 0)
      return false;
    $this->user = $info;
    $this->token = (string) @$info['token'];
    $this->name = (string) @$info['name'];
    $this->id = (integer) @$info['user_id'];
    $this->expire = (integer) @$info['expire'];
    $this->updatevar();
    return true;
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

  function updatevar() {
    $var = (array) @$this;
    if (count($this->load_class) == 0)
      return false;
    foreach ($this->load_class as $class) {
      $load_class = "wgapi_{$this->apiName}_{$class}";
      if (class_exists($load_class)) {
        if (isset($this->$class))
          foreach ($var as $key => $value)
            if (!empty($value) && $key != 'parent' && !in_array($key, $this->load_class))
              $this->$class->$key = $value;
      } else
        return false;
    }
    return true;
  }

  function update() {
    $knowledgeBase = $this->send();
    if (!$knowledgeBase)
      return false;
    $fdata = file_get_contents(__FILE__);
    $arclass = array_keys($knowledgeBase['category_names']);
    sort($arclass);
    $fdata = preg_replace("/load_class \= array\((.*)/i", "load_" . "class = array('" . implode("', '", $arclass) . "');", $fdata);
    $_pos = strpos($fdata, "// " . "After this line rewrite code");
    if ($_pos > 0)
      $fdata = substr($fdata, 0, $_pos);
    $fdata .= "// " . "After this line rewrite code" . "\n\n\n";
    $fdata .= "/**\n * {$knowledgeBase['long_name']} \n */\n";
    $methods = array();
    $function_exits = array('list');
    foreach ($knowledgeBase['methods'] as $method) {
      $documentation = "";
      $function = "";
      $functional = "";

      $input_form_info = array();
      $input_fields = array();
      foreach ($method['input_form_info']['fields'] as $fields) {
        $input_fields[] = $fields['name'];
        $input_form_info[(($fields['required']) ? 'required' : 'other')][] = $fields;
      }
      sort($input_fields);
      unset($method['input_form_info']);
      foreach ($method['errors'] as &$error) {
        $error[0] = (int) $error[0];
        $error[1] = (string) $error[1];
        $error[2] = (string) $error[2];
        $error = "array({$error[0]}, \"{$error[1]}\", \"{$error[2]}\")";
      }
      if (count($method['errors']) > 0)
        $functional .= "\$this->erorr->add(array(" . implode(", ", $method['errors']) . "));\n";
      unset($method['errors']);

      if (isset($method['allowed_protocols'])) {
        if (is_array($method['allowed_protocols']))
          $functional .= "\$allowed_protocols = array('" . implode("', '", $method['allowed_protocols']) . "');\n";
        unset($method['allowed_protocols']);
      }

      if (isset($method['allowed_http_methods'])) {
        if (is_array($method['allowed_http_methods']))
          $functional .= "\$allowed_http_methods = array('" . implode("', '", $method['allowed_http_methods']) . "');\n";
        unset($method['allowed_http_methods']);
      }

      $functional .= "\$input_fields = array('" . implode("', '", $input_fields) . "');\nforeach (\$input as \$k => \$v) {if (!in_array(\$k, \$input_fields)) {unset(\$input[\$k]);}} unset(\$input_fields);\n";
      if (in_array('access_token', $input_fields))
        $functional .= "if (!isset(\$input['access_token'])) \$input['access_token'] = '';\n";
      if (in_array('application_id', $input_fields))
        $functional .= "if (!isset(\$input['application_id'])) \$input['application_id'] = '';\n";
      if (in_array('language', $input_fields))
        $functional .= "if (!isset(\$input['language'])) \$input['language'] = '';\n";

      $documentation .= "{$method['name']}\n";
      $documentation .= "{$method['description']}\n";
      $documentation .= "@category {$method['category_name']}\n";
      $documentation .= "@link {$method['url']}\n";
      if ($method['deprecated']) {
        $documentation .= "@todo deprecated\n";
      }
      unset($method['name'], $method['description'], $method['category_name'], $method['deprecated']);
      $type_field = array();
      foreach ($input_form_info as $input_form) {
        foreach ($input_form as $fields) {
          $fields['doc_type'] = str_replace(array(', ', 'numeric'), array('|', 'integer'), $fields['doc_type']);
          $documentation .= "@param {$fields['doc_type']} \$input['{$fields['name']}'] {$fields['help_text']}\n";
          if ($fields['deprecated']) {
            $documentation .= "@todo deprecated \$input['{$fields['name']}'] {$fields['deprecated_text']}\n";
          }
          switch ($fields['doc_type']) {
            case 'string':
            case 'string|list':
              $type_field['string'][] = $fields['name'];
              break;
            case 'timestamp/date':
            case 'integer':
            case 'integer|list':
              $type_field['integer'][] = $fields['name'];
              break;
            case 'float':
            case 'float|list':
              $type_field['float'][] = $fields['name'];
              break;
            case 'boolean':
              $type_field['boolean'][] = $fields['name'];
              break;
          }
        }
      }
      foreach ($type_field as $type => $field) {
        if (count($field) > 0) {
          $functional .= "foreach(array('" . implode("', '", $field) . "') as \$field) {if (isset(\$input[\$field])) { if (is_array(\$input[\$field])) { foreach (\$input[\$field] as &\$field) { \$field = ({$type}) \$field;} \$input[\$field] = implode(',', \$input[\$field]);} else { \$input[\$field] = ({$type}) \$input[\$field];}}}\n";
        }
      }
      $documentation .= "@return array\n";
      $documentation = "\n/**\n * " . str_replace("\n", "\n * ", trim($documentation)) . "\n */\n";
      $functional .= "\$output = \$this->send('{$method['url']}', \$input, \$http_method, \$protocol);\n";
      $functional .= "return \$output;\n";


      $function_name = explode('/', $method["url"]);
      $prefix = in_array($function_name[1], $function_exits) ? 's' : '';
      $function = "function {$function_name[1]}{$prefix} (\$input = array()) {\n{$functional}\n}\n";
      $methods[$function_name[0]] = (@$methods[$function_name[0]] ? $methods[$function_name[0]] : '') . $documentation . $function;
    }


    foreach ($knowledgeBase['category_names'] as $key => $name) {
      $fdata .= "/**\n * {$name} \n */\n";
      $fdata .= "class wgapi_{$this->apiName}_{$key} extends WgApiCore {\n{$methods[$key]}\n}\n\n";
    }
    if ($file = @fopen(__FILE__ . '.php', "w")) {
      fwrite($file, $fdata);
      fclose($file);
    }
    die("API updated!");
    return true;
  }

}
