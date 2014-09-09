Wargaming.net Public API Client
=========================
Набор общедоступных методов API, которые предоставляют доступ к проектам Wargaming.net, включая игровой контент, статистику игроков, данные энциклопедии и многое другое.

Данный класс организован так что бы функционал, который доступен будет генерируется сам. При модификации определенных переменных будет сгененрирован код для API World of Tanks, World of Warplanes, Wargaming.NET

При пустом классе после первого запроса будет сгенерирован код. Так же геннерация и изменения файла будет происходить при получение ошибки "Указан неверный метод API" и "Указаный метод API отключён".

Класс использует ключи от от серверного и автономного приложений для оптимизации количества запросов и их быстро действия.


Получить ключи от приложений возможно на

    https://ru.wargaming.net/developers/applications/

При инициализации нужно обезательно указать ключевые данные:
````php
$params = array(
  'region' => 'RU', //регион
  'language' => 'ru', //язык ответа
  'apiStandalone' => '171745d21f7f98fd8878771da1000a31', //Ключь автономного приложения
  'apiServer' => 'd0a293dc77667c9328783d489c8cef73' //Ключь серверного приложения
);
new Wgapi($params);
````


Что бы регенерировать код нужно указать для:

World of Tanks
````php
  public $serverDomain = 'api.worldoftanks.';
  public $apiName = 'wot';
````


World of Warplanes
````php
  public $serverDomain = 'api.worldofwarplanes.';
  public $apiName = 'wowp';
````
**Учитывайте что команда авторизации присутствует только в API World of Tanks**


Wargaming.NET
````php
  public $serverDomain = 'api.worldoftanks.';
  public $apiName = 'wgn';
````


## Пример использования методов:
````php
$params = array(
  'region' => 'RU',
  'language' => 'ru',
  'apiStandalone' => '1b7bc64858d79aed49d1bc479248fa1a',
  'apiServer' => '2cc044ecd33f396b48287cd155c9958d'
);
$wot = new Wgapi($params);
$params = array(
  'account_id' => 1000000
);
$wot->language('en');

$output = $wot->account->info($params);
````