<?php

/**
 * Клиент для API World of Tanks с возможностью самому создавать свои функции выполнения
 *
 * @author XIRIX
 */
class Wot_api {

  /**
   * Протокол поумолчанию
   * @var string 
   */
  public $protocol = 'https';

  /**
   * Метод поумолчанию
   * @var string 
   */
  //public $http_method = 'POST';

  /**
   * Наименование апи или ее версия
   * @var string 
   */
  public $API_name = 'wot';

  /**
   * Ключ для Автономного приложения
   * Лимит выставляется на количество одновременных запросов с одного IP-адреса , и может составлять от 2 до 4 запросов в секунду.
   * @var string 
   */
  private $standalone = 'demo';

  /**
   * Ключ для Серверного приложения
   * Лимитируются по количеству запросов от приложения в секунду. В зависимости от кластера лимит может составлять от 10 до 20 запросов в секунду.
   * @var string 
   */
  private $server = 'demo';

  /**
   * Ключ авторизации пользователя
   * Если ваше приложение использует персональные данные пользователя, ознакомьтесь с правилами использования access token.
   * Для получения персональных данных пользователя необходим access token. Access token выдаётся после аутентификации пользователя по Open ID.
   * Если в вашем приложении предусмотрена аутентификация через Wargaming.net Open ID, то наличие функции «выход» обязательно.
   * Срок действия access token составляет две недели с момента его получения. Для продления срока действия активного access token, используйте метод auth/prolongate.
   * Если срок действия access token не был продлён, то при обращении к персональным данным пользователя появится сообщение об ошибке, и пользователь вашего приложения будет вынужден повторно аутентифицироваться. В целях безопасности все запросы, содержащие access token, должны отправляться по HTTPS.
   * @var string 
   */
  private $token = '';

  /**
   * Переменная мультизапросов
   * @var boolean 
   */
  public $use_multicall = false;

  /**
   * стек для мультизапросов
   * @var array 
   */
  public $stack_multicall = array();
  public $method_multicall = '';
  public $load_class = array('account', 'auth', 'clan', 'encyclopedia', 'globalwar', 'ratings', 'tanks');
  public $parent = true;

  /**
   * Инициализация входящих параметров и методов запросов
   * @param array $params Массив переменных для инициализации класса
   * @todo Наполнить функцию
   */
  function __construct($params = array()) {
    $params = (array) $params;
    if (isset($params['standalone']) && !empty($params['standalone']))
      $this->standalone = (string) $params['standalone'];
    if (isset($params['server']) && !empty($params['server']))
      $this->server = (string) $params['server'];
    if (isset($params['api_name']) && !empty($params['api_name']))
      $this->API_name = (string) $params['api_name'];
    $this->setRegion((string) $params['region']);
    $this->setLanguage((string) $params['language']);
    if (isset($params['user']) && is_array($params['user']))
      $this->setUser($info);
    if (isset($params['parent']))
      $this->parent = (bool) $params['parent'];
    $this->load();
  }

  function load() {
    $this->erorr = new Wot_api_error();
    if ($this->parent)
      foreach ($this->load_class as $class)
        if (class_exists($class)) {
          $load_class = 'api_' . $this->API_name . '_' . $class;
          $item = $this;
          $item->parent = false;
          $this->$class = new $load_class($item);
        }
  }

  /**
   * Сохранняем регион и сервер для клиента
   * @param string $region регион определенный из возможных вариантов ('ASIA','EU', 'KR', 'NA', 'RU') Поумолчанию: 'RU'
   */
  function setRegion($region = 'RU') {
    switch ($region) {
      case 'ASIA': $this->region = 'ASIA';
        $this->serverhost = 'api.worldoftanks.asia';
        break;
      case 'EU': $this->region = 'EU';
        $this->serverhost = 'api.worldoftanks.eu';
        break;
      case 'KR': $this->region = 'KR';
        $this->serverhost = 'api.worldoftanks.kr';
        break;
      case 'NA': $this->region = 'NA';
        $this->serverhost = 'api.worldoftanks.com';
        break;
      case 'RU':
      default: $this->region = 'RU';
        $this->serverhost = 'api.worldoftanks.ru';
        break;
    }
  }

  /**
   * Устанавливает язык выдаваемой информации
   * @param string $language язык определенный из возможных вариантов ('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn') Поумолчанию: 'ru'
   */
  function setLanguage($language = 'ru') {
    $language_list = array('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn');
    if (in_array($language, $language_list))
      $this->language = $language;
    else
      $this->language = 'ru';
  }

  /**
   * Устанавливает используемый протокол для метода
   * @param array $protocol варианты используемых протоколов для запросов
   * @param boolean $with_token используется ли метод авторизации пользователя
   * @return string
   */
  function setProtocol($protocol = array('http', 'https'), $with_token = false) {
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

  /**
   * Формирование URI-формата
   * @param string $mblock название группы методов
   * @param string $mname название метода
   * @return string
   */
  function setURL($mblock = '', $mname = '') {
    $_params = array($this->protocol . ':/', $this->serverhost, $this->API_name);
    if (isset($mblock) && !empty($mblock))
      $_params[] = $mblock;
    if (isset($mname) && !empty($mname))
      $_params[] = $mname;
    $_params[] = '';
    $this->url = implode('/', $_params);
    return $this->url;
  }

  /**
   * Присвоение авторизационных данных для оъекта
   * @param array $info авторизпционные данные пользователя
   * @param string $info['token'] Ключ авторизации пользователя
   * @param string $info['name'] Имя пользователя в игре
   * @param integer $info['user_id'] Идентификатор пользователя
   * @param integer $info['expire'] Время завершения действия ключа авторизации
   */
  function setUser($info = array()) {

    $info = (array) $info;
    $this->user = $info;
    $this->token = (string) @$info['token'];
    $this->name = (string) @$info['name'];
    $this->id = (integer) @$info['user_id'];
    $this->expire = (integer) @$info['expire'];
  }

  function send($mblock = '', $params = array()) {
    if (isset($params['application_id']) && empty($params['application_id'])) {
      $params['application_id'] = isset($params['access_token']) ? $this->standalone : $this->server;
    }
    if (isset($params['language']) && empty($params['language']))
      $params['language'] = $this->language;

    if ($this->use_multicall) {
      $this->method_multicall = $mblock;
      array_unshift($this->stack_multicall, $params);
      return NULL;
    }

    $c = curl_init();
    if (count($params) > 0) {
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
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
      if ($erorr['message'] == 'INVALID_ACCESS_TOKEN') {
        unset($params['access_token']);
        return $this->send($mblock, $params);
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
    $fdata = substr($fdata, 0, strpos($fdata, "// " . "After this line rewrite code"));
    $fdata .= "// " . "After this line rewrite code" . "\n\n\n";
    if ($file = @fopen(__FILE__, "w")) {
      fwrite($file, $fdata);
      fclose($file);
    }
  }

}

class Wot_api_error {

  public $field = '';
  public $code = 0;
  public $message = '';
  public $value = '';
  public $stack_multicall = array();
  private $default_dictionary = array(
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

  function __construct() {
    $this->dictionary = $this->default_dictionary;
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

  function adderror($errors = array()) {
    foreach ($errors as $error) {
      $this->dictionary[] = $error;
    }
  }

  function getMessage() {
    return $this->message;
  }

  function getValue() {
    return $this->value;
  }

  function get() {
    return array(
      'code' => $this->code,
      'message' => $this->message,
      'value' => $this->value,
    );
  }

}

// After this line rewrite code


