<?php

/**
 * WgApiCore
 * Класс хранящий в себе все основные методы для работы основного и дочернего класса
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 3.4
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
  public $load_class = array();

  /**
   * Присвоение первичных параметров для функционального класса
   * @param array $p Входящие параметры для объявления класса
   */
  function __construct($p = array()) {
    $p = (array) $p;
    foreach ($p as $_p_ => $_p)
      if (!empty($_p) && !in_array($_p_, $this->load_class))
        $this->$_p_ = $_p;
    $this->language((string) @$p['language']);
    $this->region((string) @$p['region']);
    $this->setuser(@$p['user']);
    //загрузка класса кэшированния
    $this->cache = new WgApiCache(@$p['cache']);
    $this->load();
  }

  /**
   * Выполняет загрузку вторичных классов, при их отсутствии выполняет обновляет данный файл.
   * @return boolean
   */
  function load() {
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
    $_pr = $this->protocol($pr, $_wt);
    //определение ключа приложенния
    if (isset($p['application_id']) && empty($p['application_id']))
      $p['application_id'] = $_wt ? $this->apiStandalone : $this->apiServer;
    //определение языка вывода
    if (isset($p['language']) && empty($p['language']))
      $p['language'] = $this->language;
    unset($_wt);
    $_u = $this->setURL($_pr, $m);
    $h = md5(md5($_u) . md5(json_encode($p)));
    $r = $this->cache->get($h);
    if ($r)
      return $r;
    //формированние запроса
    $c = curl_init();
    //проверка будут ли использоватся параметры метода
    if (count($p) > 0) {
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($p));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    //дополнительный параметр протокола
    if ($_pr == 'https')
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    //Эмулированние браузерра
    curl_setopt($c, CURLOPT_HTTPHEADER, array(
      "X-Requested-With: XMLHttpRequest",
      "Accept: text/html, */*",
      "User-Agent: Mozilla/3.0 (compatible; easyhttp)",
      "Connection: Keep-Alive",
    ));
    curl_setopt($c, CURLOPT_TIMEOUT, 120);
    curl_setopt($c, CURLOPT_URL, $_u);
    $d = curl_exec($c);
    curl_close($c);
    //перевод данных в массив
    $r = @json_decode((string) @$d, true);
//при ошибки получения массива возвращаем полученные данные
    if (!$r) {
      $this->cache->set($h, $d);
      return (string) @$d;
    }
    unset($d);
    //при отсутствие статуса выводим полученный масив
    if (!isset($r['status'])) {
      $this->cache->set($h, $r);
      return $r;
    }
    //при верном статусе возвращаем данные
    if ($r['status'] == 'ok') {
      $this->cache->set($h, $r['data']);
      return $r['data'];
    }
//при ошибки переводим обработчик ошибок
    if (isset($r[$r['status']])) {
      $er = $r[$r['status']];
      //присвоенние ошибки
      $this->erorr->set($er, $m, $p);
      switch ((string) $er['message']) {
        //выполняем запрос без токена
        case 'INVALID_ACCESS_TOKEN':
          unset($p['access_token']);
          return $this->send($m, $p, $pr);
          break;
        //выполняем обновление клиента
        case 'METHOD_DISABLED':
        case 'METHOD_NOT_FOUND':
          $this->update();
          break;
      }
    }
    return NULL;
  }

  /**
   * Перенаправление на запрос API.
   * @param string $pr протокол
   * @param string $b название группы методов
   * @param string $m название метода
   * @param string $mr метод перенаправление
   * @param integer $c код перенаправления
   */
  function redirect($m = '', $p = array(), $pr = array(), $mr = 'location', $c = 302) {
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
    $uri = $this->setURL($pr, $m) . "?" . http_build_query($p);
    //выбор метода перенаправления
    switch ($mr) {
      case 'refresh' : header("Refresh:0;url=" . $uri);
        break;
      case 'return' : return $uri;
        break;
      default : header("Location: " . $uri, TRUE, $c);
        break;
    }
    exit;
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
      if (!isset($r[$_i_]) && !isset($o[$_i_]))
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
            if (!empty($__v) && !in_array($__v_, $this->load_class))
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
      if ($_fn[1] == "login")
        $f .= "    \$mr = isset(\$i['return']) ? 'return' : 'location';\n";
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
      foreach ($_id as $__id) {
        $d .= str_repeat("-", 40) . "\n";
        foreach ($__id as $___id) {
          $___id['doc_type'] = str_replace(array(', ', 'numeric', 'list'), array('|', 'integer', 'array'), $___id['doc_type']);
          $d .= "@param {$___id['doc_type']} \$input['{$___id['name']}'] {$___id['help_text']}\n";
          if ($___id['deprecated'])
            $d .= "@todo deprecated \$input['{$___id['name']}'] {$___id['deprecated_text']}\n";
        }
      }

      $d .= "@return array|NULL При возникновенние ошибки выдает NULL.\n";
      //$d .= json_encode($_m) . "\n";
      //строчные замены в файле
      $d = "\n  /**\n   * " . str_replace(array("\n\n", "&mdash;", "\n", "  "), array("\n", "-", "\n     * ", " "), trim($d)) . "\n   */\n";
      if ($_fn[1] == "login")
        $f .= "    if (!isset(\$i['redirect_uri']) || empty(\$i['redirect_uri'])) \$i['redirect_uri'] = \$_SERVER['REQUEST_SCHEME'] . '://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'];\n    \$n = get_called_class();\n    if (preg_match('/\?/i', \$i['redirect_uri'])) \$i['redirect_uri'] .= '&' . \$n . '=' . __FUNCTION__;\n    else \$i['redirect_uri'] .= '?' . \$n . '=' . __FUNCTION__;\n    \$o = \$_REQUEST;\n    if (isset(\$o[\$n]) && \$o[\$n] == 'login') {\n      unset(\$o[\$n]);\n      //при отсутствие статуса выводим полученный масив\n      if (!isset(\$o['status'])) return \$o;\n      //при верном статусе возвращаем данные\n      if (\$o['status'] == 'ok') { unset(\$o['status']); return \$o;}\n      //при ошибки переводим обработчик ошибок\n      \$er = \$o;\n      //присвоенние ошибки\n      \$this->erorr->set(\$er, '{$url}', \$i);\n      switch ((string) \$er['message']) {\n        //выполняем обновление клиента\n        case 'METHOD_DISABLED':\n        case 'METHOD_NOT_FOUND': \$this->update(); break;\n      }\n      return NULL;\n    }\n";
      $f .= "    \$o = \$this->" . (($_fn[1] == "login") ? "redirect" : "send") . "('{$url}', \$i, {$_pr}" . (($_fn[1] == "login") ? ", \$mr" : "") . ");\n";
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
      $fmm = (string) @$m[$_cn_];
      $fd .= "class wgapi_{$this->apiName}_{$_cn_} extends WgApiCore {\n{$fmm}\n}\n\n";
    }
    //перезапись файлов
    if ($f = @fopen(__FILE__, "w")) {
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
      if (class_exists($lc))
        $this->$_c = new $lc($this);
      else
        $this->update();
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
 * WgApiCache
 * 
 * Класс, кэширования запросов API
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 1.0
 */
class WgApiCache {

  /**
   * Период кэширования
   * @var integer 
   */
  public $period = 21600;

  /**
   * Адрес сервера кэширования Memcache
   * @var string 
   */
  public $mem_server = '127.0.0.1';

  /**
   * Порт сервера кэширования Memcache
   * @var integer 
   */
  public $mem_port = 11211;

  /**
   * Доступные методы кэширование на сервере
   * @var array 
   */
  public $type_allowed = array();

  /**
   * Присвоение первичных параметров для функционального класса
   * @param array $p
   */
  function __construct($p = array()) {
    //определение дериктории поумолчанию
    $this->file_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WgCache' . DIRECTORY_SEPARATOR;
    $p = (array) $p;
    foreach ($p as $_p_ => $_p)
      if (!empty($_p))
        $this->$_p_ = $_p;
    $this->check();
    $this->type((string) @$p['type']);
  }

  /**
   * Определение метода кэширования, который будет использоватся
   * @param string $t Метод определенный пользователем
   * @return string
   */
  function type($t = 'NULL') {
    //проверка валидности языка
    if (in_array($t, $this->type_allowed))
      $this->type = $t;
    return $this->type;
  }

  /**
   * Проверяем доступные методы кэширования и определяем доступные возможности
   */
  function check() {
    $ta = array();
    //определяем null значенния - нечего не кэшировать
    $this->type = 'null';
    $ta[] = 'null';
    //определяем min значенния - кэширование в переменную
    $this->type = 'min';
    $ta[] = 'min';
    $this->min_cache = array();
    //определяем file значенния - кэширование в файл
    if (file_exists($this->file_dir) && is_dir($this->file_dir)) {
      $this->type = 'file';
      $ta[] = 'file';
    }
    //определяем apc значенния - кэширование в APC
    if (function_exists('apc_store') && function_exists('apc_fetch')) {
      $this->type = 'apc';
      $ta[] = 'apc';
    }
    //определяем mem значенния - кэширование в Memcache
    if (class_exists('Memcache')) {
      $this->mem_c = new Memcache;
      if ($this->mem_c->connect($this->mem_server, $this->mem_port)) {
        $this->type = 'mem';
        $ta[] = 'mem';
      }
    }
    //определяем доступные методы
    $this->type_allowed = $ta;
  }

  /**
   * Первичный метод получения кэша
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get($h) {
    $fn = 'get_' . $this->type;
    $v = $this->$fn($h);
    $v = @json_decode($v, true);
    if ($v)
      return $v;
    return FALSE;
  }

  /**
   * Метод получения кэша Memcache
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get_mem($h) {
    return $this->mem_c->get($h);
  }

  /**
   * Метод получения пустого кэша
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get_null($h) {
    return '';
  }

  /**
   * Метод получения кэша APC
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get_apc($h) {
    return apc_fetch($h);
  }

  /**
   * Метод получения кэша файлово
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get_file($h) {
    if (file_exists($this->file_dir . $h)) {
      if (time() - filemtime($this->file_dir . $h) < $this->period) {
        $v = file_get_contents($this->file_dir . $h);
        if ($v)
          return $v;
      } else
        @unlink($this->file_dir . $h);
    }
    return FALSE;
  }

  /**
   * Метод получения кэша с переменной
   * @param string $h Ключ кэша
   * @return mixed
   */
  function get_min($h) {
    if (isset($this->min_cache[$h]))
      return $this->min_cache[$h];
    return FALSE;
  }

  /**
   * Первичный метод сохраненния кэша
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set($h, $v = '') {
    $fn = 'set_' . $this->type;
    return $this->$fn($h, json_encode($v));
  }

  /**
   * Метод сохраненние кэша Memcache
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set_mem($h, $v = '') {
    return $this->mem_c->set($h, $v, MEMCACHE_COMPRESSED, $this->period);
  }

  /**
   * Метод сохраненние пустого кэша
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set_null($h, $v = '') {
    return FALSE;
  }

  /**
   * Метод сохраненние кэша APC
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set_apc($h, $v = '') {
    return apc_store($h, $v, $this->period);
  }

  /**
   * Метод сохраненние кэша Файл
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set_file($h, $v = '') {
    if ($f = @fopen($this->file_dir . $h, 'w')) {
      fwrite($f, $v);
      fclose($f);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Метод сохраненние кэша в переменную
   * @param string $h Ключ кэша
   * @param mixed $v Значение кэша
   * @return mixed
   */
  function set_min($h, $v = '') {
    $this->min_cache[$h] = $v;
    return TRUE;
  }

}
