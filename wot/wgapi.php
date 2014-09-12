<?php

/**
 * WgApiCore
 * Класс хранящий в себе все основные методы для работы основного и дочернего класса
 * 
 * @author Serg Auer <auerserg@gmail.com>
 * @version 3.5
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
  public $load_class = array('account', 'auth', 'clan', 'clanratings', 'encyclopedia', 'globalwar', 'ratings', 'tanks');

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
// After this line rewrite code


/**
 * World of Tanks 
 */
/**
 * Кланы 
 */
class wgapi_wot_clan extends WgApiCore {

  /**
   * Кланы
   * Метод возвращает часть списка кланов, отфильтрованную по первым символам имени или тега клана. Список отсортирован по имени (по умолчанию) или по дате создания, тегу, численности клана.
   * @category Кланы
   * @link clan/list
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @param string $input['search'] Первые символы названия или тега клана, по которым осуществляется поиск.
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @param string $input['order_by'] Вид сортировки. Допустимые значения: 
   * * "name" - по имени клана 
   * * "-name" - по имени клана в обратном порядке 
   * * "members_count" - по численности клана 
   * * "-members_count" - по численности клана в обратном порядке 
   * * "created_at" - по дате создания клана 
   * * "-created_at" - по дате создания клана в обратном порядке 
   * * "abbreviation" - по тегу клана 
   * * "-abbreviation" - по тегу клана в обратном порядке 
   * @param integer $input['page_no'] Номер страницы результатов
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function lists ($i = array()) {
    $this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не задано значение параметра **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум: 3 символа.")));
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'search' => 'string', 'limit' => 'numeric', 'order_by' => 'string', 'page_no' => 'numeric'))) return NULL;
    $o = $this->send('clan/list', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Данные клана
   * Метод возвращает информацию о клане.
   * @category Кланы
   * @link clan/info
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function info ($i = array()) {
    $this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Превышено максимальное количество переданных идентификаторов **clan_id**. Максимум: 100.")));
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('clan/info', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Бои клана
   * Метод возвращает список боёв клана.
   * @category Кланы
   * @link clan/battles
   * @todo deprecated
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @param integer $input['map_id'] Идентификатор Глобальной карты
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function battles ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'map_id' => 'numeric'))) return NULL;
    $o = $this->send('clan/battles', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Лучшие кланы по очкам победы
   * Метод возвращает список первых 100 кланов, отсортированных по рейтингу.
   * @category Кланы
   * @link clan/top
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param string $input['time'] Временной промежуток. Допустимые значения: 
   * * "current_season" - Текущее событие (используется по умолчанию)
   * * "current_step" - Текущий этап 
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function top ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'string', 'time' => 'string'))) return NULL;
    $o = $this->send('clan/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Провинции клана
   * Метод возвращает список провинций клана.
   * @category Кланы
   * @link clan/provinces
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function provinces ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('clan/provinces', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Очки победы клана
   * Метод возвращает количество очков победы клана.
   * @category Кланы
   * @link clan/victorypoints
   * @todo deprecated
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function victorypoints ($i = array()) {
    $this->erorr->add(array(array(407, "CLAN_ID_LIST_LIMIT_EXCEEDED", "Превышено максимальное количество переданных идентификаторов **clan_id**. Максимум: 100.")));
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('clan/victorypoints', $i, array('http', 'https'));
    return $o;
  }

  /**
   * История начисления очков победы клана
   * Метод возвращает историю начислений очков победы клана.
   * @category Кланы
   * @link clan/victorypointshistory
   * @todo deprecated
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @param integer $input['map_id'] Идентификатор Глобальной карты
   * @param timestamp/date $input['since'] Начало этапа
   * @param timestamp/date $input['until'] Конец этапа
   * @param integer $input['offset'] Сдвиг относительно первого результата
   * @param integer $input['limit'] Количество результатов (от 20 до 100)
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function victorypointshistory ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'clan_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'map_id' => 'numeric', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'offset' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('clan/victorypointshistory', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Участник клана
   * @category Кланы
   * @link clan/membersinfo
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['member_id'] Идентификатор участника клана
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function membersinfo ($i = array()) {
    $this->erorr->add(array(array(407, "MEMBER_ID_LIST_LIMIT_EXCEEDED", "Превышено максимальное количество переданных идентификаторов **member_id**. Максимум: 100.")));
    if (!$this->validate($i, array('application_id' => 'string', 'member_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('clan/membersinfo', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Рейтинги игрока 
 */
class wgapi_wot_ratings extends WgApiCore {

  /**
   * Типы рейтингов
   * Метод возвращает словарь, содержащий периоды формирования рейтингов и информацию о рейтингах.
   * @category Рейтинги игрока
   * @link ratings/types
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function types ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('ratings/types', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Рейтинги игрока
   * Метод возвращает рейтинги игроков по заданным идентификаторам.
   * @category Рейтинги игрока
   * @link ratings/accounts
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param integer|array $input['account_id'] Идентификаторы аккаунтов игроков
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function accounts ($i = array()) {
    $this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "Не задано значение параметра **account_id**"), array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date'))) return NULL;
    $o = $this->send('ratings/accounts', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Соседние позиции в рейтинге
   * Метод возвращает список соседних позиций в заданном рейтинге.
   * @category Рейтинги игрока
   * @link ratings/neighbors
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * @param string $input['rank_field'] Категория рейтинга
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @param integer $input['limit'] Максимальное количество соседних позиций
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function neighbors ($i = array()) {
    $this->erorr->add(array(array(402, "ACCOUNT_ID_NOT_SPECIFIED", "Не задано значение параметра **account_id**"), array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "Не задано значение параметра **rank_field**"), array(407, "INVALID_RANK_FIELD", "Неверное значение **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'account_id' => 'numeric', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('ratings/neighbors', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Лучшие игроки
   * Метод возвращает список лучших игроков по заданному параметру.
   * @category Рейтинги игрока
   * @link ratings/top
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['rank_field'] Категория рейтинга
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @param integer $input['limit'] Максимальное количество игроков в списке
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function top ($i = array()) {
    $this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "Не задано значение параметра **rank_field**"), array(407, "INVALID_RANK_FIELD", "Неверное значение **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('ratings/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Даты c доступными рейтингами
   * Метод возвращает даты, за которые есть рейтинговые данные.
   * @category Рейтинги игрока
   * @link ratings/dates
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function dates ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'account_id' => 'numeric, list'))) return NULL;
    $o = $this->send('ratings/dates', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Аккаунты 
 */
class wgapi_wot_account extends WgApiCore {

  /**
   * Список игроков
   * Метод возвращает часть списка игроков, отфильтрованную по первым символам имени и отсортированную по алфавиту.
   * @category Аккаунты
   * @link account/list
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['search'] 
   *     Строка поиска по имени игрока. Вид поиска и минимальная длина строки поиска зависят от параметра type.
   *     Максимальная длина: 24 символа.
   *   
   * ----------------------------------------
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
   * @param string $input['type'] Тип поиска. Определяет минимальную длину строки поиска и вид поиска. По умолчанию используется значение **startswith**. Допустимые значения: 
   * * "startswith" - Поиск по первым символам имени игрока. Минимальная длина: 3 символа без учёта регистра. (используется по умолчанию)
   * * "exact" - Поиск по строгому соответствию имени игрока. Минимальная длина: 1 символ без учёта регистра. 
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function lists ($i = array()) {
    $this->erorr->add(array(array(402, "SEARCH_NOT_SPECIFIED", "Не задано значение параметра **search**"), array(407, "NOT_ENOUGH_SEARCH_LENGTH", "Недостаточная длина параметра **search**. Минимум: 3 символа.")));
    if (!$this->validate($i, array('application_id' => 'string', 'search' => 'string'), array('language' => 'string', 'fields' => 'string', 'type' => 'string', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('account/list', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Персональные данные игрока
   * Метод возвращает информацию об игроке.
   * @category Аккаунты
   * @link account/info
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function info ($i = array()) {
    $this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Превышено максимальное количество переданных идентификаторов **account_id**. Максимум: 100.")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('account/info', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Техника игрока
   * Метод возвращает информацию о технике игрока.
   * @category Аккаунты
   * @link account/tanks
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @param integer|array $input['tank_id'] Идентификатор техники игрока
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tanks ($i = array()) {
    $this->erorr->add(array(array(407, "ACCOUNT_ID_LIST_LIMIT_EXCEEDED", "Превышено максимальное количество переданных идентификаторов **account_id**. Максимум: 100.")));
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list'))) return NULL;
    $o = $this->send('account/tanks', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения игрока
   * Метод возвращает информацию о достижениях игроков.
   * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях](/developers/api_reference/wot/encyclopedia/achievements)):
   * * от 1 до 4 для Знаков классности и Этапных достижений (type: "class");
   * * максимальное значение серийных достижений (type: "series");
   * * количество заработанных достижений из секций Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и т.п. (type: "repeatable, single, custom").
   * @category Аккаунты
   * @link account/achievements
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('account/achievements', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Танкопедия 
 */
class wgapi_wot_encyclopedia extends WgApiCore {

  /**
   * Список техники
   * Метод возвращает список всей техники из Танкопедии.
   * @category Танкопедия
   * @link encyclopedia/tanks
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tanks ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tanks', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация о технике
   * Метод возвращает информацию о технике из Танкопедии.
   * @category Танкопедия
   * @link encyclopedia/tankinfo
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer|array $input['tank_id'] Идентификатор техники
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankinfo ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'tank_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankinfo', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Двигатели
   * Метод возвращает список двигателей.
   * @category Танкопедия
   * @link encyclopedia/tankengines
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankengines ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankengines', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Башни
   * Метод возвращает список башен.
   * @category Танкопедия
   * @link encyclopedia/tankturrets
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankturrets ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankturrets', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Радиостанции
   * Метод возвращает список радиостанций.
   * @category Танкопедия
   * @link encyclopedia/tankradios
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankradios ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankradios', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Ходовые
   * Метод возвращает список ходовых.
   * @category Танкопедия
   * @link encyclopedia/tankchassis
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankchassis ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/tankchassis', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Орудия
   * Метод возвращает список орудий танков.
   * Возможны изменения логики работы метода и значений некоторых полей в зависимости от переданных дополнительных параметров.
   * Поля, которые могут измениться:
   * * **damage**
   * * **piercing_power**
   * * **rate**
   * * **price_credit**
   * * **price_gold**
   * Влияние дополнительных входных параметров:
   * * передан корректный **turret_id** — происходит фильтрация по принадлежности орудий к башне и изменение вышеуказанных характеристик в зависимости от башни;
   * * передан корректный **turret_id** и **module_id** — возвращает информацию по каждому модулю с изменёнными вышеуказанными характеристиками в зависимости от башни, или null, если модуль и башня не совместимы;
   * * передан корректный **tank_id** — если тип танка соответствует одному из AT-SPG, SPG, mediumTank, проводится фильтрация по принадлежности орудий к танку, вышеуказанные характеристики изменяются в зависимости от танка; в противном случае возвращается ошибка с требованием указать **turret_id**. Если переданы еще и **module_id**, то возвращается информация о каждом модуле с изменёнными вышеуказанными характеристиками в зависимости от танка, или null, если модуль не совместим с танком;
   * * переданы совместимые **turret_id** и **tank_id** — происходит фильтрация по принадлежности орудий к башне и танку, вышеуказанные характеристики изменяются в зависимости от башни.
   * @category Танкопедия
   * @link encyclopedia/tankguns
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @param integer $input['tank_id'] Идентификатор совместимой техники
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tankguns ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'module_id' => 'numeric, list', 'nation' => 'string', 'turret_id' => 'numeric', 'tank_id' => 'numeric'))) return NULL;
    $o = $this->send('encyclopedia/tankguns', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения
   * @category Танкопедия
   * @link encyclopedia/achievements
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/achievements', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация о Танкопедии
   * Метод возвращает информацию о Танкопедии.
   * @category Танкопедия
   * @link encyclopedia/info
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function info ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/info', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Информация об игровых картах
   * Метод возвращает информацию об игровых картах.
   * @category Танкопедия
   * @link encyclopedia/arenas
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function arenas ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('encyclopedia/arenas', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Техника игрока 
 */
class wgapi_wot_tanks extends WgApiCore {

  /**
   * Статистика по технике игрока
   * Метод возвращает общую, ротную и клановую статистику по каждой единице техники каждого пользователя.
   * @category Техника игрока
   * @link tanks/stats
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @param integer|array $input['tank_id'] Идентификатор техники игрока
   * @param string $input['in_garage'] Фильтр по наличию техники в Ангаре. Если параметр не указан, возвращается вся техника. Параметр обрабатывается только при наличии действующего access_token для указанного account_id.. Допустимые значения: 
   * * "1" - Возвращать технику из Ангара. 
   * * "0" - Возвращать технику, которой уже нет в Ангаре. 
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function stats ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) return NULL;
    $o = $this->send('tanks/stats', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Достижения по технике игрока
   * Метод возвращает список достижений по всей технике игрока.
   * Значения поля **achievements** зависят от свойств достижений (см. [Информация о достижениях]( /developers/api_reference/wot/encyclopedia/achievements )):
   * * степень от 1 до 4 для знака классности и этапных достижений (type: "class")
   * * максимальное значение серийных достижений (type: "series")
   * * количество заработанных наград из секций: Герой битвы, Эпические достижения, Групповые достижения, Особые достижения и т.п. (type: "repeatable, single, custom")
   * @category Техника игрока
   * @link tanks/achievements
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param integer $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @param integer|array $input['tank_id'] Идентификатор техники игрока
   * @param string $input['in_garage'] Фильтр по наличию техники в Ангаре. Если параметр не указан, возвращается вся техника. Параметр обрабатывается только при наличии действующего access_token для указанного account_id.. Допустимые значения: 
   * * "1" - Возвращать технику из Ангара. 
   * * "0" - Возвращать технику, которой уже нет в Ангаре. 
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function achievements ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'account_id' => 'numeric'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string', 'tank_id' => 'numeric, list', 'in_garage' => 'string'))) return NULL;
    $o = $this->send('tanks/achievements', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * Рейтинги кланов 
 */
class wgapi_wot_clanratings extends WgApiCore {

  /**
   * Типы рейтингов
   * Метод возвращает информацию о типах и категориях рейтингов.
   * @category Рейтинги кланов
   * @link clanratings/types
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function types ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('clanratings/types', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Даты c доступными рейтингами
   * Метод возвращает даты, за которые есть рейтинговые данные.
   * @category Рейтинги кланов
   * @link clanratings/dates
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * ----------------------------------------
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
   * @param integer|array $input['clan_id'] Идентификатор клана
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function dates ($i = array()) {
    $this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(407, "INVALID_CLAN_ID", "Неверное значение **clan_id**")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string'), array('language' => 'string', 'fields' => 'string', 'clan_id' => 'numeric, list'))) return NULL;
    $o = $this->send('clanratings/dates', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Лучшие кланы
   * Метод возвращает список лучших кланов по заданным параметрам.
   * @category Рейтинги кланов
   * @link clanratings/top
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param string $input['rank_field'] Категория рейтинга
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата расчёта рейтингов. Не больше, чем 7 дней до текущей даты; по умолчанию - вчера. Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @param integer $input['limit'] Максимальное количество кланов в списке. Максимум: 1000, по умолчанию: 10.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function top ($i = array()) {
    $this->erorr->add(array(array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "Не задано значение параметра **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(407, "INVALID_LIMIT", "Неверное значение **limit**"), array(407, "INVALID_RANK_FIELD", "Неверное значение **rank_field**")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('clanratings/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Рейтинги кланов
   * Метод возвращает рейтинги кланов по заданным идентификаторам.
   * @category Рейтинги кланов
   * @link clanratings/clans
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param integer|array $input['clan_id'] Идентификаторы кланов
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата расчёта рейтингов. Не больше, чем 7 дней до текущей даты; по умолчанию - вчера. Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function clans ($i = array()) {
    $this->erorr->add(array(array(402, "CLAN_ID_NOT_SPECIFIED", "Не задано значение параметра **clan_id**"), array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(407, "INVALID_CLAN_ID", "Неверное значение **clan_id**")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date'))) return NULL;
    $o = $this->send('clanratings/clans', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Соседние позиции в рейтинге кланов
   * Метод возвращает список соседних позиций в заданном рейтинге кланов.
   * @category Рейтинги кланов
   * @link clanratings/neighbors
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['type'] Период формирования рейтинга. Допустимые значения: 
   * * "1" - 1 
   * * "all" - all 
   * * "28" - 28 
   * * "7" - 7 
   * @param integer $input['clan_id'] Идентификатор клана
   * @param string $input['rank_field'] Категория рейтинга
   * ----------------------------------------
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
   * @param timestamp/date $input['date'] Дата расчёта рейтингов. Не больше, чем 7 дней до текущей даты; по умолчанию - вчера. Дата в формате UNIX timestamp или ISO 8601. Например, 1376542800 или 2013-08-15T00:00:00
   * @param integer $input['limit'] Максимальное количество соседних позиций в рейтинге кланов. Максимум: 50; по умолчанию: 5.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function neighbors ($i = array()) {
    $this->erorr->add(array(array(402, "CLAN_ID_NOT_SPECIFIED", "Не задано значение параметра **clan_id**"), array(402, "TYPE_NOT_SPECIFIED", "Не задано значение параметра **type**"), array(402, "RANK_FIELD_NOT_SPECIFIED", "Не задано значение параметра **rank_field**"), array(404, "RATINGS_NOT_FOUND", "Нет рейтинговых данных на указанную дату"), array(407, "INVALID_TYPE", "Неверное значение **type**"), array(407, "INVALID_LIMIT", "Неверное значение **limit**"), array(407, "INVALID_RANK_FIELD", "Неверное значение **rank_field**")));
    if (!$this->validate($i, array('application_id' => 'string', 'type' => 'string', 'clan_id' => 'numeric', 'rank_field' => 'string'), array('language' => 'string', 'fields' => 'string', 'date' => 'timestamp/date', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('clanratings/neighbors', $i, array('http', 'https'));
    return $o;
  }

}

/**
 * «Мировая война» 
 */
class wgapi_wot_globalwar extends WgApiCore {

  /**
   * Кланы
   * Метод возвращает список кланов, участвующих в «Мировой войне».
   * @category «Мировая война»
   * @link globalwar/clans
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * ----------------------------------------
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
   * @param integer $input['page_no'] Номер страницы результатов
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function clans ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'limit' => 'numeric', 'page_no' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/clans', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Очки славы
   * Метод возвращает достижения игрока в «Мировой войне».
   * @category «Мировая война»
   * @link globalwar/famepoints
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param integer|array $input['account_id'] Идентификатор аккаунта игрока
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function famepoints ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'account_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/famepoints', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Карты
   * Метод возвращает список карт в «Мировой войне».
   * @category «Мировая война»
   * @link globalwar/maps
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function maps ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/maps', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Провинции
   * Метод возвращает список провинций на Глобальной карте.
   * @category «Мировая война»
   * @link globalwar/provinces
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function provinces ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'province_id' => 'string, list'))) return NULL;
    $o = $this->send('globalwar/provinces', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Лучшие кланы
   * Метод возвращает список лучших кланов по одному из критериев: количество боёв, количество побед, количество провинций.
   * @category «Мировая война»
   * @link globalwar/top
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param string $input['order_by'] Вид сортировки. Допустимые значения: 
   * * "wins_count" - Количество побед клана 
   * * "combats_count" - Количество боёв клана 
   * * "provinces_count" - Количество провинций клана 
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function top ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'order_by' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/top', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Турниры
   * Метод возвращает список турниров на Глобальной карте.
   * @category «Мировая война»
   * @link globalwar/tournaments
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param string $input['province_id'] Идентификатор провинции
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function tournaments ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'province_id' => 'string'), array('language' => 'string', 'fields' => 'string'))) return NULL;
    $o = $this->send('globalwar/tournaments', $i, array('http', 'https'));
    return $o;
  }

  /**
   * История начисления очков славы игрока
   * Метод возвращает историю начисления очков славы игроку
   * @category «Мировая война»
   * @link globalwar/famepointshistory
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
   * ----------------------------------------
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
   * @param timestamp/date $input['since'] Начало этапа
   * @param timestamp/date $input['until'] Конец этапа
   * @param integer $input['page_no'] Номер страницы результатов
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function famepointshistory ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'fields' => 'string', 'since' => 'timestamp/date', 'until' => 'timestamp/date', 'page_no' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/famepointshistory', $i, array('https'));
    return $o;
  }

  /**
   * Аллея славы
   * Метод возвращает рейтинг игроков по очкам славы
   * @category «Мировая война»
   * @link globalwar/alleyoffame
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * ----------------------------------------
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
   * @param integer $input['page_no'] Номер страницы результатов
   * @param integer $input['limit'] Количество возвращаемых записей. Максимальное количество: 100. Если значение неверно или превышает 100, то по умолчанию возвращается 100 записей.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function alleyoffame ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string'), array('language' => 'string', 'fields' => 'string', 'page_no' => 'numeric', 'limit' => 'numeric'))) return NULL;
    $o = $this->send('globalwar/alleyoffame', $i, array('http', 'https'));
    return $o;
  }

  /**
   * Бои клана
   * Метод возвращает список боёв клана.
   * @category «Мировая война»
   * @link globalwar/battles
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param integer|array $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function battles ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'map_id' => 'string', 'clan_id' => 'numeric, list'), array('language' => 'string', 'fields' => 'string', 'access_token' => 'string'))) return NULL;
    $o = $this->send('globalwar/battles', $i, array('http', 'https'));
    return $o;
  }

  /**
   * История начисления очков победы клана
   * Метод возвращает историю начислений очков победы клана.
   * @category «Мировая война»
   * @link globalwar/victorypointshistory
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['map_id'] Идентификатор Глобальной карты
   * @param integer $input['clan_id'] Идентификатор клана
   * ----------------------------------------
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
   * @param timestamp/date $input['since'] Начало этапа
   * @param timestamp/date $input['until'] Конец этапа
   * @param integer $input['offset'] Сдвиг относительно первого результата
   * @param integer $input['limit'] Количество результатов (от 20 до 100)
   * @return array|NULL При возникновенние ошибки выдает NULL.
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
   * Метод осуществляет аутентификацию пользователя, используя Wargaming.net ID (OpenID). Пользователю необходимо ввести email и пароль, использованные при создании аккаунта.
   * Информация о статусе аутентификации будет отправлена на URL, указанный в параметре **redirect_uri**.
   * Параметры **redirect_uri** при успешной аутентификации:
   * * **status: ok** — аутентификация пройдена;
   * * **access_token** — ключ доступа, передаётся во все методы, требующие аутентификации;
   * * **expires_at** — срок действия **access_token**;
   * * **account_id** — идентификатор пользователя;
   * * **nickname** — имя пользователя.
   * Параметры **redirect_uri** при ошибке аутентификации:
   * * **status: error** — произошла ошибка аутентификации;
   * * **code** — код ошибки;
   * * **message** — информация об ошибке.
   * @category Аутентификация
   * @link auth/login
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * ----------------------------------------
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
   * @param integer $input['expires_at'] Срок действия **access_token** в формате UTC. Также можно указать дельту в секундах.
   * Срок действия и дельта не должны превышать две недели, начиная с настоящего времени.
   * @param string $input['redirect_uri'] 
   * URL страницы, на которую будет перенаправлен пользователь после аутентификации.
   * По умолчанию: [{API_HOST}/blank/](https://{API_HOST}/blank/)
   * @param string $input['display'] Внешний вид формы мобильных приложений. Допустимые значения: **page**, **popup**.
   * @param integer $input['nofollow'] При передаче параметра **nofollow=1** переадресация не происходит. URL возвращается в ответе.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function login ($i = array()) {
    $this->erorr->add(array(array(401, "AUTH_CANCEL", "Пользователь отменил авторизацию для приложения"), array(403, "AUTH_EXPIRED", "Превышено время ожидания авторизации пользователя"), array(410, "AUTH_ERROR", "Ошибка аутентификации")));
    $mr = isset($i['return']) ? 'return' : 'location';
    if (!$this->validate($i, array('application_id' => 'string'), array('language' => 'string', 'expires_at' => 'numeric', 'redirect_uri' => 'string', 'display' => 'string', 'nofollow' => 'numeric'))) return NULL;
    if (!isset($i['redirect_uri']) || empty($i['redirect_uri'])) $i['redirect_uri'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $n = get_called_class();
    if (preg_match('/\?/i', $i['redirect_uri'])) $i['redirect_uri'] .= '&' . $n . '=' . __FUNCTION__;
    else $i['redirect_uri'] .= '?' . $n . '=' . __FUNCTION__;
    $o = $_REQUEST;
    if (isset($o[$n]) && $o[$n] == 'login') {
      unset($o[$n]);
      //при отсутствие статуса выводим полученный масив
      if (!isset($o['status'])) return $o;
      //при верном статусе возвращаем данные
      if ($o['status'] == 'ok') { unset($o['status']); return $o;}
      //при ошибки переводим обработчик ошибок
      $er = $o;
      //присвоенние ошибки
      $this->erorr->set($er, 'auth/login', $i);
      switch ((string) $er['message']) {
        //выполняем обновление клиента
        case 'METHOD_DISABLED':
        case 'METHOD_NOT_FOUND': $this->update(); break;
      }
      return NULL;
    }
    $o = $this->redirect('auth/login', $i, array('https'), $mr);
    return $o;
  }

  /**
   * Продление Access Token
   * Метод генерирует новый **access_token** на основе действующего.
   * Используется для тех случаев, когда пользователь всё ещё пользуется приложением, а срок действия **access_token** уже подходит к концу.
   * @category Аутентификация
   * @link auth/prolongate
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
   * ----------------------------------------
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
   * @param integer $input['expires_at'] Срок действия **access_token** в формате UTC. Также можно указать дельту в секундах.
   * Срок действия и дельта не должны превышать две недели, начиная с настоящего времени.
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function prolongate ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string', 'expires_at' => 'numeric'))) return NULL;
    $o = $this->send('auth/prolongate', $i, array('https'));
    return $o;
  }

  /**
   * Выход
   * Метод удаляет **access_token** пользователя.
   * После вызова данного метода **access_token** перестаёт действовать.
   * @category Аутентификация
   * @link auth/logout
   * @param array $input
   * ----------------------------------------
   * @param string $input['application_id'] Идентификатор приложения
   * @param string $input['access_token'] Ключ доступа к персональным данным пользователя. Имеет срок действия. Для получения ключа доступа необходимо запросить аутентификацию.
   * ----------------------------------------
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
   * @return array|NULL При возникновенние ошибки выдает NULL.
   */
  function logout ($i = array()) {
    if (!$this->validate($i, array('application_id' => 'string', 'access_token' => 'string'), array('language' => 'string'))) return NULL;
    $o = $this->send('auth/logout', $i, array('https'));
    return $o;
  }

}

