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
  'apiStandalone' => '6caa0c86ea0c3ffb938038b70597f483', //Ключь автономного приложения
  'apiServer' => 'e3ed3fdd67fe9e33bb377281b1a3a57a', //Ключь серверного приложения
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
$wot = new Wgapi(array(
  'region' => 'RU', //регион
  'language' => 'ru', //язык ответа
  'apiStandalone' => '6caa0c86ea0c3ffb938038b70597f483', //Ключь автономного приложения
  'apiServer' => 'e3ed3fdd67fe9e33bb377281b1a3a57a', //Ключь серверного приложения
));

$output = $wot->account->info(array(
  'account_id' => 1000000
));
````