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

  function add($errors = array()) {
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

  function validate_input(&$input, $required = array(), $other = array()) {
    foreach (array('access_token', 'application_id', 'language') as $filed)
      if ((isset($required[$filed]) || isset($other[$filed])) && !isset($input[$filed]))
        $input[$filed] = '';

    if (isset($input['fields'])) {
      if (is_array($input['fields']))
        $input['fields'] = (string) @implode(',', $input['fields']);
      else
        $input['fields'] = (string) @$input['fields'];
    }

    foreach ($input as $k => $v)
      if (!isset($required[$filed]) && !isset($other[$filed]))
        unset($input[$k]);

    foreach ($required as $filed => $type)
      if (!isset($input[$filed])) {
        $this->erorr->set(array('code' => 402, 'field' => $filed, 'message' => strtoupper($filed) . '_NOT_SPECIFIED'));
        return false;
      }

    foreach ($required as $filed => $type)
      if (isset($input[$filed]))
        switch ($type) {
          case 'string': $input[$filed] = (string) @$input[$filed];
            break;
          case 'timestamp/date':
          case 'numeric': $input[$filed] = (int) @$input[$filed];
            break;
          case 'float': $input[$filed] = (float) @$input[$filed];
            break;
          case 'string, list':
            if (is_array($input[$filed])) {
              foreach ($input[$filed] as &$value)
                $value = (string) @$value;
              $input[$filed] = (string) @implode(',', $input[$filed]);
            } else {
              $input[$filed] = (string) @$input[$filed];
            }
            break;
          case 'timestamp/date, list':
          case 'numeric, list':
            if (is_array($input[$filed])) {
              foreach ($input[$filed] as &$value)
                $value = (int) @$value;
              $input[$filed] = (string) @implode(',', $input[$filed]);
            } else {
              $input[$filed] = (string) @$input[$filed];
            }
            break;
          case 'float, list':
            if (is_array($input[$filed])) {
              foreach ($input[$filed] as &$value)
                $value = (float) @$value;
              $input[$filed] = (string) @implode(',', $input[$filed]);
            } else {
              $input[$filed] = (string) @$input[$filed];
            }
            break;
        }
    return true;
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
      $url = $method["url"];
      $function_name = explode('/', $url);

      $input_form_info = array();
      $input_fields = array();
      foreach ($method['input_form_info']['fields'] as $fields) {
        $input_fields[(($fields['required']) ? 'required' : 'other')][$fields['name']] = $fields['doc_type'];
        $input_form_info[(($fields['required']) ? 'required' : 'other')][] = $fields;
      }
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

      $protocol = "array()";
      if (isset($method['allowed_protocols']))
        if (is_array($method['allowed_protocols']))
          $protocol = "array('" . implode("', '", $method['allowed_protocols']) . "')";
      unset($method['allowed_protocols'], $method['allowed_http_methods']);

      foreach ($input_fields as &$type)
        foreach ($type as $key => &$field) {
          $field = "'{$key}' => '{$field}'";
        }
      $functional .= "if (!\$this->validate_input(\$input, array(" . implode(", ", (array) @$input_fields['required']) . "), array(" . implode(", ", (array) @$input_fields['other']) . ")))\nreturn NULL;\n";
      unset($input_fields);
      $documentation .= "{$method['name']}\n";
      $documentation .= "{$method['description']}\n";
      $documentation .= "@category {$method['category_name']}\n";
      $documentation .= "@link {$method['url']}\n";
      if ($method['deprecated']) {
        $documentation .= "@todo deprecated\n";
      }
      $documentation .= "@param array \$input\n";
      unset($method['name'], $method['url'], $method['description'], $method['category_name'], $method['deprecated']);
      foreach ($input_form_info as $input_form) {
        foreach ($input_form as $fields) {
          $fields['doc_type'] = str_replace(', ', '|', $fields['doc_type']);
          $documentation .= "@param {$fields['doc_type']} \$input['{$fields['name']}'] {$fields['help_text']}\n";
          if ($fields['deprecated'])
            $documentation .= "@todo deprecated \$input['{$fields['name']}'] {$fields['deprecated_text']}\n";
        }
      }

      $documentation .= "@return array\n";
      $documentation .= json_encode($method) . "\n";
      $documentation = "\n/**\n * " . str_replace(array("\n", "&mdash;", "  "), array("\n * ", "-", " "), trim($documentation)) . "\n */\n";
      $functional .= "\$output = \$this->send('{$url}', \$input, {$protocol});\n";
      $functional .= "return \$output;";
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
// After this line rewrite code


/**
 * World of Tanks 
 */
/**
 * Кланы 
 */
class wgapi_wot_clan extends WgApiCore {

/**
 * Список кланов
 * Возвращает часть списка кланов, отсортированного по имени (default) или по дате создания, тегу, численности и отфильтрованного по начальной части имени или аббревиатуры.
 * 
 * Не возвращает весь список кланов.
 * @category Кланы
 * @link clan/list
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['search'] Начальная часть имени или аббревиатуры клана по которому осуществляется поиск.
 * @param numeric $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
 * @param string $input['order_by'] Вид сортировки.. Допустимые значения: 
 * 
 * * "name" - по имени клана 
 * * "-name" - по имени клана в обратном порядке 
 * * "members_count" - по численности клана 
 * * "-members_count" - по численности клана в обратном порядке 
 * * "created_at" - по дате создания клана 
 * * "-created_at" - по дате создания клана в обратном порядке 
 * * "abbreviation" - по тегу клана 
 * * "-abbreviation" - по тегу клана в обратном порядке 
 * @param numeric $input['page_no'] Номер страницы выдачи
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"motto","deprecated":false,"required":true,"help_text":"\u0414\u0435\u0432\u0438\u0437 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"abbreviation","deprecated":false,"required":true,"help_text":"\u0422\u044d\u0433 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"color","deprecated":false,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043c\u0431\u043b\u0435\u043c\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"string","name":"large","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"small","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 24x24","deprecated_text":""},{"doc_type":"string","name":"bw_tank","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u043d\u0430 \u0442\u0430\u043d\u043a\u0435 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"medium","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 32x32","deprecated_text":""}],"deprecated_text":"","name":"emblems","deprecated":false},{"doc_type":"numeric","name":"owner_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"members_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0441\u043e\u0437\u0434\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"owner_name","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function lists ($input = array()) {
$this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'search' => 'string', 'limit' => 'numeric', 'order_by' => 'string', 'page_no' => 'numeric')))
return NULL;
$output = $this->send('clan/list', $input, array('http', 'https'));
return $output;
}

/**
 * Данные клана
 * Возвращает информацию о клане.
 * @category Кланы
 * @link clan/info
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"motto","deprecated":false,"required":true,"help_text":"\u0414\u0435\u0432\u0438\u0437 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"abbreviation","deprecated":false,"required":true,"help_text":"\u0422\u044d\u0433 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"color","deprecated":false,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043c\u0431\u043b\u0435\u043c\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"string","name":"large","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"small","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 24x24","deprecated_text":""},{"doc_type":"string","name":"bw_tank","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u043d\u0430 \u0442\u0430\u043d\u043a\u0435 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"medium","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 32x32","deprecated_text":""}],"deprecated_text":"","name":"emblems","deprecated":false},{"doc_type":"numeric","name":"owner_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"members_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0441\u043e\u0437\u0434\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"clan_color","deprecated":true,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":"\u041f\u043e\u043b\u0435 \u043f\u0435\u0440\u0435\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u043e \u0432 color"},{"doc_type":"string","name":"description","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0441\u0442 \u043e\u043f\u0438\u0441\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"description_html","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0441\u0442 \u043e\u043f\u0438\u0441\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430 \u0432 HTML","deprecated_text":""},{"doc_type":"boolean","name":"is_clan_disbanded","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a \u0442\u043e\u0433\u043e \u0431\u044b\u043b \u043a\u043b\u0430\u043d \u0440\u0430\u0441\u043f\u0443\u0449\u0435\u043d \u0438\u043b\u0438 \u043d\u0435\u0442","deprecated_text":""},{"doc_type":"timestamp","name":"updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u0438 \u043e \u043a\u043b\u0430\u043d\u0435","deprecated_text":""},{"doc_type":"boolean","name":"request_availability","deprecated":false,"required":true,"help_text":"\u041c\u043e\u0436\u0435\u0442 \u043b\u0438 \u043a\u043b\u0430\u043d \u043f\u0440\u0438\u0433\u043b\u0430\u0448\u0430\u0442\u044c \u0438\u0433\u0440\u043e\u043a\u043e\u0432 \u043a \u0441\u0435\u0431\u0435","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0432\u0441\u0442\u0443\u043f\u043b\u0435\u043d\u0438\u044f \u0432 \u043a\u043b\u0430\u043d","deprecated_text":""},{"doc_type":"string","name":"role","deprecated":false,"required":true,"help_text":"\u0420\u043e\u043b\u044c \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"role_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u043f\u0430\u0440\u0430\u043c\u0435\u0442\u0440\u0430 role","deprecated_text":""},{"doc_type":"string","name":"account_name","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u0430","deprecated_text":""}],"deprecated_text":"","name":"members","deprecated":false},{"help_text":"\u041f\u0440\u0438\u0432\u0430\u0442\u043d\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"numeric","name":"chips_count","deprecated":false,"required":true,"help_text":"\u041e\u0431\u0449\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0444\u0438\u0448\u0435\u043a \u043a\u043b\u0430\u043d\u0430, \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u0434\u043e\u0441\u0442\u0443\u043f\u043d\u0430 \u0441\u043e\u0433\u043b\u0430\u0441\u043d\u043e \u0438\u0433\u0440\u043e\u0432\u043e\u0439 \u043f\u043e\u043b\u0438\u0442\u0438\u043a\u0435 \"\u041c\u0438\u0440\u043e\u0432\u043e\u0439 \u0432\u043e\u0439\u043d\u044b\"","deprecated_text":""},{"doc_type":"numeric","name":"treasury","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0433\u043e\u043b\u0434\u044b \u0432 \u043a\u0430\u0437\u043d\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"private","deprecated":false},{"doc_type":"numeric","name":"victory_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u0437\u0430 \u0432\u0441\u044e \u043a\u0430\u043c\u043f\u0430\u043d\u0438\u044e","deprecated_text":""},{"doc_type":"numeric","name":"victory_points_step_delta","deprecated":false,"required":true,"help_text":"\u041e\u0447\u043a\u0438 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430 \u0437\u0430 \u0442\u0435\u043a\u0443\u0449\u0438\u0439 \u044d\u0442\u0430\u043f","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function info ($input = array()) {
$this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string')))
return NULL;
$output = $this->send('clan/info', $input, array('http', 'https'));
return $output;
}

/**
 * Список боёв клана
 * Возвращает список боев клана.
 * @category Кланы
 * @link clan/battles
 * @todo deprecated
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param numeric $input['map_id'] Идентификатор карты
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u0431\u043e\u044f\n\n * **for_province** \u2014 \u0431\u043e\u0439 \u0437\u0430 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u044e\n * **meeting_engagement** \u2014 \u0432\u0441\u0442\u0440\u0435\u0447\u043d\u044b\u0439 \u0431\u043e\u0439\n * **landing** \u2014 \u0442\u0443\u0440\u043d\u0438\u0440 \u0437\u0430 \u0432\u044b\u0441\u0430\u0434\u043a\u0443\n","deprecated_text":""},{"doc_type":"boolean","name":"started","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a \u043d\u0430\u0447\u0430\u043b\u0430 \u0431\u043e\u044f","deprecated_text":""},{"doc_type":"timestamp","name":"time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043d\u0430\u0447\u0430\u043b\u0430 \u0431\u043e\u044f","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0430\u0440\u0435\u043d\u0435","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0430\u0440\u0435\u043d\u044b","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u043f\u043e\u043b\u044f name","deprecated_text":""}],"deprecated_text":"","name":"arenas","deprecated":false},{"doc_type":"list of strings","name":"provinces","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0439","deprecated_text":""},{"help_text":"\u041f\u0440\u0438\u0432\u0430\u0442\u043d\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"numeric","name":"chips","deprecated":false,"required":true,"help_text":"\u0427\u0438\u0441\u043b\u043e \u0444\u0438\u0448\u0435\u043a","deprecated_text":""}],"deprecated_text":"","name":"private","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function battles ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'map_id' => 'numeric')))
return NULL;
$output = $this->send('clan/battles', $input, array('http', 'https'));
return $output;
}

/**
 * Топ кланов по очкам победы
 * Возвращает часть списка кланов, отсортированного по рейтингу
 * 
 * Не возвращает весь список кланов, только первые 100.
 * @category Кланы
 * @link clan/top
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['time'] Временной промежуток. Допустимые значения: 
 * 
 * * "current_season" - Текущее событие (используется по умолчанию)
 * * "current_step" - Текущий этап 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"motto","deprecated":false,"required":true,"help_text":"\u0414\u0435\u0432\u0438\u0437 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"abbreviation","deprecated":false,"required":true,"help_text":"\u0422\u044d\u0433 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"color","deprecated":false,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043c\u0431\u043b\u0435\u043c\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"string","name":"large","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"small","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 24x24","deprecated_text":""},{"doc_type":"string","name":"bw_tank","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u043d\u0430 \u0442\u0430\u043d\u043a\u0435 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"medium","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 32x32","deprecated_text":""}],"deprecated_text":"","name":"emblems","deprecated":false},{"doc_type":"numeric","name":"owner_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"members_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0441\u043e\u0437\u0434\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"owner_name","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"rating_position","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0441\u0442\u043e \u0432 \u0440\u0435\u0439\u0442\u0438\u043d\u0433\u0435","deprecated_text":""},{"doc_type":"numeric","name":"victory_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u0437\u0430 \u0432\u0441\u044e \u043a\u0430\u043c\u043f\u0430\u043d\u0438\u044e","deprecated_text":""},{"doc_type":"numeric","name":"victory_points_turn_delta","deprecated":true,"required":true,"help_text":"\u041e\u0447\u043a\u0438 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430 \u0437\u0430 \u043f\u043e\u0441\u043b\u0435\u0434\u043d\u0438\u0439 \u0445\u043e\u0434","deprecated_text":""},{"doc_type":"numeric","name":"victory_points_step_delta","deprecated":false,"required":true,"help_text":"\u041e\u0447\u043a\u0438 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430 \u0437\u0430 \u0442\u0435\u043a\u0443\u0449\u0438\u0439 \u044d\u0442\u0430\u043f","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function top ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'string', 'time' => 'string')))
return NULL;
$output = $this->send('clan/top', $input, array('http', 'https'));
return $output;
}

/**
 * Провинции клана
 * Возвращает списка провинций клана
 * @category Кланы
 * @link clan/provinces
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"arena_id","deprecated":false,"required":true,"help_text":"\u041d\u043e\u043c\u0435\u0440 \u043a\u0430\u0440\u0442\u044b","deprecated_text":""},{"doc_type":"string","name":"arena_name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u0430\u0440\u0442\u044b \u0432 World of Tanks, \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u043e\u0439 \u0431\u0443\u0434\u0443\u0442 \u043f\u0440\u043e\u0438\u0441\u0445\u043e\u0434\u0438\u0442\u044c \u0431\u043e\u0438 \u0437\u0430 \u044d\u0442\u0443 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u044e","deprecated_text":""},{"doc_type":"boolean","name":"attacked","deprecated":false,"required":true,"help_text":"\u0410\u0442\u0430\u043a\u043e\u0432\u0430\u043d\u0430 \u043b\u0438 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"boolean","name":"combats_running","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0443\u0442 \u043b\u0438 \u0432 \u0434\u0430\u043d\u043d\u044b\u0439 \u043c\u043e\u043c\u0435\u043d\u0442 \u0431\u043e\u0438 \u043d\u0430 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"occupancy_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u0432\u043b\u0430\u0434\u0435\u043d\u0438\u044f \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0435\u0439 \u0432 \u0434\u043d\u044f\u0445","deprecated_text":""},{"doc_type":"timestamp","name":"prime_time","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0430\u0439\u043c-\u0442\u0430\u0439\u043c","deprecated_text":""},{"doc_type":"string","name":"province_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"revenue","deprecated":false,"required":true,"help_text":"\u0421\u0443\u0442\u043e\u0447\u043d\u044b\u0439 \u0434\u043e\u0445\u043e\u0434 \u0441 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"help_text":"\u041f\u0440\u0438\u0432\u0430\u0442\u043d\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"boolean","name":"capital","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a \u0443\u043a\u0430\u0437\u044b\u0432\u0430\u0435\u0442 \u0440\u0430\u0437\u043c\u0435\u0449\u0435\u043d\u0430 \u043b\u0438 \u0432 \u043f\u0440\u043e\u0444\u0438\u043d\u0446\u0438\u0438 \u0421\u0442\u0430\u0432\u043a\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"private","deprecated":false},{"doc_type":"string","name":"map_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u0430\u0440\u0442\u044b","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function provinces ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string')))
return NULL;
$output = $this->send('clan/provinces', $input, array('http', 'https'));
return $output;
}

/**
 * Очки победы клана
 * Количество очков победы у клана
 * @category Кланы
 * @link clan/victorypoints
 * @todo deprecated
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"points","deprecated":false,"required":true,"help_text":"\u041e\u0447\u043a\u0438 \u043f\u043e\u0431\u0435\u0434\u044b","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function victorypoints ($input = array()) {
$this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('clan/victorypoints', $input, array('http', 'https'));
return $output;
}

/**
 * История начисления очков победы клана
 * История начислений очков победы для клана
 * @category Кланы
 * @link clan/victorypointshistory
 * @todo deprecated
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric $input['map_id'] Идентификатор карты
 * @param timestamp/date $input['since'] Начало периода
 * @param timestamp/date $input['until'] Конец периода
 * @param numeric $input['offset'] Сдвиг относительно первого результата
 * @param numeric $input['limit'] Кол-во результатов (от 20 до 100)
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043a\u043e\u043b-\u0432\u0435 \u0440\u0435\u0437\u0443\u043b\u044c\u0442\u0430\u0442\u043e\u0432","fields":[{"doc_type":"numeric","name":"total_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0440\u0435\u0437\u0443\u043b\u044c\u0442\u0430\u0442\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"page","deprecated":false},{"help_text":"\u0418\u0441\u0442\u043e\u0440\u0438\u044f \u043d\u0430\u0447\u0438\u0441\u043b\u0435\u043d\u0438\u044f \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"numeric","name":"turn_id","deprecated":true,"required":true,"help_text":"ID \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"current_clan_victory_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430 \u043f\u043e\u0441\u043b\u0435 \u0441\u043e\u0432\u0435\u0440\u0448\u0435\u043d\u0438\u044f \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u043e\u0435 \u0431\u044b\u043b\u0430 \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0435\u043d\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u044f (\u043c\u043e\u0436\u0435\u0442 \u0431\u044b\u0442\u044c \u043a\u0430\u043a \u043f\u043e\u043b\u043e\u0436\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u043c \u0442\u0430\u043a \u0438 \u043e\u0442\u0440\u0438\u0446\u0430\u0442\u0435\u043b\u044c\u043d\u044b\u043c)","deprecated_text":""},{"doc_type":"string","name":"factor","deprecated":true,"required":true,"help_text":"\u0422\u0435\u0445\u043d\u0438\u0447\u0435\u0441\u043a\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u0444\u0430\u043a\u0442\u043e\u0440\u0430, \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043f\u0440\u0438\u0432\u0435\u043b \u043a \u0438\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u044e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"factor_message","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0441\u0442\u043e\u0432\u043e\u0435 \u043e\u043f\u0438\u0441\u0430\u043d\u0438\u0435 \u0444\u0430\u043a\u0442\u043e\u0440\u0430, \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043f\u0440\u0438\u0432\u0435\u043b \u043a \u0438\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u044e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"victorypointshistory","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function victorypointshistory ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'numeric', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric')))
return NULL;
$output = $this->send('clan/victorypointshistory', $input, array('http', 'https'));
return $output;
}

/**
 * Информация о члене клана
 * 
 * @category Кланы
 * @link clan/membersinfo
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['member_id'] Идентификатор члена клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"clan_name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"abbreviation","deprecated":false,"required":true,"help_text":"\u0422\u044d\u0433 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"color","deprecated":false,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043c\u0431\u043b\u0435\u043c\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"string","name":"large","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"small","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 24x24","deprecated_text":""},{"doc_type":"string","name":"bw_tank","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u043d\u0430 \u0442\u0430\u043d\u043a\u0435 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"medium","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 32x32","deprecated_text":""}],"deprecated_text":"","name":"emblems","deprecated":false},{"doc_type":"string","name":"role","deprecated":false,"required":true,"help_text":"\u0420\u043e\u043b\u044c \u0438\u0433\u0440\u043e\u043a\u0430 \u0432 \u043a\u043b\u0430\u043d\u0435","deprecated_text":""},{"doc_type":"string","name":"role_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u043f\u0430\u0440\u0430\u043c\u0435\u0442\u0440\u0430 role","deprecated_text":""},{"doc_type":"timestamp","name":"since","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0432\u0441\u0442\u0443\u043f\u043b\u0435\u043d\u0438\u044f \u0432 \u043a\u043b\u0430\u043d","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function membersinfo ($input = array()) {
$this->erorr->add(array(array(407, "MEMBER_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **member_id** превышен ( >100 )")));
if (!$this->validate_input($input, array('application_id' => 'string', 'member_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('clan/membersinfo', $input, array('http', 'https'));
return $output;
}

}

/**
 * Рейтинги игроков 
 */
class wgapi_wot_ratings extends WgApiCore {

/**
 * Типы рейтингов
 * Возвращает словарь типов рейтингов и информацию о них.
 * @category Рейтинги игроков
 * @link ratings/types
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u0440\u0435\u0439\u0442\u0438\u043d\u0433\u0430","deprecated_text":""},{"doc_type":"numeric","name":"threshold","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0440\u043e\u0433 \u0432\u0445\u043e\u0436\u0434\u0435\u043d\u0438\u044f \u0432 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","deprecated_text":""},{"doc_type":"list of strings","name":"rank_fields","deprecated":false,"required":true,"help_text":"\u0421\u043f\u0438\u0441\u043e\u043a \u043a\u0430\u0442\u0435\u0433\u043e\u0440\u0438\u0439 \u0440\u0435\u0439\u0442\u0438\u043d\u0433\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function types ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('ratings/types', $input, array('http', 'https'));
return $output;
}

/**
 * Рейтинги игроков
 * Возвращает рейтинги игроков по заданным идентификаторам.
 * @category Рейтинги игроков
 * @link ratings/accounts
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['account_id'] Идентификаторы аккаунтов игроков
 * @param string $input['type'] Тип рейтинга. Допустимые значения: 
 * 
 * * "1" - 1 
 * * "all" - all 
 * * "28" - 28 
 * * "7" - 7 
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param timestamp/date $input['date'] Дата в формате UNIX timestamp либо ISO 8601. Например, 1376542800 либо 2013-08-15T00:00:00
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"battles_to_play","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0431\u043e\u0451\u0432 \u0434\u043e \u0432\u0445\u043e\u0436\u0434\u0435\u043d\u0438\u044f \u0432 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","deprecated_text":""},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0435\u043d\u043d\u044b\u0445 \u0431\u043e\u0435\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"battles_count","deprecated":false},{"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_max","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_avg","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u0431\u0435\u0434","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"wins_ratio","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_count","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"hits_ratio","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_dealt","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043e\u043f\u044b\u0442","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_amount","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_count","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_avg","deprecated":false},{"help_text":"\u0413\u043b\u043e\u0431\u0430\u043b\u044c\u043d\u044b\u0439 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"global_rating","deprecated":false},{"help_text":"\u041e\u0447\u043a\u0438 \u0437\u0430\u0445\u0432\u0430\u0442\u0430 \u0431\u0430\u0437\u044b","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"capture_points","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u0432\u044b\u0436\u0438\u0432\u0430\u0435\u043c\u043e\u0441\u0442\u0438","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"survived_ratio","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function accounts ($input = array()) {
$this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date')))
return NULL;
$output = $this->send('ratings/accounts', $input, array('http', 'https'));
return $output;
}

/**
 * Соседи игрока по рейтингу
 * Возвращает список соседей по заданному рейтингу.
 * @category Рейтинги игроков
 * @link ratings/neighbors
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['type'] Тип рейтинга. Допустимые значения: 
 * 
 * * "1" - 1 
 * * "all" - all 
 * * "28" - 28 
 * * "7" - 7 
 * @param string $input['rank_field'] Категория рейтинга
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param timestamp/date $input['date'] Дата в формате UNIX timestamp либо ISO 8601. Например, 1376542800 либо 2013-08-15T00:00:00
 * @param numeric $input['limit'] Лимит количества соседей
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"battles_to_play","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0431\u043e\u0451\u0432 \u0434\u043e \u0432\u0445\u043e\u0436\u0434\u0435\u043d\u0438\u044f \u0432 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","deprecated_text":""},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0435\u043d\u043d\u044b\u0445 \u0431\u043e\u0435\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"battles_count","deprecated":false},{"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_max","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_avg","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u0431\u0435\u0434","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"wins_ratio","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_count","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"hits_ratio","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_dealt","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043e\u043f\u044b\u0442","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_amount","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_count","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_avg","deprecated":false},{"help_text":"\u0413\u043b\u043e\u0431\u0430\u043b\u044c\u043d\u044b\u0439 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"global_rating","deprecated":false},{"help_text":"\u041e\u0447\u043a\u0438 \u0437\u0430\u0445\u0432\u0430\u0442\u0430 \u0431\u0430\u0437\u044b","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"capture_points","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u0432\u044b\u0436\u0438\u0432\u0430\u0435\u043c\u043e\u0441\u0442\u0438","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"survived_ratio","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function neighbors ($input = array()) {
$this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric')))
return NULL;
$output = $this->send('ratings/neighbors', $input, array('http', 'https'));
return $output;
}

/**
 * Топ игроков
 * Возвращает топ игроков по заданному параметру.
 * @category Рейтинги игроков
 * @link ratings/top
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['type'] Тип рейтинга. Допустимые значения: 
 * 
 * * "1" - 1 
 * * "all" - all 
 * * "28" - 28 
 * * "7" - 7 
 * @param string $input['rank_field'] Категория рейтинга
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param timestamp/date $input['date'] Дата в формате UNIX timestamp либо ISO 8601. Например, 1376542800 либо 2013-08-15T00:00:00
 * @param numeric $input['limit'] Лимит количества игроков в топе
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"battles_to_play","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0431\u043e\u0451\u0432 \u0434\u043e \u0432\u0445\u043e\u0436\u0434\u0435\u043d\u0438\u044f \u0432 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","deprecated_text":""},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0435\u043d\u043d\u044b\u0445 \u0431\u043e\u0435\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"battles_count","deprecated":false},{"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_max","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_avg","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u0431\u0435\u0434","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"wins_ratio","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_count","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"hits_ratio","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_dealt","deprecated":false},{"help_text":"\u041e\u0431\u0449\u0438\u0439 \u043e\u043f\u044b\u0442","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"xp_amount","deprecated":false},{"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_count","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"frags_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043d\u0430\u043d\u0435\u0441\u0435\u043d\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"damage_avg","deprecated":false},{"help_text":"\u0421\u0440\u0435\u0434\u043d\u0435\u0435 \u043a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432 \u0437\u0430 \u0431\u043e\u0439","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"spotted_avg","deprecated":false},{"help_text":"\u0413\u043b\u043e\u0431\u0430\u043b\u044c\u043d\u044b\u0439 \u0440\u0435\u0439\u0442\u0438\u043d\u0433","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"global_rating","deprecated":false},{"help_text":"\u041e\u0447\u043a\u0438 \u0437\u0430\u0445\u0432\u0430\u0442\u0430 \u0431\u0430\u0437\u044b","fields":[{"doc_type":"numeric","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"capture_points","deprecated":false},{"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u0432\u044b\u0436\u0438\u0432\u0430\u0435\u043c\u043e\u0441\u0442\u0438","fields":[{"doc_type":"float","name":"value","deprecated":false,"required":true,"help_text":"\u0410\u0431\u0441\u043e\u043b\u044e\u0442\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u0435 \u043f\u043e\u0437\u0438\u0446\u0438\u0438","deprecated_text":""}],"deprecated_text":"","name":"survived_ratio","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function top ($input = array()) {
$this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
if (!$this->validate_input($input, array('application_id' => 'string', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric')))
return NULL;
$output = $this->send('ratings/top', $input, array('http', 'https'));
return $output;
}

/**
 * Даты доступных рейтингов
 * Возвращает даты, за которые есть рейтинговые данные
 * @category Рейтинги игроков
 * @link ratings/dates
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['type'] Тип рейтинга. Допустимые значения: 
 * 
 * * "1" - 1 
 * * "all" - all 
 * * "28" - 28 
 * * "7" - 7 
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['account_id'] Идентификатор аккаунта игрока
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"list of timestamps","name":"dates","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u044b, \u0437\u0430 \u043a\u043e\u0442\u043e\u0440\u044b\u0435 \u0435\u0441\u0442\u044c \u0440\u0435\u0439\u0442\u0438\u043d\u0433\u043e\u0432\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function dates ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'account_id' => 'numeric, list')))
return NULL;
$output = $this->send('ratings/dates', $input, array('http', 'https'));
return $output;
}

}

/**
 * Аккаунт 
 */
class wgapi_wot_account extends WgApiCore {

/**
 * Список игроков
 * Возвращает часть списка игроков, отсортированного по имени и отфильтрованного по его начальной части.
 * 
 * Не возвращает весь список игроков.
 * @category Аккаунт
 * @link account/list
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['search'] 
 *     Строка поиска по имени игрока. Вид поиска и минимальная длина строки поиска зависят от параметра type.
 *     Максимальная длина строки поиска 24 символа.
 *   
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['type'] Тип поиска. Влияет на минимальную длину строки поиска и вид поиска. По умолчанию используется **startswith**. Допустимые значения: 
 * 
 * * "startswith" - Поиск по начальной части имени игрока. Минимальная длина 3 символа, поиск без учета регистра (используется по умолчанию)
 * * "exact" - Поиск по строгому соответствию имени игрока. Минимальная длина строки поиска 1 символ, поиск без учета регистра 
 * @param numeric $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"id","deprecated":true,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":"\u041f\u043e\u043b\u0435 \u043f\u0435\u0440\u0435\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u043e \u0432 account_id"},{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"nickname","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function lists ($input = array()) {
$this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
if (!$this->validate_input($input, array('application_id' => 'string', 'search' => 'string'), array('language' => 'string', 'fields' => 'string', 'type' => 'string', 'limit' => 'numeric')))
return NULL;
$output = $this->send('account/list', $input, array('http', 'https'));
return $output;
}

/**
 * Данные игрока
 * Возвращает информацию об игроке.
 * @category Аккаунт
 * @link account/info
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","fields":[{"doc_type":"numeric","name":"max_xp","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u044b\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""},{"doc_type":"numeric","name":"max_damage","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""},{"doc_type":"numeric","name":"max_damage_vehicle","deprecated":false,"required":true,"help_text":"\u0422\u0435\u0445\u043d\u0438\u043a\u0430, \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u043e\u0439 \u0431\u044b\u043b \u043d\u0430\u043d\u0435\u0441\u0435\u043d \u043c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""},{"help_text":"\u0412\u0441\u044f \u0441\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"all","deprecated":false},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u0440\u043e\u0442\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"company","deprecated":false},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u043a\u043b\u0430\u043d\u043e\u0432\u044b\u0445 \u0431\u043e\u0451\u0432","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"clan","deprecated":false},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u0438\u0441\u0442\u043e\u0440\u0438\u0447\u0435\u0441\u043a\u0438\u0445 \u0431\u043e\u0451\u0432","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"historical","deprecated":false}],"deprecated_text":"","name":"statistics","deprecated":false},{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"help_text":"\u0414\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u0438\u0433\u0440\u043e\u043a\u0430","fields":[{"doc_type":"numeric","name":"warrior","deprecated":false,"required":true,"help_text":"\u0412\u043e\u0438\u043d","deprecated_text":""},{"doc_type":"numeric","name":"invader","deprecated":false,"required":true,"help_text":"\u0417\u0430\u0445\u0432\u0430\u0442\u0447\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"sniper","deprecated":false,"required":true,"help_text":"\u0421\u043d\u0430\u0439\u043f\u0435\u0440","deprecated_text":""},{"doc_type":"numeric","name":"sniper2","deprecated":false,"required":true,"help_text":"\u0422\u0430\u043d\u043a\u0438\u0441\u0442-\u0421\u043d\u0430\u0439\u043f\u0435\u0440","deprecated_text":""},{"doc_type":"numeric","name":"main_gun","deprecated":false,"required":true,"help_text":"\u041e\u0441\u043d\u043e\u0432\u043d\u043e\u0439 \u043a\u0430\u043b\u0438\u0431\u0440","deprecated_text":""},{"doc_type":"numeric","name":"defender","deprecated":false,"required":true,"help_text":"\u0417\u0430\u0449\u0438\u0442\u043d\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"steelwall","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0442\u0435\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"supporter","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0434\u0434\u0435\u0440\u0436\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"scout","deprecated":false,"required":true,"help_text":"\u0420\u0430\u0437\u0432\u0435\u0434\u0447\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"evileye","deprecated":false,"required":true,"help_text":"\u0414\u043e\u0437\u043e\u0440\u043d\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"medal_boelter","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0411\u0451\u043b\u044c\u0442\u0435\u0440\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_orlik","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041e\u0440\u043b\u0438\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_oskin","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041e\u0441\u044c\u043a\u0438\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_kolobanov","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041a\u043e\u043b\u043e\u0431\u0430\u043d\u043e\u0432\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_halonen","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0425\u0430\u043b\u043e\u043d\u0435\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_fadin","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0424\u0430\u0434\u0438\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_burda","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0411\u0443\u0440\u0434\u044b","deprecated_text":""},{"doc_type":"numeric","name":"medal_billotte","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0411\u0438\u0439\u043e\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_heroes_of_rassenay","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0433\u0435\u0440\u043e\u0435\u0432 \u0420\u0430\u0441\u0435\u0439\u043d\u044f\u044f","deprecated_text":""},{"doc_type":"numeric","name":"medal_bruno_pietro","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0411\u0440\u0443\u043d\u043e","deprecated_text":""},{"doc_type":"numeric","name":"medal_tarczay","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0422\u0430\u0440\u0446\u0430\u044f","deprecated_text":""},{"doc_type":"numeric","name":"medal_radley_walters","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0420\u044d\u0434\u043b\u0438-\u0423\u043e\u043b\u0442\u0435\u0440\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_lafayette_pool","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041f\u0443\u043b\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_pascucci","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041f\u0430\u0441\u043a\u0443\u0447\u0447\u0438","deprecated_text":""},{"doc_type":"numeric","name":"medal_dumitru","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0414\u0443\u043c\u0438\u0442\u0440\u0443","deprecated_text":""},{"doc_type":"numeric","name":"medal_lehvaslaiho","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041b\u0435\u0445\u0432\u0435\u0441\u043b\u0430\u0439\u0445\u043e","deprecated_text":""},{"doc_type":"numeric","name":"medal_nikolas","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041d\u0438\u043a\u043e\u043b\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_tamada_yoshio","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0422\u0430\u043c\u0430\u0434\u044b \u0419\u043e\u0448\u0438\u043e","deprecated_text":""},{"doc_type":"numeric","name":"medal_delanglade","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0434\u0435 \u041b\u0430\u043d\u0433\u043b\u0430\u0434\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_kay","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041a\u0435\u044f","deprecated_text":""},{"doc_type":"numeric","name":"medal_carius","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041a\u0430\u0440\u0438\u0443\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_knispel","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041a\u043d\u0438\u0441\u043f\u0435\u043b\u044f","deprecated_text":""},{"doc_type":"numeric","name":"medal_poppel","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041f\u043e\u043f\u0435\u043b\u044f","deprecated_text":""},{"doc_type":"numeric","name":"medal_abrams","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u0410\u0431\u0440\u0430\u043c\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_le_clerc","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041b\u0435\u043a\u043b\u0435\u0440\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_lavrinenko","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041b\u0430\u0432\u0440\u0438\u043d\u0435\u043d\u043a\u043e","deprecated_text":""},{"doc_type":"numeric","name":"medal_ekins","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u042d\u043a\u0438\u043d\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"medal_brothers_in_arms","deprecated":false,"required":true,"help_text":"\u0411\u0440\u0430\u0442\u044c\u044f \u043f\u043e \u043e\u0440\u0443\u0436\u0438\u044e","deprecated_text":""},{"doc_type":"numeric","name":"medal_crucial_contribution","deprecated":false,"required":true,"help_text":"\u0420\u0435\u0448\u0430\u044e\u0449\u0438\u0439 \u0432\u043a\u043b\u0430\u0434","deprecated_text":""},{"doc_type":"numeric","name":"beasthunter","deprecated":false,"required":true,"help_text":"\u0417\u0432\u0435\u0440\u043e\u0431\u043e\u0439","deprecated_text":""},{"doc_type":"numeric","name":"sinai","deprecated":false,"required":true,"help_text":"\u041b\u0435\u0432 \u0421\u0438\u043d\u0430\u044f","deprecated_text":""},{"doc_type":"numeric","name":"title_sniper","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0440\u0435\u043b\u043e\u043a","deprecated_text":""},{"doc_type":"numeric","name":"raider","deprecated":false,"required":true,"help_text":"\u0420\u0435\u0439\u0434\u0435\u0440","deprecated_text":""},{"doc_type":"numeric","name":"mousebane","deprecated":false,"required":true,"help_text":"\u0413\u0440\u043e\u0437\u0430 \u043c\u044b\u0448\u0435\u0439","deprecated_text":""},{"doc_type":"numeric","name":"diehard","deprecated":false,"required":true,"help_text":"\u0416\u0438\u0432\u0443\u0447\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"kamikaze","deprecated":false,"required":true,"help_text":"\u041a\u0430\u043c\u0438\u043a\u0430\u0434\u0437\u0435","deprecated_text":""},{"doc_type":"numeric","name":"hand_of_death","deprecated":false,"required":true,"help_text":"\u041a\u043e\u0441\u0430 \u0441\u043c\u0435\u0440\u0442\u0438","deprecated_text":""},{"doc_type":"numeric","name":"armor_piercer","deprecated":false,"required":true,"help_text":"\u0411\u0440\u043e\u043d\u0435\u0431\u043e\u0439\u0449\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"invincible","deprecated":false,"required":true,"help_text":"\u041d\u0435\u0443\u044f\u0437\u0432\u0438\u043c\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"bombardier","deprecated":false,"required":true,"help_text":"\u0411\u043e\u043c\u0431\u0430\u0440\u0434\u0438\u0440","deprecated_text":""},{"doc_type":"numeric","name":"patton_valley","deprecated":false,"required":true,"help_text":"\u0414\u043e\u043b\u0438\u043d\u0430 \u041f\u0430\u0442\u0442\u043e\u043d\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"lumberjack","deprecated":false,"required":true,"help_text":"\u0414\u0440\u043e\u0432\u043e\u0441\u0435\u043a","deprecated_text":""},{"doc_type":"numeric","name":"max_sniper_series","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0435\u0440\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u0421\u0442\u0440\u0435\u043b\u043e\u043a","deprecated_text":""},{"doc_type":"numeric","name":"max_invincible_series","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0435\u0440\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u041d\u0435\u0443\u044f\u0437\u0432\u0438\u043c\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"max_diehard_series","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0435\u0440\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u0416\u0438\u0432\u0443\u0447\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"max_killing_series","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0435\u0440\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u041a\u043e\u0441\u0430 \u0441\u043c\u0435\u0440\u0442\u0438","deprecated_text":""},{"doc_type":"numeric","name":"max_piercing_series","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u0435\u0440\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f \u0411\u0440\u043e\u043d\u0435\u0431\u043e\u0439\u0449\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"lucky_devil","deprecated":false,"required":true,"help_text":"\u0421\u0447\u0430\u0441\u0442\u043b\u0438\u0432\u0447\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"huntsman","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0434\u0430\u043b\u044c \u041d\u0430\u0439\u0434\u0438\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"sturdy","deprecated":false,"required":true,"help_text":"\u0421\u043f\u0430\u0440\u0442\u0430\u043d\u0435\u0446","deprecated_text":""},{"doc_type":"numeric","name":"iron_man","deprecated":false,"required":true,"help_text":"\u041d\u0435\u0432\u043e\u0437\u043c\u0443\u0442\u0438\u043c\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_ussr","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u0421\u0421\u0421\u0420","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_germany","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u0413\u0435\u0440\u043c\u0430\u043d\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_usa","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u0421\u0428\u0410","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_france","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u0424\u0440\u0430\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_uk","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u0411\u0440\u0438\u0442\u0430\u043d\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_china","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u041a\u0438\u0442\u0430\u044f","deprecated_text":""},{"doc_type":"numeric","name":"tank_expert_japan","deprecated":false,"required":true,"help_text":"\u042d\u043a\u0441\u043f\u0435\u0440\u0442 \u042f\u043f\u043e\u043d\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_ussr","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u0421\u0421\u0421\u0420","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_germany","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u0413\u0435\u0440\u043c\u0430\u043d\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_usa","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u0421\u0428\u0410","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_france","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u0424\u0440\u0430\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_uk","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u0411\u0440\u0438\u0442\u0430\u043d\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_china","deprecated":false,"required":true,"help_text":"\u041c\u0435\u0445\u0430\u043d\u0438\u043a-\u0438\u043d\u0436\u0435\u043d\u0435\u0440 \u041a\u0438\u0442\u0430\u044f","deprecated_text":""},{"doc_type":"numeric","name":"mechanic_engineer_japan","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0436\u0435\u043d\u0435\u0440-\u043c\u0435\u0445\u0430\u043d\u0438\u043a \u042f\u043f\u043e\u043d\u0438\u044f","deprecated_text":""}],"deprecated_text":"\u0418\u0441\u043f\u043e\u043b\u044c\u0437\u0443\u0439\u0442\u0435 \u043c\u0435\u0442\u043e\u0434 account\/achievements","name":"achievements","deprecated":true},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0441\u043e\u0437\u0434\u0430\u043d\u0438\u044f \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"timestamp","name":"updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u0438 \u043e\u0431 \u0438\u0433\u0440\u043e\u043a\u0435","deprecated_text":""},{"doc_type":"timestamp","name":"logout_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f \u043f\u043e\u0441\u043b\u0435\u0434\u043d\u0435\u0439 \u0438\u0433\u0440\u043e\u0432\u043e\u0439 \u0441\u0435\u0441\u0441\u0438\u0438","deprecated_text":""},{"doc_type":"timestamp","name":"last_battle_time","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043f\u043e\u0441\u043b\u0435\u0434\u043d\u0435\u0433\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0435\u043d\u043d\u043e\u0433\u043e \u0431\u043e\u044f","deprecated_text":""},{"doc_type":"string","name":"nickname","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"global_rating","deprecated":false,"required":true,"help_text":"\u041f\u043e\u043a\u0430\u0437\u0430\u0442\u0435\u043b\u044c \u043b\u0438\u0447\u043d\u043e\u0433\u043e \u0440\u0435\u0439\u0442\u0438\u043d\u0433\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"help_text":"\u041f\u0440\u0438\u0432\u0430\u0442\u043d\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435 \u0438\u0433\u0440\u043e\u043a\u0430","fields":[{"doc_type":"string","name":"account_type","deprecated":true,"required":true,"help_text":"\u0422\u0438\u043f \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"account_type_i18n","deprecated":true,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u043f\u043e\u043b\u044f account_type","deprecated_text":""},{"doc_type":"numeric","name":"gold","deprecated":false,"required":true,"help_text":"\u0417\u043e\u043b\u043e\u0442\u043e","deprecated_text":""},{"doc_type":"numeric","name":"free_xp","deprecated":false,"required":true,"help_text":"\u0421\u0432\u043e\u0431\u043e\u0434\u043d\u044b\u0439 \u043e\u043f\u044b\u0442","deprecated_text":""},{"doc_type":"numeric","name":"credits","deprecated":false,"required":true,"help_text":"\u041a\u0440\u0435\u0434\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"timestamp","name":"premium_expires_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0438\u0441\u0442\u0435\u0447\u0435\u043d\u0438\u044f \u043f\u0440\u0435\u043c\u0438\u0443\u043c \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430","deprecated_text":""},{"doc_type":"boolean","name":"is_premium","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0434\u0438\u043a\u0430\u0442\u043e\u0440 \u043f\u0440\u0435\u043c\u0438\u0443\u043c \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430","deprecated_text":""},{"doc_type":"string","name":"ban_info","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u0438\u0433\u0440\u043e\u0432\u043e\u043c \u0431\u0430\u043d\u0435","deprecated_text":""},{"doc_type":"timestamp","name":"ban_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f \u0438\u0433\u0440\u043e\u0432\u043e\u0433\u043e \u0431\u0430\u043d\u0430","deprecated_text":""},{"help_text":"\u041e\u0433\u0440\u0430\u043d\u0438\u0447\u0435\u043d\u0438\u044f \u0438\u0433\u0440\u043e\u043a\u0430","fields":[{"doc_type":"timestamp","name":"chat_ban_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f \u0431\u0430\u043d\u0430 \u0447\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"timestamp","name":"clan_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f \u043e\u0433\u0440\u0430\u043d\u0438\u0447\u0435\u043d\u0438\u044f \u043f\u043e \u043a\u043b\u0430\u043d\u0443","deprecated_text":""}],"deprecated_text":"","name":"restrictions","deprecated":false},{"doc_type":"list of integers","name":"friends","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u043e\u0432 \u0434\u0440\u0443\u0437\u0435\u0439 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"boolean","name":"is_bound_to_phone","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0432\u044f\u0437\u0430\u043d \u043b\u0438 \u0430\u043a\u043a\u0430\u0443\u043d\u0442 \u043a \u043d\u043e\u043c\u0435\u0440\u0443 \u043c\u043e\u0431\u0438\u043b\u044c\u043d\u043e\u0433\u043e \u0442\u0435\u043b\u0435\u0444\u043e\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"private","deprecated":false},{"doc_type":"string","name":"client_language","deprecated":false,"required":true,"help_text":"\u042f\u0437\u044b\u043a, \u0432\u044b\u0431\u0440\u0430\u043d\u043d\u044b\u0439 \u0432 \u043a\u043b\u0438\u0435\u043d\u0442\u0435 \u0438\u0433\u0440\u044b","deprecated_text":""},{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function info ($input = array()) {
$this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string')))
return NULL;
$output = $this->send('account/info', $input, array('http', 'https'));
return $output;
}

/**
 * Танки игрока
 * Возвращает детальную информацию о танках игрока.
 * @category Аккаунт
 * @link account/tanks
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param numeric|list $input['tank_id'] Идентификатор танка игрока
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"tank_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"mark_of_mastery","deprecated":false,"required":true,"help_text":"\n\u0417\u043d\u0430\u043a \u043a\u043b\u0430\u0441\u0441\u043d\u043e\u0441\u0442\u0438:\n\n * 0 - \u041e\u0442\u0441\u0443\u0442\u0441\u0442\u0432\u0443\u0435\u0442\n * 1 - \u0422\u0440\u0435\u0442\u044c\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 2 - \u0412\u0442\u043e\u0440\u0430\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 3 - \u041f\u0435\u0440\u0432\u0430\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 4 - \u041c\u0430\u0441\u0442\u0435\u0440\n","deprecated_text":""},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u0442\u0430\u043d\u043a\u0430","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""}],"deprecated_text":"","name":"statistics","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tanks ($input = array()) {
$this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list')))
return NULL;
$output = $this->send('account/tanks', $input, array('http', 'https'));
return $output;
}

/**
 * Достижения игрока
 * Возвращает достижение по игрокам.
 * 
 * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях](/developers/api_reference/wot/encyclopedia/achievements)):
 * 
 * * от 1 до 4 для знака классности и этапных достижений (type: "class")
 * * максимальное значение серийных достижений (type: "series")
 * * количество заработанных наград из секций: Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и тп. (type: "repeatable, single, custom")
 * 
 * @category Аккаунт
 * @link account/achievements
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"associative array","name":"achievements","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f\u0445","deprecated_text":""},{"doc_type":"associative array","name":"max_series","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0445 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u044f\u0445 \u0441\u0435\u0440\u0438\u0439\u043d\u044b\u0445 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u0439","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function achievements ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('account/achievements', $input, array('http', 'https'));
return $output;
}

}

/**
 * Мировая война 
 */
class wgapi_wot_globalwar extends WgApiCore {

/**
 * Список кланов на ГК
 * Возвращает список кланов на карте.
 * @category Мировая война
 * @link globalwar/clans
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
 * @param numeric $input['page_no'] Номер страницы выдачи
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"help_text":"\u0421\u043f\u0438\u0441\u043e\u043a \u0437\u0430\u0445\u0432\u0430\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0439","fields":[{"doc_type":"string","name":"province_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"province_i18n","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"occupancy_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u0432\u043b\u0430\u0434\u0435\u043d\u0438\u044f \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0435\u0439 \u0432 \u0434\u043d\u044f\u0445","deprecated_text":""}],"deprecated_text":"","name":"provinces","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function clans ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'limit' => 'numeric', 'page_no' => 'numeric')))
return NULL;
$output = $this->send('globalwar/clans', $input, array('http', 'https'));
return $output;
}

/**
 * Информация по актуальному количеству очков славы игрока на ГК
 * Возвращает достижения игрока на карте.
 * @category Мировая война
 * @link globalwar/famepoints
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param numeric|list $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"fame_points","deprecated":false,"required":true,"help_text":"\u041e\u0447\u043a\u0438 \u0441\u043b\u0430\u0432\u044b","deprecated_text":""},{"doc_type":"numeric","name":"position","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f \u0432 \u0430\u043b\u043b\u0435\u0435 \u0441\u043b\u0430\u0432\u044b","deprecated_text":""},{"doc_type":"timestamp","name":"updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u0438 \u043e \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f\u0445 \u0438\u0433\u0440\u043e\u043a\u0430 \u043d\u0430 \u043a\u0430\u0440\u0442\u0435","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function famepoints ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('globalwar/famepoints', $input, array('http', 'https'));
return $output;
}

/**
 * Список доступных карт на ГК
 * Возвращает список карт на Глобальной карте.
 * @category Мировая война
 * @link globalwar/maps
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"map_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u0430\u0440\u0442\u044b","deprecated_text":""},{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u043a\u0430\u0440\u0442\u044b: event \u0438\u043b\u0438 normal","deprecated_text":""},{"doc_type":"string","name":"state","deprecated":false,"required":true,"help_text":"\u0421\u043e\u0441\u0442\u043e\u044f\u043d\u0438\u0435 \u043a\u0430\u0440\u0442\u044b: freezed, active \u0438\u043b\u0438 unavailable","deprecated_text":""},{"doc_type":"string","name":"map_url","deprecated":false,"required":true,"help_text":"URL \u043d\u0430 \u043a\u0430\u0440\u0442\u0443 \u043d\u0430 \u043f\u043e\u0440\u0442\u0430\u043b\u0435","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function maps ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('globalwar/maps', $input, array('http', 'https'));
return $output;
}

/**
 * Список провинций определенной карты на ГК
 * Возвращает список провинций на карте.
 * @category Мировая война
 * @link globalwar/provinces
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string|list $input['province_id'] Идентификатор провинции
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"province_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"province_i18n","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"status","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0430\u0442\u0443\u0441 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438: normal, start, gold, mutiny, delayed_mutiny","deprecated_text":""},{"doc_type":"list of strings","name":"neighbors","deprecated":false,"required":true,"help_text":"\u0421\u043f\u0438\u0441\u043e\u043a \u0441\u043e\u0441\u0435\u0434\u043d\u0438\u0445 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0439","deprecated_text":""},{"help_text":"\u0421\u043f\u0438\u0441\u043e\u043a \u0440\u0435\u0433\u0438\u043e\u043d\u043e\u0432","fields":[{"doc_type":"string","name":"region_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0440\u0435\u0433\u0438\u043e\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"region_i18n","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0440\u0435\u0433\u0438\u043e\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"regions","deprecated":false},{"doc_type":"numeric","name":"vehicle_max_level","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u043e \u0434\u043e\u043f\u0443\u0441\u0442\u0438\u043c\u044b\u0439 \u0443\u0440\u043e\u0432\u0435\u043d\u044c \u0442\u0435\u0445\u043d\u0438\u043a\u0438","deprecated_text":""},{"doc_type":"numeric","name":"revenue","deprecated":false,"required":true,"help_text":"\u0414\u043d\u0435\u0432\u043d\u043e\u0439 \u0434\u043e\u0445\u043e\u0434","deprecated_text":""},{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u041a\u043b\u0430\u043d-\u0432\u043b\u0430\u0434\u0435\u043b\u0435\u0446","deprecated_text":""},{"doc_type":"numeric","name":"prime_time","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0430\u0439\u043c-\u0442\u0430\u0439\u043c","deprecated_text":""},{"doc_type":"string","name":"primary_region","deprecated":false,"required":true,"help_text":"\u041e\u0441\u043d\u043e\u0432\u043d\u043e\u0439 \u0440\u0435\u0433\u0438\u043e\u043d","deprecated_text":""},{"doc_type":"string","name":"arena","deprecated":false,"required":true,"help_text":"\u0410\u0440\u0435\u043d\u0430 \u0441\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"timestamp","name":"updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u0438 \u043e \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u044f\u0445 \u043d\u0430 \u043a\u0430\u0440\u0442\u0435","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function provinces ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'province_id' => 'string, list')))
return NULL;
$output = $this->send('globalwar/provinces', $input, array('http', 'https'));
return $output;
}

/**
 * Список топ кланов на ГК
 * Возвращает список топ кланов по одному из критериев - количество боев, количество провинций, количество побед.
 * @category Мировая война
 * @link globalwar/top
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['order_by'] Вид сортировки.. Допустимые значения: 
 * 
 * * "wins_count" - Количество побед 
 * * "combats_count" - Количество боев 
 * * "provinces_count" - Количество провинций 
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"motto","deprecated":false,"required":true,"help_text":"\u0414\u0435\u0432\u0438\u0437 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"abbreviation","deprecated":false,"required":true,"help_text":"\u0422\u044d\u0433 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"color","deprecated":false,"required":true,"help_text":"\u0426\u0432\u0435\u0442 \u043a\u043b\u0430\u043d\u0430 \u0432 HEX-\u0444\u043e\u0440\u043c\u0430\u0442\u0435 \u201c#RRGGBB\u201d","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043c\u0431\u043b\u0435\u043c\u0430\u0445 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"string","name":"large","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"small","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 24x24","deprecated_text":""},{"doc_type":"string","name":"bw_tank","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u043d\u0430 \u0442\u0430\u043d\u043a\u0435 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 64x64","deprecated_text":""},{"doc_type":"string","name":"medium","deprecated":false,"required":true,"help_text":"URL \u044d\u043c\u0431\u043b\u0435\u043c\u044b \u043a\u043b\u0430\u043d\u0430 \u0440\u0430\u0437\u043c\u0435\u0440\u043e\u043c 32x32","deprecated_text":""}],"deprecated_text":"","name":"emblems","deprecated":false},{"doc_type":"numeric","name":"owner_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"members_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0441\u043e\u0437\u0434\u0430\u043d\u0438\u044f \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"owner_name","deprecated":false,"required":true,"help_text":"\u0418\u043c\u044f \u0432\u043b\u0430\u0434\u0435\u043b\u044c\u0446\u0430 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"wins_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"combats_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0431\u043e\u0435\u0432","deprecated_text":""},{"doc_type":"numeric","name":"provinces_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0439","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function top ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'order_by' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('globalwar/top', $input, array('http', 'https'));
return $output;
}

/**
 * Информация о турнирах на ГК
 * Возвращает список турниров на карте.
 * @category Мировая война
 * @link globalwar/tournaments
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['province_id'] Идентификатор провинции
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"province_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"province_i18n","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"province_status","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0430\u0442\u0443\u0441 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"string","name":"status","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0430\u0442\u0443\u0441 \u0442\u0443\u0440\u043d\u0438\u0440\u0430","deprecated_text":""},{"doc_type":"timestamp","name":"start_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u0441\u0442\u0430\u0440\u0442\u0430","deprecated_text":""},{"doc_type":"timestamp","name":"finish_time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"max_competitors","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u043e\u0435 \u043a\u043e\u043b-\u0432\u043e \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"list of integers","name":"competitors","deprecated":false,"required":true,"help_text":"\u0421\u043f\u0438\u0441\u043e\u043a \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"winner","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0431\u0435\u0434\u0438\u0442\u0435\u043b\u044c","deprecated_text":""},{"help_text":"\u0414\u0435\u0440\u0435\u0432\u043e \u0442\u0443\u0440\u043d\u0438\u0440\u0430","fields":[{"doc_type":"boolean","name":"current","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0443\u0449\u0438\u0439 \u0440\u0430\u0443\u043d\u0434","deprecated_text":""},{"doc_type":"numeric","name":"round_idx","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c \u0440\u0430\u0443\u043d\u0434\u0430: 0 - \u0441\u0443\u043f\u0435\u0440\u0444\u0438\u043d\u0430\u043b, 1 - \u0444\u0438\u043d\u0430\u043b, 2 - \u043f\u043e\u043b\u0443\u0444\u0438\u043d\u0430\u043b \u0438 \u0442.\u0434.","deprecated_text":""},{"help_text":"\u0411\u043e\u0438 \u0440\u0430\u0443\u043d\u0434\u0430","fields":[{"doc_type":"numeric","name":"clan1","deprecated":false,"required":true,"help_text":"\u041f\u0435\u0440\u0432\u044b\u0439 \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a","deprecated_text":""},{"doc_type":"numeric","name":"clan2","deprecated":false,"required":true,"help_text":"\u0412\u0442\u043e\u0440\u043e\u0439 \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a","deprecated_text":""},{"doc_type":"string","name":"fail_status","deprecated":false,"required":true,"help_text":"\u0421\u0442\u0430\u0442\u0443\u0441 \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"timestamp","name":"start_at","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u0441\u0442\u0430\u0440\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"winner","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0431\u0435\u0434\u0438\u0442\u0435\u043b\u044c","deprecated_text":""}],"deprecated_text":"","name":"battles","deprecated":false}],"deprecated_text":"","name":"tournament_tree","deprecated":false},{"doc_type":"timestamp","name":"updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u0438 \u043e \u0431\u043e\u044f\u0445 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tournaments ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'province_id' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('globalwar/tournaments', $input, array('http', 'https'));
return $output;
}

/**
 * История очков славы игрока
 * История начислений очков победы для игрока
 * @category Мировая война
 * @link globalwar/famepointshistory
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param timestamp/date $input['since'] Начало периода
 * @param timestamp/date $input['until'] Конец периода
 * @param numeric $input['page_no'] Номер страницы выдачи
 * @param numeric $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"fame_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u043e\u0435 \u0431\u044b\u043b\u0430 \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0435\u043d\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u044f (\u043c\u043e\u0436\u0435\u0442 \u0431\u044b\u0442\u044c \u043a\u0430\u043a \u043f\u043e\u043b\u043e\u0436\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u043c \u0442\u0430\u043a \u0438 \u043e\u0442\u0440\u0438\u0446\u0430\u0442\u0435\u043b\u044c\u043d\u044b\u043c)","deprecated_text":""},{"doc_type":"string","name":"factor_message","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0441\u0442\u043e\u0432\u043e\u0435 \u043e\u043f\u0438\u0441\u0430\u043d\u0438\u0435 \u0444\u0430\u043a\u0442\u043e\u0440\u0430, \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043f\u0440\u0438\u0432\u0435\u043b \u043a \u0438\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u044e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function famepointshistory ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'page_no' => 'numeric', 'limit' => 'numeric')))
return NULL;
$output = $this->send('globalwar/famepointshistory', $input, array('https'));
return $output;
}

/**
 * Аллея славы
 * Топ игроков по очкам славы
 * @category Мировая война
 * @link globalwar/alleyoffame
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric $input['page_no'] Номер страницы выдачи
 * @param numeric $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"clan_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"numeric","name":"fame_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0441\u043b\u0430\u0432\u044b \u043f\u043e\u0431\u0435\u0434 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"rank","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0437\u0438\u0446\u0438\u044f \u0438\u0433\u0440\u043e\u043a\u0430 \u043d\u0430 \u0410\u043b\u0435\u0435 \u0441\u043b\u0430\u0432\u044b","deprecated_text":""},{"doc_type":"numeric","name":"rank_delta","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0433\u0440\u0435\u0441\u0441 \u0438\u0433\u0440\u043e\u043a\u0430 \u043d\u0430 \u0410\u043b\u0435\u0435 \u0441\u043b\u0430\u0432\u044b","deprecated_text":""},{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function alleyoffame ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'page_no' => 'numeric', 'limit' => 'numeric')))
return NULL;
$output = $this->send('globalwar/alleyoffame', $input, array('http', 'https'));
return $output;
}

/**
 * Список боёв клана
 * Возвращает список боев клана.
 * @category Мировая война
 * @link globalwar/battles
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param numeric|list $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u0431\u043e\u044f\n\n * **for_province** \u2014 \u0431\u043e\u0439 \u0437\u0430 \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u044e\n * **meeting_engagement** \u2014 \u0432\u0441\u0442\u0440\u0435\u0447\u043d\u044b\u0439 \u0431\u043e\u0439\n * **landing** \u2014 \u0442\u0443\u0440\u043d\u0438\u0440 \u0437\u0430 \u0432\u044b\u0441\u0430\u0434\u043a\u0443\n","deprecated_text":""},{"doc_type":"boolean","name":"started","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a \u043d\u0430\u0447\u0430\u043b\u0430 \u0431\u043e\u044f","deprecated_text":""},{"doc_type":"timestamp","name":"time","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043d\u0430\u0447\u0430\u043b\u0430 \u0431\u043e\u044f","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0430\u0440\u0435\u043d\u0435","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0430\u0440\u0435\u043d\u044b","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u043f\u043e\u043b\u044f name","deprecated_text":""}],"deprecated_text":"","name":"arenas","deprecated":false},{"doc_type":"list of strings","name":"provinces","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u043f\u0440\u043e\u0432\u0438\u043d\u0446\u0438\u0439","deprecated_text":""},{"help_text":"\u041f\u0440\u0438\u0432\u0430\u0442\u043d\u044b\u0435 \u0434\u0430\u043d\u043d\u044b\u0435 \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"numeric","name":"chips","deprecated":false,"required":true,"help_text":"\u0427\u0438\u0441\u043b\u043e \u0444\u0438\u0448\u0435\u043a","deprecated_text":""}],"deprecated_text":"","name":"private","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function battles ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string')))
return NULL;
$output = $this->send('globalwar/battles', $input, array('http', 'https'));
return $output;
}

/**
 * История начисления очков победы клана
 * История начислений очков победы для клана
 * @category Мировая война
 * @link globalwar/victorypointshistory
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['map_id'] Идентификатор карты
 * @param numeric $input['clan_id'] Идентификатор клана
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param timestamp/date $input['since'] Начало периода
 * @param timestamp/date $input['until'] Конец периода
 * @param numeric $input['offset'] Сдвиг относительно первого результата
 * @param numeric $input['limit'] Кол-во результатов (от 20 до 100)
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043a\u043e\u043b-\u0432\u0435 \u0440\u0435\u0437\u0443\u043b\u044c\u0442\u0430\u0442\u043e\u0432","fields":[{"doc_type":"numeric","name":"total_count","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0440\u0435\u0437\u0443\u043b\u044c\u0442\u0430\u0442\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"page","deprecated":false},{"help_text":"\u0418\u0441\u0442\u043e\u0440\u0438\u044f \u043d\u0430\u0447\u0438\u0441\u043b\u0435\u043d\u0438\u044f \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","fields":[{"doc_type":"numeric","name":"turn_id","deprecated":true,"required":true,"help_text":"ID \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"timestamp","name":"created_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"current_clan_victory_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430 \u043f\u043e\u0441\u043b\u0435 \u0441\u043e\u0432\u0435\u0440\u0448\u0435\u043d\u0438\u044f \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u043e\u0435 \u0431\u044b\u043b\u0430 \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0435\u043d\u0430 \u0442\u0440\u0430\u043d\u0437\u0430\u043a\u0446\u0438\u044f (\u043c\u043e\u0436\u0435\u0442 \u0431\u044b\u0442\u044c \u043a\u0430\u043a \u043f\u043e\u043b\u043e\u0436\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u043c \u0442\u0430\u043a \u0438 \u043e\u0442\u0440\u0438\u0446\u0430\u0442\u0435\u043b\u044c\u043d\u044b\u043c)","deprecated_text":""},{"doc_type":"string","name":"factor","deprecated":true,"required":true,"help_text":"\u0422\u0435\u0445\u043d\u0438\u0447\u0435\u0441\u043a\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435 \u0444\u0430\u043a\u0442\u043e\u0440\u0430, \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043f\u0440\u0438\u0432\u0435\u043b \u043a \u0438\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u044e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","deprecated_text":""},{"doc_type":"string","name":"factor_message","deprecated":false,"required":true,"help_text":"\u0422\u0435\u043a\u0441\u0442\u043e\u0432\u043e\u0435 \u043e\u043f\u0438\u0441\u0430\u043d\u0438\u0435 \u0444\u0430\u043a\u0442\u043e\u0440\u0430, \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043f\u0440\u0438\u0432\u0435\u043b \u043a \u0438\u0437\u043c\u0435\u043d\u0435\u043d\u0438\u044e \u043e\u0447\u043a\u043e\u0432 \u043f\u043e\u0431\u0435\u0434\u044b \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"victorypointshistory","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function victorypointshistory ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric')))
return NULL;
$output = $this->send('globalwar/victorypointshistory', $input, array('http', 'https'));
return $output;
}

}

/**
 * Аутентификация 
 */
class wgapi_wot_auth extends WgApiCore {

/**
 * Вход по OpenID
 * Осуществляет аутентификацию пользователя с использованием WarGaming.Net ID (OpenID).
 * 
 * На странице аутентификации пользователю необходимо ввести свой логин и пароль.
 * 
 * Информация о статусе авторизации будет отправлена по адресу указаному в параметре **redirect_uri**.
 * 
 * Параметры к **redirect_uri** при успешной аутентификации:
 * 
 * * **status** - ok, аутентификация пройдена
 * * **access_token** - Ключ доступа, передается во все методы требующие аутентификацию.
 * * **expires_at** - Время окончания срока действия **access_token**.
 * * **account_id** - Идентификатор залогиненного аккаунта.
 * * **nickname** - Имя залогиненного аккаунта.
 * 
 * Параметры к **redirect_uri** если произошла ошибка:
 * 
 * * **status** - error, ошибка аутентификации.
 * * **code** - Код ошибки.
 * * **message** - Сообщение с информацией об ошибке.
 * @category Аутентификация
 * @link auth/login
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param numeric $input['expires_at'] Конечный период в UTC до которого должен работать **access_token**. Можно также указать дельту в секундах, сколько должен действовать **access_token**.
 * 
 * Конечный период и дельта не должны превышать двух недель от текущего времени.
 * @param string $input['redirect_uri'] 
 * URL на который будет переброшен пользователь после того как он пройдет аутентификацию.
 * 
 * По умолчанию: [{API_HOST}/blank/](https://{API_HOST}/blank/)
 * 
 * @param string $input['display'] Внешний вид формы для мобильных. Допустимые значения: page, popup
 * @param numeric $input['nofollow'] При передаче параметра nofollow=1 URL будет возвращен в теле ответа вместо редиректа
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"location","deprecated":false,"required":true,"help_text":"URL \u043d\u0430 \u043a\u043e\u0442\u043e\u0440\u044b\u0439 \u043d\u0435\u043e\u0431\u0445\u043e\u0434\u0438\u043c\u043e \u043f\u0435\u0440\u0435\u043d\u0430\u043f\u0440\u0430\u0432\u0438\u0442\u044c \u043a\u043b\u0438\u0435\u043d\u0442\u0430 \u0434\u043b\u044f \u0430\u0443\u0442\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0446\u0438\u0438.\n\u0412\u043e\u0437\u0432\u0440\u0430\u0449\u0430\u0435\u0442\u0441\u044f \u0442\u043e\u043b\u044c\u043a\u043e \u0435\u0441\u043b\u0438 \u043f\u0435\u0440\u0435\u0434\u0430\u043d \u043f\u0430\u0440\u0430\u043c\u0435\u0442\u0440 nofollow=1.","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function login ($input = array()) {
$this->erorr->add(array(array(401, "AUTH_CANCEL", "Пользователь отменил авторизацию для приложения"), array(403, "AUTH_EXPIRED", "Превышено время ожидания подтверждения авторизации пользователем"), array(410, "AUTH_ERROR", "Ошибка аутентификации")));
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'expires_at' => 'numeric', 'redirect_uri' => 'string', 'display' => 'string', 'nofollow' => 'numeric')))
return NULL;
$output = $this->send('auth/login', $input, array('https'));
return $output;
}

/**
 * Продление access_token
 * Выдает новый **access_token** на основе действующего.
 * 
 * Используется для тех случаев когда пользователь все еще пользуется приложением, а срок действия его **access_token** уже подходит к концу.
 * @category Аутентификация
 * @link auth/prolongate
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param numeric $input['expires_at'] Конечный период в UTC до которого должен работать **access_token**. Можно также указать дельту в секундах, сколько должен действовать **access_token**.
 * 
 * Конечный период и дельта не должны превышать двух недель от текущего времени.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"access_token","deprecated":false,"required":true,"help_text":"\u041a\u043b\u044e\u0447 \u0434\u043e\u0441\u0442\u0443\u043f\u0430, \u043f\u0435\u0440\u0435\u0434\u0430\u0435\u0442\u0441\u044f \u0432\u043e \u0432\u0441\u0435 \u043c\u0435\u0442\u043e\u0434\u044b \u0442\u0440\u0435\u0431\u0443\u044e\u0449\u0438\u0435 \u0430\u0443\u0442\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0446\u0438\u044e","deprecated_text":""},{"doc_type":"timestamp","name":"expires_at","deprecated":false,"required":true,"help_text":"\u0412\u0440\u0435\u043c\u044f \u043e\u043a\u043e\u043d\u0447\u0430\u043d\u0438\u044f \u0441\u0440\u043e\u043a\u0430 \u0434\u0435\u0439\u0441\u0442\u0432\u0438\u044f access_token","deprecated_text":""},{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function prolongate ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'expires_at' => 'numeric')))
return NULL;
$output = $this->send('auth/prolongate', $input, array('https'));
return $output;
}

/**
 * Выход (забыть аутентификацию)
 * Удаляет данные авторизации пользователя на доступ к его персональным данным.
 * 
 * После вызова данного метода перестанет действовать **access_token**.
 * @category Аутентификация
 * @link auth/logout
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @return array
 * {"output_form_info":null}
 */
function logout ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string')))
return NULL;
$output = $this->send('auth/logout', $input, array('https'));
return $output;
}

}

/**
 * Энциклопедия 
 */
class wgapi_wot_encyclopedia extends WgApiCore {

/**
 * Список танков
 * Возвращает список всех танков из танкопедии.
 * @category Энциклопедия
 * @link encyclopedia/tanks
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"tank_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"boolean","name":"is_premium","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u0442\u0430\u043d\u043a - \u043f\u0440\u0435\u043c\u0438\u0443\u043c\u043d\u044b\u0439","deprecated_text":""},{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"type_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u044b\u0439 \u0442\u0438\u043f \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"short_name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u043e\u0435 \u043a\u043e\u0440\u043e\u0442\u043a\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tanks ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tanks', $input, array('http', 'https'));
return $output;
}

/**
 * Информация о технике
 * Возвращает информацию о танке из танкопедии.
 * @category Энциклопедия
 * @link encyclopedia/tankinfo
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric|list $input['tank_id'] Идентификатор танка
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":false,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"tank_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"localized_name","deprecated":true,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"short_name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u043e\u0435 \u043a\u043e\u0440\u043e\u0442\u043a\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"image","deprecated":false,"required":true,"help_text":"URL \u043d\u0430 \u0438\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u044f \u0442\u0430\u043d\u043a\u0430 160x100px","deprecated_text":""},{"doc_type":"string","name":"image_small","deprecated":false,"required":true,"help_text":"URL \u043d\u0430 \u0438\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u044f \u0442\u0430\u043d\u043a\u0430 124x31px","deprecated_text":""},{"doc_type":"string","name":"contour_image","deprecated":false,"required":true,"help_text":"URL \u043a \u0438\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u044e-\u043a\u043e\u043d\u0442\u0443\u0440\u0443 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"max_health","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0447\u043d\u043e\u0441\u0442\u044c","deprecated_text":""},{"doc_type":"float","name":"limit_weight","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0435\u0434\u0435\u043b\u044c\u043d\u044b\u0439 \u0432\u0435\u0441","deprecated_text":""},{"doc_type":"boolean","name":"is_gift","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u0442\u0430\u043d\u043a - \u043f\u043e\u0434\u0430\u0440\u043e\u0447\u043d\u044b\u0439","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u044d\u043a\u0438\u043f\u0430\u0436\u0435","fields":[{"doc_type":"string","name":"role","deprecated":false,"required":true,"help_text":"\u0420\u043e\u043b\u044c \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""},{"doc_type":"string","name":"role_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u0440\u043e\u043b\u0438 \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""},{"doc_type":"list of strings","name":"additional_roles","deprecated":true,"required":false,"help_text":"\u0414\u043e\u043f\u043e\u043b\u043d\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u0435 \u0440\u043e\u043b\u0438 \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""},{"help_text":"\u0414\u043e\u043f\u043e\u043b\u043d\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u0435 \u0440\u043e\u043b\u0438 \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","fields":[{"doc_type":"string","name":"role","deprecated":false,"required":true,"help_text":"\u0420\u043e\u043b\u044c \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""},{"doc_type":"string","name":"role_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u0440\u043e\u043b\u0438 \u0447\u043b\u0435\u043d\u0430 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""}],"deprecated_text":"","name":"additional_roles_i18n","deprecated":false}],"deprecated_text":"","name":"crew","deprecated":false},{"doc_type":"numeric","name":"engine_power","deprecated":false,"required":true,"help_text":"\u041c\u043e\u0449\u043d\u043e\u0441\u0442\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u0434\u0432\u0438\u0433\u0430\u0442\u0435\u043b\u044f","deprecated_text":""},{"doc_type":"float","name":"speed_limit","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u0441\u043a\u043e\u0440\u043e\u0441\u0442\u044c","deprecated_text":""},{"doc_type":"numeric","name":"chassis_rotation_speed","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u044c \u043f\u043e\u0432\u043e\u0440\u043e\u0442\u0430 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u0448\u0430\u0441\u0441\u0438","deprecated_text":""},{"doc_type":"numeric","name":"turret_rotation_speed","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u044c \u0432\u0440\u0430\u0449\u0435\u043d\u0438\u044f \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0431\u0430\u0448\u043d\u0438","deprecated_text":""},{"doc_type":"numeric","name":"vehicle_armor_forehead","deprecated":false,"required":true,"help_text":"\u041b\u043e\u0431\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u043a\u043e\u0440\u043f\u0443\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"vehicle_armor_board","deprecated":false,"required":true,"help_text":"\u0411\u043e\u0440\u0442\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u043a\u043e\u0440\u043f\u0443\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"vehicle_armor_fedd","deprecated":false,"required":true,"help_text":"\u041a\u043e\u0440\u043c\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u043a\u043e\u0440\u043f\u0443\u0441\u0430","deprecated_text":""},{"doc_type":"numeric","name":"turret_armor_forehead","deprecated":false,"required":true,"help_text":"\u041b\u043e\u0431\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0431\u0430\u0448\u043d\u0438","deprecated_text":""},{"doc_type":"numeric","name":"turret_armor_board","deprecated":false,"required":true,"help_text":"\u0411\u043e\u0440\u0442\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0431\u0430\u0448\u043d\u0438","deprecated_text":""},{"doc_type":"numeric","name":"turret_armor_fedd","deprecated":false,"required":true,"help_text":"\u041a\u043e\u0440\u043c\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0431\u0430\u0448\u043d\u0438","deprecated_text":""},{"doc_type":"string","name":"gun_name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"gun_max_ammo","deprecated":false,"required":true,"help_text":"\u0420\u0430\u0437\u043c\u0435\u0440 \u0431\u043e\u0435\u043a\u043e\u043c\u043f\u043b\u0435\u043a\u0442\u0430 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"gun_damage_min","deprecated":false,"required":true,"help_text":"\u041c\u0438\u043d\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"gun_damage_max","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0443\u0440\u043e\u043d \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"gun_piercing_power_min","deprecated":false,"required":true,"help_text":"\u041c\u0438\u043d\u0438\u043c\u0430\u043b\u044c\u043d\u043e\u0435 \u043f\u0440\u043e\u0431\u0438\u0442\u0438\u0435 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"gun_piercing_power_max","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u043e\u0435 \u043f\u0440\u043e\u0431\u0438\u0442\u0438\u0435 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"float","name":"gun_rate","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u0440\u0435\u043b\u044c\u043d\u043e\u0441\u0442\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0433\u043e \u043e\u0440\u0443\u0434\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"circular_vision_radius","deprecated":false,"required":true,"help_text":"\u041e\u0431\u0437\u043e\u0440 \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0431\u0430\u0448\u043d\u0438","deprecated_text":""},{"doc_type":"numeric","name":"radio_distance","deprecated":false,"required":true,"help_text":"\u0414\u0438\u0441\u0442\u0430\u043d\u0446\u0438\u044f \u0441\u0442\u043e\u043a\u043e\u0432\u043e\u0439 \u0440\u0430\u0434\u0438\u043e\u0441\u0442\u0430\u043d\u0446\u0438\u0438","deprecated_text":""},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0441\u0442\u0430\u043d\u0430\u0432\u043b\u0438\u0432\u0430\u0435\u043c\u044b\u0445 \u0431\u0430\u0448\u043d\u044f\u0445","fields":[{"doc_type":"boolean","name":"is_default","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u043c\u043e\u0434\u0443\u043b\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""}],"deprecated_text":"","name":"turrets","deprecated":false},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0441\u0442\u0430\u043d\u0430\u0432\u043b\u0438\u0432\u0430\u0435\u043c\u044b\u0445 \u043e\u0440\u0443\u0434\u0438\u044f\u0445","fields":[{"doc_type":"boolean","name":"is_default","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u043c\u043e\u0434\u0443\u043b\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""}],"deprecated_text":"","name":"guns","deprecated":false},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0441\u0442\u0430\u043d\u0430\u0432\u043b\u0438\u0432\u0430\u0435\u043c\u044b\u0445 \u0434\u0432\u0438\u0433\u0430\u0442\u0435\u043b\u044f\u0445","fields":[{"doc_type":"boolean","name":"is_default","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u043c\u043e\u0434\u0443\u043b\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""}],"deprecated_text":"","name":"engines","deprecated":false},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0441\u0442\u0430\u043d\u0430\u0432\u043b\u0438\u0432\u0430\u0435\u043c\u044b\u0445 \u0448\u0430\u0441\u0441\u0438","fields":[{"doc_type":"boolean","name":"is_default","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u043c\u043e\u0434\u0443\u043b\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""}],"deprecated_text":"","name":"chassis","deprecated":false},{"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e\u0431 \u0443\u0441\u0442\u0430\u043d\u0430\u0432\u043b\u0438\u0432\u0430\u0435\u043c\u044b\u0445 \u0440\u0430\u0434\u0438\u043e\u0441\u0442\u0430\u043d\u0446\u0438\u044f\u0445","fields":[{"doc_type":"boolean","name":"is_default","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u043c\u043e\u0434\u0443\u043b\u044c \u0441\u0442\u043e\u043a\u043e\u0432\u044b\u0439","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""}],"deprecated_text":"","name":"radios","deprecated":false},{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"string","name":"type_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u044b\u0439 \u0442\u0438\u043f \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"boolean","name":"is_premium","deprecated":false,"required":true,"help_text":"\u041f\u0440\u0438\u0437\u043d\u0430\u043a, \u0447\u0442\u043e \u0442\u0430\u043d\u043a - \u043f\u0440\u0435\u043c\u0438\u0443\u043c\u043d\u044b\u0439","deprecated_text":""},{"doc_type":"list of integers","name":"parent_tanks","deprecated":true,"required":false,"help_text":"\u0420\u043e\u0434\u0438\u0442\u0435\u043b\u044c\u0441\u043a\u0438\u0435 \u0442\u0430\u043d\u043a\u0438","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankinfo ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'tank_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tankinfo', $input, array('http', 'https'));
return $output;
}

/**
 * Список двигателей танков
 * Метод возвращает список двигателей танков.
 * @category Энциклопедия
 * @link encyclopedia/tankengines
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['module_id'] Идентификатор модуля
 * @param string $input['nation'] Нация. Допустимые значения: 
 * 
 * * "ussr" - СССР 
 * * "germany" - Германия 
 * * "usa" - США 
 * * "france" - Франция 
 * * "uk" - Великобритания 
 * * "china" - Китай 
 * * "japan" - Япония 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":true,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""},{"doc_type":"numeric","name":"power","deprecated":false,"required":true,"help_text":"\u041c\u043e\u0449\u043d\u043e\u0441\u0442\u044c","deprecated_text":""},{"doc_type":"numeric","name":"fire_starting_chance","deprecated":false,"required":true,"help_text":"\u0412\u0435\u0440\u043e\u044f\u0442\u043d\u043e\u0441\u0442\u044c \u043f\u043e\u0436\u0430\u0440\u0430","deprecated_text":""},{"doc_type":"list of integers","name":"tanks","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankengines ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tankengines', $input, array('http', 'https'));
return $output;
}

/**
 * Список башен танков
 * Метод возвращает список башен танков.
 * @category Энциклопедия
 * @link encyclopedia/tankturrets
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['module_id'] Идентификатор модуля
 * @param string $input['nation'] Нация. Допустимые значения: 
 * 
 * * "ussr" - СССР 
 * * "germany" - Германия 
 * * "usa" - США 
 * * "france" - Франция 
 * * "uk" - Великобритания 
 * * "china" - Китай 
 * * "japan" - Япония 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":true,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""},{"doc_type":"numeric","name":"armor_forehead","deprecated":false,"required":true,"help_text":"\u041b\u043e\u0431\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f","deprecated_text":""},{"doc_type":"numeric","name":"armor_board","deprecated":false,"required":true,"help_text":"\u0411\u043e\u0440\u0442\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f","deprecated_text":""},{"doc_type":"numeric","name":"armor_fedd","deprecated":false,"required":true,"help_text":"\u041a\u043e\u0440\u043c\u043e\u0432\u0430\u044f \u0431\u0440\u043e\u043d\u044f","deprecated_text":""},{"doc_type":"numeric","name":"rotation_speed","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u044c \u0432\u0440\u0430\u0449\u0435\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"circular_vision_radius","deprecated":false,"required":true,"help_text":"\u041e\u0431\u0437\u043e\u0440","deprecated_text":""},{"doc_type":"list of integers","name":"tanks","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankturrets ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tankturrets', $input, array('http', 'https'));
return $output;
}

/**
 * Список радиостанций танков
 * Метод возвращает список радиостанций танков.
 * @category Энциклопедия
 * @link encyclopedia/tankradios
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['module_id'] Идентификатор модуля
 * @param string $input['nation'] Нация. Допустимые значения: 
 * 
 * * "ussr" - СССР 
 * * "germany" - Германия 
 * * "usa" - США 
 * * "france" - Франция 
 * * "uk" - Великобритания 
 * * "china" - Китай 
 * * "japan" - Япония 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":true,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""},{"doc_type":"numeric","name":"distance","deprecated":false,"required":true,"help_text":"\u0414\u0438\u0441\u0442\u0430\u043d\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"list of integers","name":"tanks","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankradios ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tankradios', $input, array('http', 'https'));
return $output;
}

/**
 * Список ходовых танков
 * Метод возвращает список ходовых танков.
 * @category Энциклопедия
 * @link encyclopedia/tankchassis
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['module_id'] Идентификатор модуля
 * @param string $input['nation'] Нация. Допустимые значения: 
 * 
 * * "ussr" - СССР 
 * * "germany" - Германия 
 * * "usa" - США 
 * * "france" - Франция 
 * * "uk" - Великобритания 
 * * "china" - Китай 
 * * "japan" - Япония 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":true,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""},{"doc_type":"float","name":"max_load","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u0430\u044f \u043d\u0430\u0433\u0440\u0443\u0437\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"rotation_speed","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u044c \u043f\u043e\u0432\u043e\u0440\u043e\u0442\u0430","deprecated_text":""},{"doc_type":"list of integers","name":"tanks","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankchassis ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string')))
return NULL;
$output = $this->send('encyclopedia/tankchassis', $input, array('http', 'https'));
return $output;
}

/**
 * Список орудий танков
 * Метод возвращает список орудий танков.
 * 
 * Возможны изменения логики работы метода и значений некоторых полей в зависимости от переданных дополнительных параметров.
 * 
 * Поля, которые могут измениться:
 * 
 * * **damage**
 * * **piercing_power**
 * * **rate**
 * * **price_credit**
 * * **price_gold**
 * 
 * Влияние дополнительных входных параметров:
 * 
 * * передан корректный **turret_id** — происходит фильтрация по принадлежности орудий к башне и изменение вышеуказанных характеристик в зависимости от башни
 * * передан корректный **turret_id** и **module_id** — возвращает информацию по каждому модулю с измененными вышеуказанными характеристиками в зависимости от башни или null, если модуль и башня не совместимы
 * * передан корректный **tank_id** - если тип танка соответствует одному из AT-SPG, SPG, mediumTank — будет проведена фильтрация по по принадлежности орудий к танку и изменены вышеуказанные характеристики в зависимости от танка. В противном случае будет возвращена ошибка о необходимости указания **turret_id**. Если переданы еще и **module_id**, то будет возвращена информация о каждом модуле с измененными вышеуказанными характеристиками в зависимости от танка или null, если модуль с танком не совместим
 * * переданы совместимые **turret_id** и **tank_id** — происходит фильтрация по принадлежности орудий к башне и танку, и изменение вышеуказанных характеристик в зависимости от башни
 * 
 * @category Энциклопедия
 * @link encyclopedia/tankguns
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param numeric|list $input['module_id'] Идентификатор модуля
 * @param string $input['nation'] Нация. Допустимые значения: 
 * 
 * * "ussr" - СССР 
 * * "germany" - Германия 
 * * "usa" - США 
 * * "france" - Франция 
 * * "uk" - Великобритания 
 * * "china" - Китай 
 * * "japan" - Япония 
 * @param numeric $input['turret_id'] Идентификатор совместимой башни
 * @param numeric $input['tank_id'] Идентификатор совместимого танка
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"nation_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0430\u0446\u0438\u044f \u043d\u0430\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"numeric","name":"level","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u0432\u0435\u043d\u044c","deprecated_text":""},{"doc_type":"numeric","name":"price_gold","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u0437\u043e\u043b\u043e\u0442\u0435","deprecated_text":""},{"doc_type":"numeric","name":"price_credit","deprecated":false,"required":true,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043a\u0440\u0435\u0434\u0438\u0442\u0430\u0445","deprecated_text":""},{"doc_type":"numeric","name":"price_xp","deprecated":true,"required":false,"help_text":"\u0426\u0435\u043d\u0430 \u0432 \u043e\u043f\u044b\u0442\u0435","deprecated_text":""},{"doc_type":"float","name":"weight","deprecated":true,"required":false,"help_text":"\u0412\u0435\u0441","deprecated_text":""},{"doc_type":"numeric","name":"module_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u043c\u043e\u0434\u0443\u043b\u044f","deprecated_text":""},{"doc_type":"list of integers","name":"damage","deprecated":false,"required":true,"help_text":"\u0423\u0440\u043e\u043d","deprecated_text":""},{"doc_type":"list of integers","name":"piercing_power","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0431\u0438\u0442\u0438\u0435","deprecated_text":""},{"doc_type":"float","name":"rate","deprecated":false,"required":true,"help_text":"\u0421\u043a\u043e\u0440\u043e\u0441\u0442\u0440\u0435\u043b\u044c\u043d\u043e\u0441\u0442\u044c","deprecated_text":""},{"doc_type":"list of integers","name":"turrets","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0431\u0430\u0448\u0435\u043d","deprecated_text":""},{"doc_type":"list of integers","name":"tanks","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440\u044b \u0441\u043e\u0432\u043c\u0435\u0441\u0442\u0438\u043c\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function tankguns ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string', 'turret_id' => 'numeric', 'tank_id' => 'numeric')))
return NULL;
$output = $this->send('encyclopedia/tankguns', $input, array('http', 'https'));
return $output;
}

/**
 * Достижения
 * 
 * @category Энциклопедия
 * @link encyclopedia/achievements
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"name","deprecated":false,"required":true,"help_text":"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"type","deprecated":false,"required":true,"help_text":"\u0422\u0438\u043f","deprecated_text":""},{"doc_type":"string","name":"section","deprecated":false,"required":true,"help_text":"\u0421\u0435\u043a\u0446\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"section_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0441\u0435\u043a\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"numeric","name":"section_order","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0440\u044f\u0434\u043e\u043a \u043e\u0442\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u044f \u0441\u0435\u043a\u0446\u0438\u0439","deprecated_text":""},{"doc_type":"string","name":"image","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"image_big","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u0435 180x180px","deprecated_text":""},{"doc_type":"string","name":"description","deprecated":false,"required":true,"help_text":"\u041e\u043f\u0438\u0441\u0430\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"condition","deprecated":false,"required":true,"help_text":"\u0423\u0441\u043b\u043e\u0432\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"hero_info","deprecated":false,"required":true,"help_text":"Historical info","deprecated_text":""},{"doc_type":"numeric","name":"order","deprecated":false,"required":true,"help_text":"\u041f\u043e\u0440\u044f\u0434\u043e\u043a \u043e\u0442\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u044f \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f","deprecated_text":""},{"help_text":"\u0412\u0430\u0440\u0438\u0430\u0446\u0438\u0438 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f","fields":[{"doc_type":"string","name":"name_i18n","deprecated":false,"required":true,"help_text":"\u041b\u043e\u043a\u0430\u043b\u0438\u0437\u0438\u0440\u043e\u0432\u0430\u043d\u043d\u043e\u0435 \u043d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f","deprecated_text":""},{"doc_type":"string","name":"image","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u0435","deprecated_text":""},{"doc_type":"string","name":"image_big","deprecated":false,"required":true,"help_text":"\u0418\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u0435 180x180px","deprecated_text":""}],"deprecated_text":"","name":"options","deprecated":false}],"deprecated_text":"","name":"","deprecated":false}}
 */
function achievements ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string')))
return NULL;
$output = $this->send('encyclopedia/achievements', $input, array('http', 'https'));
return $output;
}

/**
 * Информация о Танкопедии
 * Метод возвращает информацию о Танкопедии.
 * @category Энциклопедия
 * @link encyclopedia/info
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"string","name":"game_version","deprecated":false,"required":true,"help_text":"\u0412\u0435\u0440\u0441\u0438\u044f \u0438\u0433\u0440\u043e\u0432\u043e\u0433\u043e \u043a\u043b\u0438\u0435\u043d\u0442\u0430","deprecated_text":""},{"doc_type":"timestamp","name":"game_updated_at","deprecated":false,"required":true,"help_text":"\u0414\u0430\u0442\u0430 \u043e\u0431\u043d\u043e\u0432\u043b\u0435\u043d\u0438\u044f \u0438\u0433\u0440\u043e\u0432\u043e\u0433\u043e \u043a\u043b\u0438\u0435\u043d\u0442\u0430","deprecated_text":""},{"doc_type":"associative array","name":"vehicle_types","deprecated":false,"required":true,"help_text":"\u0412\u043e\u0437\u043c\u043e\u0436\u043d\u044b\u0435 \u0442\u0438\u043f\u044b \u0442\u0435\u0445\u043d\u0438\u043a\u0438","deprecated_text":""},{"doc_type":"associative array","name":"vehicle_nations","deprecated":false,"required":true,"help_text":"\u0412\u043e\u0437\u043c\u043e\u0436\u043d\u044b\u0435 \u043d\u0430\u0446\u0438\u0438","deprecated_text":""},{"doc_type":"associative array","name":"vehicle_crew_roles","deprecated":false,"required":true,"help_text":"\u0412\u043e\u0437\u043c\u043e\u0436\u043d\u044b\u0435 \u0440\u043e\u043b\u0438 \u044d\u043a\u0438\u043f\u0430\u0436\u0430","deprecated_text":""},{"doc_type":"associative array","name":"clans_roles","deprecated":false,"required":true,"help_text":"\u0412\u043e\u0437\u043c\u043e\u0436\u043d\u044b\u0435 \u0440\u043e\u043b\u0438 \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a\u043e\u0432 \u043a\u043b\u0430\u043d\u0430","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function info ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string')))
return NULL;
$output = $this->send('encyclopedia/info', $input, array('http', 'https'));
return $output;
}

}

/**
 * Танки игрока 
 */
class wgapi_wot_tanks extends WgApiCore {

/**
 * Статистика по танкам игрока
 * Возвращает общую, ротную и клановую статистику по каждому танку каждого пользователя.
 * @category Танки игрока
 * @link tanks/stats
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param numeric|list $input['tank_id'] Идентификатор танка игрока
 * @param string $input['in_garage'] Фильтрация по присутствию танка в гараже. Если параметр не указан, возвращаются все танки.Параметр обрабатывается только при наличии валидного access_token для указанного account_id. Допустимые значения: 
 * 
 * * "1" - Возвращать только танки из гаража 
 * * "0" - Возвращать танки, которых уже нет в гараже 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"tank_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"help_text":"\u0412\u0441\u044f \u0441\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"all","deprecated":false},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u0440\u043e\u0442\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"company","deprecated":false},{"help_text":"\u0421\u0442\u0430\u0442\u0438\u0441\u0442\u0438\u043a\u0430 \u043a\u043b\u0430\u043d\u043e\u0432\u044b\u0445 \u0431\u043e\u0451\u0432","fields":[{"doc_type":"numeric","name":"battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0431\u043e\u0451\u0432","deprecated_text":""},{"doc_type":"numeric","name":"wins","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0431\u0435\u0434","deprecated_text":""},{"doc_type":"numeric","name":"xp","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u043e\u0433\u043e \u043e\u043f\u044b\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"losses","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u0440\u0430\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"survived_battles","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0431\u043e\u0451\u0432 \u0432 \u043a\u043e\u0442\u043e\u0440\u044b\u0445 \u0438\u0433\u0440\u043e\u043a \u0432\u044b\u0436\u0438\u043b","deprecated_text":""},{"doc_type":"numeric","name":"damage_received","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"damage_dealt","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0430\u043d\u0435\u0441\u0451\u043d\u043d\u044b\u0445 \u043f\u043e\u0432\u0440\u0435\u0436\u0434\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"spotted","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0431\u043d\u0430\u0440\u0443\u0436\u0435\u043d\u043d\u044b\u0445 \u043f\u0440\u043e\u0442\u0438\u0432\u043d\u0438\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"shots","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u0440\u043e\u0438\u0437\u0432\u0435\u0434\u0451\u043d\u043d\u044b\u0445 \u0432\u044b\u0441\u0442\u0440\u0435\u043b\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"hits","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"frags","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u0443\u043d\u0438\u0447\u0442\u043e\u0436\u0435\u043d\u043d\u044b\u0445 \u0442\u0430\u043d\u043a\u043e\u0432","deprecated_text":""},{"doc_type":"numeric","name":"capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0445\u0432\u0430\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"dropped_capture_points","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043e\u0447\u043a\u043e\u0432 \u0437\u0430\u0449\u0438\u0442\u044b","deprecated_text":""},{"doc_type":"numeric","name":"hits_percents","deprecated":false,"required":true,"help_text":"\u041f\u0440\u043e\u0446\u0435\u043d\u0442 \u043f\u043e\u043f\u0430\u0434\u0430\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"numeric","name":"draws","deprecated":false,"required":true,"help_text":"\u041a\u043e\u043b-\u0432\u043e \u043d\u0438\u0447\u044c\u0438\u0445","deprecated_text":""},{"doc_type":"numeric","name":"battle_avg_xp","deprecated":false,"required":true,"help_text":"\u0421\u0440\u0435\u0434\u043d\u0438\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""}],"deprecated_text":"","name":"clan","deprecated":false},{"doc_type":"numeric","name":"max_xp","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0437\u0430\u0440\u0430\u0431\u043e\u0442\u0430\u043d\u044b\u0439 \u043e\u043f\u044b\u0442 \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""},{"doc_type":"numeric","name":"max_frags","deprecated":false,"required":true,"help_text":"\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u043e \u0443\u0431\u0438\u0442\u043e \u0437\u0430 \u0431\u043e\u0439","deprecated_text":""},{"doc_type":"boolean","name":"in_garage","deprecated":false,"required":false,"help_text":"\u041f\u0440\u0438\u0441\u0443\u0442\u0441\u0442\u0432\u0438\u0435 \u0442\u0430\u043d\u043a\u0430 \u0432 \u0433\u0430\u0440\u0430\u0436\u0435. \u0414\u0430\u043d\u043d\u044b\u0435 \u0434\u043e\u0441\u0442\u0443\u043f\u043d\u044b \u0442\u043e\u043b\u044c\u043a\u043e \u043f\u0440\u0438 \u043d\u0430\u043b\u0438\u0447\u0438\u0438 \u0432\u0430\u043b\u0438\u0434\u043d\u043e\u0433\u043e access_token \u0434\u043b\u044f \u0443\u043a\u0430\u0437\u0430\u043d\u043d\u043e\u0433\u043e \u0430\u043a\u043a\u0430\u0443\u043d\u0442\u0430","deprecated_text":""},{"doc_type":"numeric","name":"mark_of_mastery","deprecated":false,"required":true,"help_text":"\n\u0417\u043d\u0430\u043a \u043a\u043b\u0430\u0441\u0441\u043d\u043e\u0441\u0442\u0438:\n\n * 0 - \u041e\u0442\u0441\u0443\u0442\u0441\u0442\u0432\u0443\u0435\u0442\n * 1 - \u0422\u0440\u0435\u0442\u044c\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 2 - \u0412\u0442\u043e\u0440\u0430\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 3 - \u041f\u0435\u0440\u0432\u0430\u044f \u0441\u0442\u0435\u043f\u0435\u043d\u044c\n * 4 - \u041c\u0430\u0441\u0442\u0435\u0440\n","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function stats ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string')))
return NULL;
$output = $this->send('tanks/stats', $input, array('http', 'https'));
return $output;
}

/**
 * Достижения по танкам игрока
 * Возвращает список достижений по всем танкам игрока.
 * 
 * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях]( /developers/api_reference/wot/encyclopedia/achievements )):
 * 
 * * степень от 1 до 4 для знака классности и этапных достижений (type: "class")
 * * максимальное значение серийных достижений (type: "series")
 * * количество заработанных наград из секций: Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и тп. (type: "repeatable, single, custom")
 * 
 * @category Танки игрока
 * @link tanks/achievements
 * @param array $input
 * @param string $input['application_id'] Идентификатор приложения
 * @param numeric $input['account_id'] Идентификатор аккаунта игрока
 * @param string $input['language'] Язык локализации. Допустимые значения: 
 * 
 * * "en" - English 
 * * "ru" - Русский (используется по умолчанию)
 * * "pl" - Polski 
 * * "de" - Deutsch 
 * * "fr" - Français 
 * * "es" - Español 
 * * "zh-cn" - 简体中文 
 * * "tr" - Türkçe 
 * * "cs" - Čeština 
 * * "th" - ไทย 
 * * "vi" - Tiếng Việt 
 * * "ko" - 한국어 
 * @param string $input['fields'] Список полей ответа. Поля разделяются запятыми. Вложенные поля разделяются точками. Если параметр не указан, возвращаются все поля.
 * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
 * @param numeric|list $input['tank_id'] Идентификатор танка игрока
 * @param string $input['in_garage'] Фильтрация по присутствию танка в гараже. Если параметр не указан, возвращаются все танки.Параметр обрабатывается только при наличии валидного access_token для указанного account_id. Допустимые значения: 
 * 
 * * "1" - Возвращать только танки из гаража 
 * * "0" - Возвращать танки, которых уже нет в гараже 
 * @return array
 * {"output_form_info":{"help_text":"","fields":[{"doc_type":"numeric","name":"account_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0438\u0433\u0440\u043e\u043a\u0430","deprecated_text":""},{"doc_type":"numeric","name":"tank_id","deprecated":false,"required":true,"help_text":"\u0418\u0434\u0435\u043d\u0442\u0438\u0444\u0438\u043a\u0430\u0442\u043e\u0440 \u0442\u0430\u043d\u043a\u0430","deprecated_text":""},{"doc_type":"associative array","name":"achievements","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043f\u043e\u043b\u0443\u0447\u0435\u043d\u043d\u044b\u0445 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u044f\u0445","deprecated_text":""},{"doc_type":"associative array","name":"series","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u0442\u0435\u043a\u0443\u0449\u0438\u0445 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u044f\u0445 \u0441\u0435\u0440\u0438\u0439\u043d\u044b\u0445 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u0439","deprecated_text":""},{"doc_type":"associative array","name":"max_series","deprecated":false,"required":true,"help_text":"\u0418\u043d\u0444\u043e\u0440\u043c\u0430\u0446\u0438\u044f \u043e \u043c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0445 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u044f\u0445 \u0441\u0435\u0440\u0438\u0439\u043d\u044b\u0445 \u0434\u043e\u0441\u0442\u0438\u0436\u0435\u043d\u0438\u0439","deprecated_text":""}],"deprecated_text":"","name":"","deprecated":false}}
 */
function achievements ($input = array()) {
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string')))
return NULL;
$output = $this->send('tanks/achievements', $input, array('http', 'https'));
return $output;
}

}

