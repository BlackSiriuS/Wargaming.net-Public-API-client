<?php

/**
 * Wgapi
 * 
 * Набор общедоступных методов API, которые предоставляют доступ к проектам Wargaming.net, включая игровой контент, статистику игроков, данные энциклопедии и многое другое.
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 1.0
 */
class Wgapi extends WgapiCore {

  /**
   * Параметр определяющий что данный класс является главным/первичным
   * @var boolean 
   */
  public $parent = true;

  /**
   * Выполняет загрузку вторичных классов, при их отсутствии выполняет обновляет данный файл.
   * @return boolean
   */
  function load() {
    //загрузка класса ошибок
    $this->erorr = new WgApiError();
    //проверка масива наименований методов
    if (count($this->load_class) == 0)
      return $this->update();
    //загрузка дочерних классов по наименованию метода
    foreach ($this->load_class as $_c) {
      $lc = "wgapi_{$this->apiName}_{$_c}";
      //проверка существования дочернего метода
      if (class_exists($lc)) {
        $this->$_c = new $lc($this);
      } else {
        /**
         * @todo Убрать все элементы вывода
         */
        echo "class not found '{$lc}'; \n";
        $this->update();
      }
    }
    return true;
  }

}

/**
 * WgApiError
 * 
 * Класс, хранящий в себе все ошибки полученные при выполнение запросов API
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 1.4
 */
class WgApiError {

  /**
   * Код ошибки
   * @var integer 
   */
  public $code = 0;

  /**
   * Поле из-за, которого возникла ошибка
   * @var string
   */
  public $field = '';

  /**
   * Сообщение ошибки
   * @var string 
   */
  public $message = '';

  /**
   * Описание ошибки для вывода пользователю
   * @var string 
   */
  public $value = '';

  /**
   * Устанавливает первичный словарь ошибок для возможности обработки описания
   */
  function __construct() {
    //первичный словарь ошибок
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

  /**
   * Добавления ошибок в словарь
   * @param array $er Масив ошибок @example array(array(code, message, value))
   * @return array Новый словарь ошибок
   */
  function add($er = array()) {
    //добавление нескольких ошибок в цикле
    foreach ($er as $_er)
      $this->dictionary[] = $_er;
    return $this->dictionary;
  }

  /**
   * Добавления события ошибки для вывода пользователю
   * @param array $er Масив ошибок @example array(code, message, value)
   * @param string $url Метод в котором возникла ошибка
   * @param array $p Параметры метода с которыми возникли ошибки
   * @return NULL
   */
  function set($er = array(), $url = '', $p = array()) {
    //устанавливет значенния для класса
    foreach ($er as $_er_ => $_er)
      $this->$_er_ = $_er;
    $this->url = $url;
    $this->params = $p;
    //поиск описания ошибки 
    foreach ($this->dictionary as $_er)
      if ($_er[0] == $this->code && str_replace('%FIELD%', strtoupper($this->field), $_er[1]) == $this->message) {
        $this->value = str_replace('%FIELD%', '"' . $this->field . '"', $_er[2]);
        break;
      }
    return NULL;
  }

  /**
   * Вывод значение ошибки
   * @return string
   */
  function getMessage() {
    return $this->message;
  }

  /**
   * Вывод описания ошибки
   * @return string
   */
  function getValue() {
    return $this->value;
  }

  /**
   * Выводит масив ошибки со всеми параметрами
   * @return array
   */
  function get() {
    return array($this->code, $this->message, $this->value);
  }

}

/**
 * WgApiCore
 * Класс хранящий в себе все основные методы для работы основного и дочернего класса
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 2.7
 */
class WgApiCore {

  /**
   * Предпочтительный протокол для использование запросов
   * @var string 
   */
  public $protocol = 'http';

  /**
   * URI игрового сервера на соответствующем кластере, без домена региона
   * @var string 
   */
  public $serverDomain = 'api.worldoftanks.';

  /**
   * версия API
   * @var string 
   */
  public $apiName = 'wot';

  /**
   * Идентификатор приложения работающего с API.
   * Автономные приложения — приложения, которые взаимодействуют с API на уровне «клиент-сервер». Запросы от автономных приложений приходят с различных IP-адресов.
   * Лимит выставляется на количество одновременных запросов с одного IP-адреса , и может составлять от 2 до 4 запросов в секунду.
   * @var string 
   */
  public $apiStandalone = 'demo';

  /**
   * Идентификатор приложения работающего с API.
   * Серверные приложения — все внешние приложения, которые взаимодействуют с API на уровне «сервер-сервер».
   * Лимитируются по количеству запросов от приложения в секунду. В зависимости от кластера лимит может составлять от 10 до 20 запросов в секунду.
   * @var string 
   */
  public $apiServer = 'demo';

  /**
   * Ключ доступа, выписывается методом аутентификации.
   * Для получения персональных данных пользователя необходим access token. Access token выдаётся после аутентификации пользователя по Open ID.
   * Срок действия access token составляет две недели с момента его получения. Для продления срока действия активного access token, используйте метод @see auth/prolongate.
   * @var string 
   */
  public $token = '';

  /**
   * Язык локализации. Допустимые значения: 
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
   * @var string 
   */
  public $language = 'ru';

  /**
   * Список доступных методов и дочерних класов для подгрузки
   * @var array 
   */
  public $load_class = array('account', 'auth', 'clan', 'encyclopedia', 'globalwar', 'ratings', 'tanks');

  /**
   * Присвоение первичных параметров для функционально класса
   * @param array $p Входящие параметры для объявления класса
   */
  function __construct($p = array()) {
    $p = (array) $p;
    foreach ($p as $_p_ => $_p)
      if (!empty($_p) && $_p_ != 'parent' && !in_array($_p_, $this->load_class))
        $this->$_p_ = $_p;
    $this->language((string) @$p['language']);
    $this->region((string) @$p['region']);
    $this->setuser(@$p['user']);
    $this->load();
  }

  /**
   * Выполняет загрузку вторичных классов, при их отсутствии выполняет обновляет данный файл.
   * @return boolean
   */
  function load() {
    //загрузка класса ошибок
    $this->erorr = new WgApiError();
    return true;
  }

  /**
   * Присвоение языка для API Client
   * @param string $l язык клиента
   * @return string
   */
  function language($l = 'ru') {
    //проверка валидности языка
    if (in_array($l, array('cs', 'de', 'en', 'es', 'fr', 'ko', 'pl', 'ru', 'th', 'tr', 'vi', 'zh-cn')))
      $this->language = $l;
    else
      $this->language = 'ru';
    //обновление переменных для дочерних классво
    $this->updatevar();
    return $this->language;
  }

  /**
   * Присвоение региона для API Client
   * @param string $r регион клиента
   * @return string
   */
  function region($r = 'RU') {
    //проверка валидности региона
    if (in_array($r, array('ASIA', 'EU', 'KR', 'NA', 'RU')))
      $this->region = $r;
    else
      $this->region = 'RU';
    $this->server = $this->serverDomain . strtolower($this->region);
    //обновление переменных для дочерних классво
    $this->updatevar();
    return $this->server;
  }

  /**
   * Установка авторизационных данных пользователя
   * @param array $i массив с данными пользователя для аворизации
   * @return boolean
   */
  function setuser($i = array()) {
    $i = (array) @$i;
    if (count($i) == 0)
      return false;
    $this->user = $i;
    $this->token = (string) @$i['token'];
    $this->name = (string) @$i['name'];
    $this->id = (integer) @$i['user_id'];
    $this->expire = (integer) @$i['expire'];
    //обновление переменных для дочерних классво
    $this->updatevar();
    return true;
  }

  /**
   * Выберает подходящий протокол для запроса
   * @param array $pr массив с подходящими протоколами для определенных запросов
   * @param boolean $t флаг используется ли авторизация пользователя
   * @return string
   */
  function protocol($pr = array('http', 'https'), $t = false) {
    $pr = (array) $pr;
    //определение предпочтительного протокола
    $_pr = ($t) ? 'https' : $this->protocol;
    unset($t);
    //при отсутствие вариантов использовать предпочтительный протокол
    if (count($pr) == 0)
      return $_pr;
    //выбор предпочтительного протокола из доступных вариантов
    if (in_array($_pr, $pr))
      return $_pr;
    //выбор доступного варианта
    return array_shift($pr);
  }

  /**
   * Формирование ссылки для создания запроса
   * @param string $pr протокол
   * @param string $b название группы методов
   * @param string $m название метода
   * @return string
   */
  function seturl($pr, $b = '', $m = '') {
    $_p = array($pr . ':/', $this->server, $this->apiName);
    if ($b)
      $_p[] = $b;
    if ($m)
      $_p[] = $m;
    $_p[] = '';
    $this->url = implode('/', $_p);
    return $this->url;
  }

  /**
   * Выполнение запросов API. При возниконовеннии ошибки возращается NULL. Ошибка записывае в класс error.
   * @param type $m название группы методов и метода
   * @param type $p параметры метода
   * @param type $pr протокол
   * @return null|array
   */
  function send($m = '', $p = array(), $pr = array()) {
    //определение токена авторизации
    if (isset($p['access_token']) && empty($p['access_token']))
      $p['access_token'] = (isset($this->token) && !empty($this->token)) ? $this->token : '';
    //удаление токена авторизации при его пустом значении
    if (isset($p['access_token']) && empty($p['access_token']))
      unset($p['access_token']);
    //флаг авторизировано пользователя
    $_wt = (isset($p['access_token']) && !empty($p['access_token']));
    //выбор протокола
    $pr = $this->protocol($pr, $_wt);
    //определение ключа приложенния
    if (isset($p['application_id']) && empty($p['application_id']))
      $p['application_id'] = $_wt ? $this->apiStandalone : $this->apiServer;
    //определение языка вывода
    if (isset($p['language']) && empty($p['language']))
      $p['language'] = $this->language;
    unset($_wt);
    //формированние запроса
    $c = curl_init();
    //проверка будут ли использоватся параметры метода
    if (count($p) > 0) {
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($p));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    //дополнительный параметр протокола
    if ($pr == 'https')
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    //Эмулированние браузерра
    curl_setopt($c, CURLOPT_HTTPHEADER, array(
      "X-Requested-With: XMLHttpRequest",
      "Accept: text/html, */*",
      "User-Agent: Mozilla/3.0 (compatible; easyhttp)",
      "Connection: Keep-Alive",
    ));
    curl_setopt($c, CURLOPT_TIMEOUT, 120);
    curl_setopt($c, CURLOPT_URL, $this->setURL($pr, $m));
    $d = curl_exec($c);
    curl_close($c);
    //перевод данных в массив
    $r = @json_decode((string) @$d, true);
//при ошибки получения массива возвращаем полученные данные
    if (!$r)
      return (string) @$d;
    unset($d);
    //при отсутствие статуса выводим полученный масив
    if (!isset($r['status']))
      return $r;
    //при верном статусе возвращаем данные
    if ($r['status'] == 'ok')
      return $r['data'];
//при ошибки переводим обработчик ошибок
    if (isset($r[$r['status']])) {
      $er = $r[$r['status']];
      //присвоенние ошибки
      $this->erorr->set($er, $m, $p);
      switch ((string) $er['message']) {
        //выполняем запрос без токена
        case 'INVALID_ACCESS_TOKEN':
          unset($p['access_token']);
          return $this->send($m, $p);
          break;
        //выполняем обновление клиента
        case 'METHOD_DISABLED':
        case 'METHOD_NOT_FOUND':
          /**
           * @todo Убрать все элементы вывода
           */
          echo $m . $this->error->getValue();
          $this->update();
          break;
      }
    }
    return NULL;
  }

  /**
   * Валидация входящих параметров для выполняющихся методов
   * @param array $i Входящие параметры
   * @param array $r Обезательные параметры
   * @param array $o Остальные параметры
   * @return boolean
   */
  function validate(&$i, $r = array(), $o = array()) {
    //Присвоение пустых значений для выполненния автоматических полей
    foreach (array('access_token', 'application_id', 'language') as $_f)
      if ((isset($r[$_f]) || isset($o[$_f])) && !isset($i[$_f]))
        $i[$_f] = '';
    //обработка списка полей ответа, которые в результате запроса должны быть записаны через запятую.
    if (isset($i['fields'])) {
      if (is_array($i['fields']))
        $i['fields'] = (string) @implode(',', $i['fields']);
      else
        $i['fields'] = (string) @$i['fields'];
    }
    //удаление лишних полей
    foreach ($i as $_i_ => $_i)
      if (!isset($r[$_f]) && !isset($o[$_f]))
        unset($i[$_i_]);
    //проверка обезательных полей, при ошибки выдает false;
    foreach ($r as $_r_ => $_r)
      if (!isset($i[$_r_])) {
        $this->erorr->set(array('code' => 402, 'field' => $_r_, 'message' => strtoupper($_r_) . '_NOT_SPECIFIED'));
        return false;
      }
    //проверка типов входящих параметров
    foreach (array($r, $o) as $_t)
      foreach ($_t as $_r_ => $_r)
        if (isset($i[$_r_]))
          switch ($_r) {
            case 'string': $i[$_r_] = (string) @$i[$_r_];
              break;
            case 'timestamp/date':
            case 'numeric': $i[$_r_] = (int) @$i[$_r_];
              break;
            case 'float': $i[$_r_] = (float) @$i[$_r_];
              break;
            case 'string, list':
              if (is_array($i[$_r_])) {
                //ограничиваем до 100 элементов
                $i[$_r_] = array_slice($i[$_r_], 0, 100);
                //проверяем тип значенния
                foreach ($i[$_r_] as &$_i)
                  $_i = (string) @$_i;
                //переводим в строку
                $i[$_r_] = (string) @implode(',', $i[$_r_]);
              } else {
                $i[$_r_] = (string) @$i[$_r_];
              }
              break;
            case 'timestamp/date, list':
            case 'numeric, list':
              if (is_array($i[$_r_])) {
                //ограничиваем до 100 элементов
                $i[$_r_] = array_slice($i[$_r_], 0, 100);
                //проверяем тип значенния
                foreach ($i[$_r_] as &$_i)
                  $_i = (int) @$_i;
                //переводим в строку
                $i[$_r_] = (string) @implode(',', $i[$_r_]);
              } else {
                $i[$_r_] = (string) @$i[$_r_];
              }
              break;
            case 'float, list':
              if (is_array($i[$_r_])) {
                //ограничиваем до 100 элементов
                $i[$_r_] = array_slice($i[$_r_], 0, 100);
                //проверяем тип значенния
                foreach ($i[$_r_] as &$_i)
                  $_i = (float) @$_i;
                //переводим в строку
                $i[$_r_] = (string) @implode(',', $i[$_r_]);
              } else {
                $i[$_r_] = (string) @$i[$_r_];
              }
              break;
          }
    return true;
  }

  /**
   * Обновление параметров для дочерних классов
   */
  function updatevar() {
    $_v = (array) @$this;
    //проверка масива наименований методов
    if (count($this->load_class) == 0)
      return false;
    foreach ($this->load_class as $c) {
      $lc = "wgapi_{$this->apiName}_{$c}";
      //проверка существования дочернего метода
      if (class_exists($lc))
        if (isset($this->$c))
        //присвоенние дочернему классу переменных
          foreach ($_v as $__v_ => $__v)
            if (!empty($__v) && $__v_ != 'parent' && !in_array($__v_, $this->load_class))
              $this->$c->$__v_ = $__v;
    }
  }

  /**
   * Обновление файла API Client
   * @return boolean
   */
  function update() {
    $_fe = array('list');
    //Получаем базу знанний
    $kb = $this->send();
    if (!$kb)
      return false;
    //Получаем содержимое файла
    $fd = file_get_contents(__FILE__);
    //Определяем масив дочерних объектов
    $_с_ = array_keys($kb['category_names']);
    sort($_с_);
    $fd = preg_replace("/load_class \= array\((.*)/i", "load_" . "class = array('" . implode("', '", $_с_) . "');", $fd);
    //Удаляем старый код
    $_rp = strpos($fd, "// " . "After this line rewrite code");
    if ($_rp > 0)
      $fd = substr($fd, 0, $_rp);
    //Добавляем линюю удаления
    $fd .= "// " . "After this line rewrite code" . "\n\n\n";
    $fd .= "/**\n * {$kb['long_name']} \n */\n";
    $m = array();
    //формируем код методов
    foreach ($kb['methods'] as $_m) {
      //функционал
      $f = "";
      $url = $_m["url"];
      $_fn = explode('/', $url);
      //временное значенние входящих полей
      $_id = array();
      $_iv = array();
      foreach ($_m['input_form_info']['fields'] as $fields) {
        //масив полейи их типов
        $_iv[(($fields['required']) ? 'required' : 'other')][$fields['name']] = $fields['doc_type'];
        //масив полей для документации
        $_id[(($fields['required']) ? 'required' : 'other')][] = $fields;
      }
      unset($_m['input_form_info']);
      //создание строки специфических ошибок
      foreach ($_m['errors'] as &$_er) {
        $_er[0] = (int) $_er[0];
        $_er[1] = (string) $_er[1];
        $_er[2] = (string) $_er[2];
        $_er = "array({$_er[0]}, \"{$_er[1]}\", \"{$_er[2]}\")";
      }
      if (count($_m['errors']) > 0)
        $f .= "    \$this->erorr->add(array(" . implode(", ", $_m['errors']) . "));\n";
      unset($_m['errors']);
      //создание строки доступных протоколов
      $_pr = "array()";
      if (isset($_m['allowed_protocols']))
        if (is_array($_m['allowed_protocols']))
          $_pr = "array('" . implode("', '", $_m['allowed_protocols']) . "')";
      unset($_m['allowed_protocols'], $_m['allowed_http_methods']);
      //создание полей для проверки валидации
      foreach ($_iv as &$type)
        foreach ($type as $_cn_ => &$field) {
          $field = "'{$_cn_}' => '{$field}'";
        }
      $f .= "    if (!\$this->validate(\$i, array(" . implode(", ", (array) @$_iv['required']) . "), array(" . implode(", ", (array) @$_iv['other']) . "))) return NULL;\n";
      unset($_iv);
      //документация
      $d = "";
      $d .= "{$_m['name']}\n";
      $d .= "{$_m['description']}\n";
      $d .= "@category {$_m['category_name']}\n";
      $d .= "@link {$_m['url']}\n";
      if ($_m['deprecated']) {
        $d .= "@todo deprecated\n";
      }
      $d .= "@param array \$input\n";
      unset($_m['name'], $_m['url'], $_m['description'], $_m['category_name'], $_m['deprecated']);
      //описание входящих полей
      foreach ($_id as $__id)
        foreach ($__id as $___id) {
          $___id['doc_type'] = str_replace(array(', ', 'numeric', 'list'), array('|', 'integer', 'array'), $___id['doc_type']);
          $d .= "@param {$___id['doc_type']} \$input['{$___id['name']}'] {$___id['help_text']}\n";
          if ($___id['deprecated'])
            $d .= "@todo deprecated \$input['{$___id['name']}'] {$___id['deprecated_text']}\n";
        }

      $d .= "@return array\n";
      //$d .= json_encode($_m) . "\n";
      //строчные замены в файле
      $d = "\n  /**\n   * " . str_replace(array("\n\n", "&mdash;", "\n", "  "), array("\n", "-", "\n     * ", " "), trim($d)) . "\n   */\n";
      $f .= "    \$o = \$this->send('{$url}', \$i, {$_pr});\n";
      $f .= "    return \$o;";
      //формирование функций
      $_pre = in_array($_fn[1], $_fe) ? 's' : '';
      $f = "  function {$_fn[1]}{$_pre} (\$i = array()) {\n{$f}\n  }\n";
      $m[$_fn[0]] = (@$m[$_fn[0]] ? $m[$_fn[0]] : '') . $d . $f;
      unset($f, $d);
    }
    //формирование классов
    foreach ($kb['category_names'] as $_cn_ => $_cn) {
      $fd .= "/**\n * {$_cn} \n */\n";
      if (isset($m[$_cn_]))
        $fd .= "class wgapi_{$this->apiName}_{$_cn_} extends WgApiCore {\n{$m[$_cn_]}\n}\n\n";
    }
    //перезапись файлов
    /**
     * @todo Убрать заглушку, что бы перезаписывался сам файл
     */
    if ($f = @fopen(__FILE__ . '.php', "w")) {
      fwrite($f, $fd);
      fclose($f);
    }
    /**
     * @todo Убрать все элементы вывода
     */
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
   * Не возвращает весь список кланов.
   * @category Кланы
   * @link clan/list
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @param string $input['order_by'] Вид сортировки.. Допустимые значения: 
   * * "name" - по имени клана 
   * * "-name" - по имени клана в обратном порядке 
   * * "members_count" - по численности клана 
   * * "-members_count" - по численности клана в обратном порядке 
   * * "created_at" - по дате создания клана 
   * * "-created_at" - по дате создания клана в обратном порядке 
   * * "abbreviation" - по тегу клана 
   * * "-abbreviation" - по тегу клана в обратном порядке 
   * @param integer $input['page_no'] Номер страницы выдачи
   * @return array
   */
  function lists ($i = array()) {
    $this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'search' => 'string', 'limit' => 'numeric', 'order_by' => 'string', 'page_no' => 'numeric'))) return NULL;
    $o = $this->send('clan/list', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Данные клана
   * Возвращает информацию о клане.
   * @category Кланы
   * @link clan/info
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function info ($i = array()) {
    $this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('clan/info', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список боёв клана
   * Возвращает список боев клана.
   * @category Кланы
   * @link clan/battles
   * @todo deprecated
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['map_id'] Идентификатор карты
   * @return array
   */
  function battles ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'map_id' => 'numeric'))) return NULL;
    $o = $this->send('clan/battles', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Топ кланов по очкам победы
   * Возвращает часть списка кланов, отсортированного по рейтингу
   * Не возвращает весь список кланов, только первые 100.
   * @category Кланы
   * @link clan/top
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * * "current_season" - Текущее событие (используется по умолчанию)
   * * "current_step" - Текущий этап 
   * @return array
   */
  function top ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'string', 'time' => 'string'))) return NULL;
    $o = $this->send('clan/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Провинции клана
   * Возвращает списка провинций клана
   * @category Кланы
   * @link clan/provinces
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function provinces ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('clan/provinces', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Очки победы клана
   * Количество очков победы у клана
   * @category Кланы
   * @link clan/victorypoints
   * @todo deprecated
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function victorypoints ($i = array()) {
    $this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **clan_id** превышен ( >100 )")));
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('clan/victorypoints', $i, array('http', 'https'));
    return $o;
  }

  /**
   * История начисления очков победы клана
   * История начислений очков победы для клана
   * @category Кланы
   * @link clan/victorypointshistory
   * @todo deprecated
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['map_id'] Идентификатор карты
   * @param timestamp/date $input['since'] Начало периода
   * @param timestamp/date $input['until'] Конец периода
   * @param integer $input['offset'] Сдвиг относительно первого результата
   * @param integer $input['limit'] Кол-во результатов (от 20 до 100)
   * @return array
   */
  function victorypointshistory ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'numeric', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('clan/victorypointshistory', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация о члене клана
   * @category Кланы
   * @link clan/membersinfo
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['member_id'] Идентификатор члена клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function membersinfo ($i = array()) {
    $this->erorr->add(array(array(407, "MEMBER_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **member_id** превышен ( >100 )")));
    if (!$this->validate($i, array('application_id' => 'string', 'member_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('clan/membersinfo', $i, array('http', 'https'));
    return $o;
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
  function types ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('ratings/types', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Рейтинги игроков
   * Возвращает рейтинги игроков по заданным идентификаторам.
   * @category Рейтинги игроков
   * @link ratings/accounts
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификаторы аккаунтов игроков
   * @param string $input['type'] Тип рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function accounts ($i = array()) {
    $this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date'))) return NULL;
    $o = $this->send('ratings/accounts', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Соседи игрока по рейтингу
   * Возвращает список соседей по заданному рейтингу.
   * @category Рейтинги игроков
   * @link ratings/neighbors
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['type'] Тип рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['rank_field'] Категория рейтинга
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['limit'] Лимит количества соседей
   * @return array
   */
  function neighbors ($i = array()) {
    $this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "**account_id** не указан"), array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('ratings/neighbors', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Топ игроков
   * Возвращает топ игроков по заданному параметру.
   * @category Рейтинги игроков
   * @link ratings/top
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Тип рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['rank_field'] Категория рейтинга
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['limit'] Лимит количества игроков в топе
   * @return array
   */
  function top ($i = array()) {
    $this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "**type** не указан"), array(407, "INVALID_TYPE", "Указан неверный **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "**rank_field** не указан"), array(407, "INVALID_RANK_FIELD", "Указан неверный **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных за указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('ratings/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Даты доступных рейтингов
   * Возвращает даты, за которые есть рейтинговые данные
   * @category Рейтинги игроков
   * @link ratings/dates
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Тип рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * @return array
   */
  function dates ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'account_id' => 'numeric, list'))) return NULL;
    $o = $this->send('ratings/dates', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Аккаунт 
 */
class wgapi_wot_account extends WgApiCore {

  /**
   * Список игроков
   * Возвращает часть списка игроков, отсортированного по имени и отфильтрованного по его начальной части.
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
   * * "startswith" - Поиск по начальной части имени игрока. Минимальная длина 3 символа, поиск без учета регистра (используется по умолчанию)
   * * "exact" - Поиск по строгому соответствию имени игрока. Минимальная длина строки поиска 1 символ, поиск без учета регистра 
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array
   */
  function lists ($i = array()) {
    $this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не указан обязательный параметр **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум 3 символа")));
    if (!$this->validate($i, array('application_id' => 'string', 'search' => 'string'), array('language' => 'string', 'fields' => 'string', 'type' => 'string', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('account/list', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Данные игрока
   * Возвращает информацию об игроке.
   * @category Аккаунт
   * @link account/info
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function info ($i = array()) {
    $this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('account/info', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Танки игрока
   * Возвращает детальную информацию о танках игрока.
   * @category Аккаунт
   * @link account/tanks
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['tank_id'] Идентификатор танка игрока
   * @return array
   */
  function tanks ($i = array()) {
    $this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Лимит переданных идентификаторов **account_id** превышен ( >100 )")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list'))) return NULL;
    $o = $this->send('account/tanks', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения игрока
   * Возвращает достижение по игрокам.
   * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях](/developers/api_reference/wot/encyclopedia/achievements)):
   * * от 1 до 4 для знака классности и этапных достижений (type: "class")
   * * максимальное значение серийных достижений (type: "series")
   * * количество заработанных наград из секций: Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и тп. (type: "repeatable, single, custom")
   * @category Аккаунт
   * @link account/achievements
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('account/achievements', $i, array('http', 'https'));
    return $o;
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
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @param integer $input['page_no'] Номер страницы выдачи
   * @return array
   */
  function clans ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'limit' => 'numeric', 'page_no' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/clans', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация по актуальному количеству очков славы игрока на ГК
   * Возвращает достижения игрока на карте.
   * @category Мировая война
   * @link globalwar/famepoints
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор карты
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function famepoints ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/famepoints', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список доступных карт на ГК
   * Возвращает список карт на Глобальной карте.
   * @category Мировая война
   * @link globalwar/maps
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function maps ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/maps', $i, array('http', 'https'));
    return $o;
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
   * @param string|array $input['province_id'] Идентификатор провинции
   * @return array
   */
  function provinces ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'province_id' => 'string, list'))) return NULL;
    $o = $this->send('globalwar/provinces', $i, array('http', 'https'));
    return $o;
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
   * * "wins_count" - Количество побед 
   * * "combats_count" - Количество боев 
   * * "provinces_count" - Количество провинций 
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function top ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'order_by' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/top', $i, array('http', 'https'));
    return $o;
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
  function tournaments ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'province_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/tournaments', $i, array('http', 'https'));
    return $o;
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
   * @param integer $input['page_no'] Номер страницы выдачи
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array
   */
  function famepointshistory ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'page_no' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/famepointshistory', $i, array('https'));
    return $o;
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
   * @param integer $input['page_no'] Номер страницы выдачи
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array
   */
  function alleyoffame ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'page_no' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/alleyoffame', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список боёв клана
   * Возвращает список боев клана.
   * @category Мировая война
   * @link globalwar/battles
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор карты
   * @param integer|array $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function battles ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('globalwar/battles', $i, array('http', 'https'));
    return $o;
  }

  /**
   * История начисления очков победы клана
   * История начислений очков победы для клана
   * @category Мировая война
   * @link globalwar/victorypointshistory
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор карты
   * @param integer $input['clan_id'] Идентификатор клана
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['offset'] Сдвиг относительно первого результата
   * @param integer $input['limit'] Кол-во результатов (от 20 до 100)
   * @return array
   */
  function victorypointshistory ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/victorypointshistory', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Аутентификация 
 */
class wgapi_wot_auth extends WgApiCore {

  /**
   * Вход по OpenID
   * Осуществляет аутентификацию пользователя с использованием WarGaming.Net ID (OpenID).
   * На странице аутентификации пользователю необходимо ввести свой логин и пароль.
   * Информация о статусе авторизации будет отправлена по адресу указаному в параметре **redirect_uri**.
   * Параметры к **redirect_uri** при успешной аутентификации:
   * * **status** - ok, аутентификация пройдена
   * * **access_token** - Ключ доступа, передается во все методы требующие аутентификацию.
   * * **expires_at** - Время окончания срока действия **access_token**.
   * * **account_id** - Идентификатор залогиненного аккаунта.
   * * **nickname** - Имя залогиненного аккаунта.
   * Параметры к **redirect_uri** если произошла ошибка:
   * * **status** - error, ошибка аутентификации.
   * * **code** - Код ошибки.
   * * **message** - Сообщение с информацией об ошибке.
   * @category Аутентификация
   * @link auth/login
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['expires_at'] Конечный период в UTC до которого должен работать **access_token**. Можно также указать дельту в секундах, сколько должен действовать **access_token**.
   * Конечный период и дельта не должны превышать двух недель от текущего времени.
   * @param string $input['redirect_uri'] 
   * URL на который будет переброшен пользователь после того как он пройдет аутентификацию.
   * По умолчанию: [{API_HOST}/blank/](https://{API_HOST}/blank/)
   * @param string $input['display'] Внешний вид формы для мобильных. Допустимые значения: page, popup
   * @param integer $input['nofollow'] При передаче параметра nofollow=1 URL будет возвращен в теле ответа вместо редиректа
   * @return array
   */
  function login ($i = array()) {
    $this->erorr->add(array(array(401, "AUTH_CANCEL", "Пользователь отменил авторизацию для приложения"), array(403, "AUTH_EXPIRED", "Превышено время ожидания подтверждения авторизации пользователем"), array(410, "AUTH_ERROR", "Ошибка аутентификации")));
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'expires_at' => 'numeric', 'redirect_uri' => 'string', 'display' => 'string', 'nofollow' => 'numeric'))) return NULL;
    $o = $this->send('auth/login', $i, array('https'));
    return $o;
  }

  /**
   * Продление access_token
   * Выдает новый **access_token** на основе действующего.
   * Используется для тех случаев когда пользователь все еще пользуется приложением, а срок действия его **access_token** уже подходит к концу.
   * @category Аутентификация
   * @link auth/prolongate
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer $input['expires_at'] Конечный период в UTC до которого должен работать **access_token**. Можно также указать дельту в секундах, сколько должен действовать **access_token**.
   * Конечный период и дельта не должны превышать двух недель от текущего времени.
   * @return array
   */
  function prolongate ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'expires_at' => 'numeric'))) return NULL;
    $o = $this->send('auth/prolongate', $i, array('https'));
    return $o;
  }

  /**
   * Выход (забыть аутентификацию)
   * Удаляет данные авторизации пользователя на доступ к его персональным данным.
   * После вызова данного метода перестанет действовать **access_token**.
   * @category Аутентификация
   * @link auth/logout
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function logout ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string'))) return NULL;
    $o = $this->send('auth/logout', $i, array('https'));
    return $o;
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
  function tanks ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tanks', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация о технике
   * Возвращает информацию о танке из танкопедии.
   * @category Энциклопедия
   * @link encyclopedia/tankinfo
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['tank_id'] Идентификатор танка
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function tankinfo ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'tank_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankinfo', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список двигателей танков
   * Метод возвращает список двигателей танков.
   * @category Энциклопедия
   * @link encyclopedia/tankengines
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['module_id'] Идентификатор модуля
   * @param string $input['nation'] Нация. Допустимые значения: 
   * * "ussr" - СССР 
   * * "germany" - Германия 
   * * "usa" - США 
   * * "france" - Франция 
   * * "uk" - Великобритания 
   * * "china" - Китай 
   * * "japan" - Япония 
   * @return array
   */
  function tankengines ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankengines', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список башен танков
   * Метод возвращает список башен танков.
   * @category Энциклопедия
   * @link encyclopedia/tankturrets
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['module_id'] Идентификатор модуля
   * @param string $input['nation'] Нация. Допустимые значения: 
   * * "ussr" - СССР 
   * * "germany" - Германия 
   * * "usa" - США 
   * * "france" - Франция 
   * * "uk" - Великобритания 
   * * "china" - Китай 
   * * "japan" - Япония 
   * @return array
   */
  function tankturrets ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankturrets', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список радиостанций танков
   * Метод возвращает список радиостанций танков.
   * @category Энциклопедия
   * @link encyclopedia/tankradios
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['module_id'] Идентификатор модуля
   * @param string $input['nation'] Нация. Допустимые значения: 
   * * "ussr" - СССР 
   * * "germany" - Германия 
   * * "usa" - США 
   * * "france" - Франция 
   * * "uk" - Великобритания 
   * * "china" - Китай 
   * * "japan" - Япония 
   * @return array
   */
  function tankradios ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankradios', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список ходовых танков
   * Метод возвращает список ходовых танков.
   * @category Энциклопедия
   * @link encyclopedia/tankchassis
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['module_id'] Идентификатор модуля
   * @param string $input['nation'] Нация. Допустимые значения: 
   * * "ussr" - СССР 
   * * "germany" - Германия 
   * * "usa" - США 
   * * "france" - Франция 
   * * "uk" - Великобритания 
   * * "china" - Китай 
   * * "japan" - Япония 
   * @return array
   */
  function tankchassis ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankchassis', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Список орудий танков
   * Метод возвращает список орудий танков.
   * Возможны изменения логики работы метода и значений некоторых полей в зависимости от переданных дополнительных параметров.
   * Поля, которые могут измениться:
   * * **damage**
   * * **piercing_power**
   * * **rate**
   * * **price_credit**
   * * **price_gold**
   * Влияние дополнительных входных параметров:
   * * передан корректный **turret_id** — происходит фильтрация по принадлежности орудий к башне и изменение вышеуказанных характеристик в зависимости от башни
   * * передан корректный **turret_id** и **module_id** — возвращает информацию по каждому модулю с измененными вышеуказанными характеристиками в зависимости от башни или null, если модуль и башня не совместимы
   * * передан корректный **tank_id** - если тип танка соответствует одному из AT-SPG, SPG, mediumTank — будет проведена фильтрация по по принадлежности орудий к танку и изменены вышеуказанные характеристики в зависимости от танка. В противном случае будет возвращена ошибка о необходимости указания **turret_id**. Если переданы еще и **module_id**, то будет возвращена информация о каждом модуле с измененными вышеуказанными характеристиками в зависимости от танка или null, если модуль с танком не совместим
   * * переданы совместимые **turret_id** и **tank_id** — происходит фильтрация по принадлежности орудий к башне и танку, и изменение вышеуказанных характеристик в зависимости от башни
   * @category Энциклопедия
   * @link encyclopedia/tankguns
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['module_id'] Идентификатор модуля
   * @param string $input['nation'] Нация. Допустимые значения: 
   * * "ussr" - СССР 
   * * "germany" - Германия 
   * * "usa" - США 
   * * "france" - Франция 
   * * "uk" - Великобритания 
   * * "china" - Китай 
   * * "japan" - Япония 
   * @param integer $input['turret_id'] Идентификатор совместимой башни
   * @param integer $input['tank_id'] Идентификатор совместимого танка
   * @return array
   */
  function tankguns ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string', 'turret_id' => 'numeric', 'tank_id' => 'numeric'))) return NULL;
    $o = $this->send('encyclopedia/tankguns', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения
   * @category Энциклопедия
   * @link encyclopedia/achievements
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/achievements', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация о Танкопедии
   * Метод возвращает информацию о Танкопедии.
   * @category Энциклопедия
   * @link encyclopedia/info
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
  function info ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/info', $i, array('http', 'https'));
    return $o;
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
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['tank_id'] Идентификатор танка игрока
   * @param string $input['in_garage'] Фильтрация по присутствию танка в гараже. Если параметр не указан, возвращаются все танки.Параметр обрабатывается только при наличии валидного access_token для указанного account_id. Допустимые значения: 
   * * "1" - Возвращать только танки из гаража 
   * * "0" - Возвращать танки, которых уже нет в гараже 
   * @return array
   */
  function stats ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) return NULL;
    $o = $this->send('tanks/stats', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения по танкам игрока
   * Возвращает список достижений по всем танкам игрока.
   * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях]( /developers/api_reference/wot/encyclopedia/achievements )):
   * * степень от 1 до 4 для знака классности и этапных достижений (type: "class")
   * * максимальное значение серийных достижений (type: "series")
   * * количество заработанных наград из секций: Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и тп. (type: "repeatable, single, custom")
   * @category Танки игрока
   * @link tanks/achievements
   * @param array $input
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['language'] Язык локализации. Допустимые значения: 
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
   * @param integer|array $input['tank_id'] Идентификатор танка игрока
   * @param string $input['in_garage'] Фильтрация по присутствию танка в гараже. Если параметр не указан, возвращаются все танки.Параметр обрабатывается только при наличии валидного access_token для указанного account_id. Допустимые значения: 
   * * "1" - Возвращать только танки из гаража 
   * * "0" - Возвращать танки, которых уже нет в гараже 
   * @return array
   */
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) return NULL;
    $o = $this->send('tanks/achievements', $i, array('http', 'https'));
    return $o;
  }

}

