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
  private $protocol = 'https';

  /**
   * Метод поумолчанию
   * @var string 
   */
  private $http_method = 'POST';

  /**
   * Наименование апи или ее версия
   * @var string 
   */
  private $API_name = 'wot';

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
   * Инициализация входящих параметров и методов запросов
   * @param array $params Массив переменных для инициализации класса
   * @todo Наполнить функцию
   */
  function __construct($params = array()) {
    
  }

  /**
   * Сохранняем регион и сервер для клиента
   * @param string $region регион определенный из возможных вариантов ('ASIA','EU', 'KR', 'NA', 'RU') Поумолчанию: 'RU'
   */
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
   * Устанавливает используемый метод запросов
   * @param array $http_method варианты используемых методов для запросов
   * @return string
   */
  function setMethod($http_method = array('GET', 'POST')) {
    $http_method = (array) $http_method;
    if (count($http_method) == 0)
      return $this->http_method;
    if (in_array($this->http_method, $http_method))
      return $this->http_method;
    return $http_method[0];
  }

  /**
   * Присвоение авторизационных данных для оъекта
   * @param string $info['token'] Ключ авторизации пользователя
   * @param string $info['name'] Имя пользователя в игре
   * @param integer $info['user_id'] Идентификатор пользователя
   * @param integer $info['expire'] Время завершения действия ключа авторизации
   */
  function setUser($info = array()) {
    $info = (array) $info;
    $this->token = (string) @$info['token'];
    $this->name = (string) @$info['name'];
    $this->id = (integer) @$info['user_id'];
    $this->expire = (integer) @$info['expire'];
  }

}
