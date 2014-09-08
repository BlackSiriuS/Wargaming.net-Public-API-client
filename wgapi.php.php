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

      if (isset($method['allowed_protocols'])) {
        if (is_array($method['allowed_protocols']))
          $functional .= "\$allowed_protocols = array('" . implode("', '", $method['allowed_protocols']) . "');\n";
        unset($method['allowed_protocols']);
      }

      foreach ($input_fields as &$type)
        foreach ($type as $key => &$field) {
          $field = "'{$key}' => '{$field}'";
        }
      $functional .= "if (!\$this->validate_input(\$input, array(" . implode(", ", (array) @$input_fields['required']) . "), array(" . implode(", ", (array) @$input_fields['other']) . "))) {return NULL;}\n";
      unset($input_fields);
      $documentation .= "{$method['name']}\n";
      $documentation .= "{$method['description']}\n";
      $documentation .= "@category {$method['category_name']}\n";
      $documentation .= "@link {$method['url']}\n";
      if ($method['deprecated']) {
        $documentation .= "@todo deprecated\n";
      }
      $documentation .= "@param array \$input\n";
      unset($method['name'], $method['description'], $method['category_name'], $method['deprecated']);
      foreach ($input_form_info as $input_form) {
        foreach ($input_form as $fields) {
          $fields['doc_type'] = str_replace(', ', '|', $fields['doc_type']);
          $documentation .= "@param {$fields['doc_type']} \$input['{$fields['name']}'] {$fields['help_text']}\n";
          if ($fields['deprecated'])
            $documentation .= "@todo deprecated \$input['{$fields['name']}'] {$fields['deprecated_text']}\n";
        }
      }

      $documentation .= "@return array\n";
      $documentation = "\n/**\n * " . str_replace(array("\n", "&mdash;", "  "), array("\n * ", "-", " "), trim($documentation)) . "\n */\n";
      $functional .= "\$output = \$this->send('{$method['url']}', \$input/*, \$protocol*/);\n";
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
 */
function lists ($input = array()) {
$this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'search' => 'string', 'limit' => 'numeric', 'order_by' => 'string', 'page_no' => 'numeric'))) {return NULL;}
$output = $this->send('clan/list', $input/*, $protocol*/);
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
 */
function info ($input = array()) {
$this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) {return NULL;}
$output = $this->send('clan/info', $input/*, $protocol*/);
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
 */
function battles ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'map_id' => 'numeric'))) {return NULL;}
$output = $this->send('clan/battles', $input/*, $protocol*/);
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
 */
function top ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'string', 'time' => 'string'))) {return NULL;}
$output = $this->send('clan/top', $input/*, $protocol*/);
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
 */
function provinces ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) {return NULL;}
$output = $this->send('clan/provinces', $input/*, $protocol*/);
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
 */
function victorypoints ($input = array()) {
$this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('clan/victorypoints', $input/*, $protocol*/);
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
 */
function victorypointshistory ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'numeric', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('clan/victorypointshistory', $input/*, $protocol*/);
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
 */
function membersinfo ($input = array()) {
$this->erorr->add(array(array(407, "MEMBER_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **member_id** превышен ( >100 )")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'member_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('clan/membersinfo', $input/*, $protocol*/);
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
 */
function types ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('ratings/types', $input/*, $protocol*/);
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
 */
function accounts ($input = array()) {
$this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date'))) {return NULL;}
$output = $this->send('ratings/accounts', $input/*, $protocol*/);
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
 */
function neighbors ($input = array()) {
$this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('ratings/neighbors', $input/*, $protocol*/);
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
 */
function top ($input = array()) {
$this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('ratings/top', $input/*, $protocol*/);
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
 */
function dates ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'account_id' => 'numeric, list'))) {return NULL;}
$output = $this->send('ratings/dates', $input/*, $protocol*/);
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
 */
function lists ($input = array()) {
$this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'search' => 'string'), array('language' => 'string', 'fields' => 'string', 'type' => 'string', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('account/list', $input/*, $protocol*/);
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
 */
function info ($input = array()) {
$this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) {return NULL;}
$output = $this->send('account/info', $input/*, $protocol*/);
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
 */
function tanks ($input = array()) {
$this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list'))) {return NULL;}
$output = $this->send('account/tanks', $input/*, $protocol*/);
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
 */
function achievements ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('account/achievements', $input/*, $protocol*/);
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
 */
function clans ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'limit' => 'numeric', 'page_no' => 'numeric'))) {return NULL;}
$output = $this->send('globalwar/clans', $input/*, $protocol*/);
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
 */
function famepoints ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('globalwar/famepoints', $input/*, $protocol*/);
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
 */
function maps ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('globalwar/maps', $input/*, $protocol*/);
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
 */
function provinces ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'province_id' => 'string, list'))) {return NULL;}
$output = $this->send('globalwar/provinces', $input/*, $protocol*/);
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
 */
function top ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'order_by' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('globalwar/top', $input/*, $protocol*/);
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
 */
function tournaments ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'province_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('globalwar/tournaments', $input/*, $protocol*/);
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
 */
function famepointshistory ($input = array()) {
$allowed_protocols = array('https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'page_no' => 'numeric', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('globalwar/famepointshistory', $input/*, $protocol*/);
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
 */
function alleyoffame ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'page_no' => 'numeric', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('globalwar/alleyoffame', $input/*, $protocol*/);
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
 */
function battles ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) {return NULL;}
$output = $this->send('globalwar/battles', $input/*, $protocol*/);
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
 */
function victorypointshistory ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric'))) {return NULL;}
$output = $this->send('globalwar/victorypointshistory', $input/*, $protocol*/);
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
 */
function login ($input = array()) {
$this->erorr->add(array(array(401, "AUTH_CANCEL", "Пользователь отменил авторизацию для приложения"), array(403, "AUTH_EXPIRED", "Превышено время ожидания подтверждения авторизации пользователем"), array(410, "AUTH_ERROR", "Ошибка аутентификации")));
$allowed_protocols = array('https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'expires_at' => 'numeric', 'redirect_uri' => 'string', 'display' => 'string', 'nofollow' => 'numeric'))) {return NULL;}
$output = $this->send('auth/login', $input/*, $protocol*/);
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
 */
function prolongate ($input = array()) {
$allowed_protocols = array('https');
if (!$this->validate_input($input, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'expires_at' => 'numeric'))) {return NULL;}
$output = $this->send('auth/prolongate', $input/*, $protocol*/);
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
 */
function logout ($input = array()) {
$allowed_protocols = array('https');
if (!$this->validate_input($input, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string'))) {return NULL;}
$output = $this->send('auth/logout', $input/*, $protocol*/);
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
 */
function tanks ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tanks', $input/*, $protocol*/);
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
 */
function tankinfo ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'tank_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tankinfo', $input/*, $protocol*/);
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
 */
function tankengines ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tankengines', $input/*, $protocol*/);
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
 */
function tankturrets ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tankturrets', $input/*, $protocol*/);
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
 */
function tankradios ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tankradios', $input/*, $protocol*/);
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
 */
function tankchassis ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/tankchassis', $input/*, $protocol*/);
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
 */
function tankguns ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string', 'turret_id' => 'numeric', 'tank_id' => 'numeric'))) {return NULL;}
$output = $this->send('encyclopedia/tankguns', $input/*, $protocol*/);
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
 */
function achievements ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/achievements', $input/*, $protocol*/);
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
 */
function info ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string'), array('language' => 'string'))) {return NULL;}
$output = $this->send('encyclopedia/info', $input/*, $protocol*/);
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
 */
function stats ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) {return NULL;}
$output = $this->send('tanks/stats', $input/*, $protocol*/);
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
 */
function achievements ($input = array()) {
$allowed_protocols = array('http', 'https');
if (!$this->validate_input($input, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) {return NULL;}
$output = $this->send('tanks/achievements', $input/*, $protocol*/);
return $output;

}

}

