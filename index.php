<?php 

/**
 * Тестовый магазин СНГБ Электронной коммерции 
 * (SNGBEcomm + Slim + Twig + Redbean)
 * 
 * @author Yura Zatsepin <yu.zatsepin@sngb.com>
 */
require 'vendor/autoload.php';

require_once("../sngbecomm-php/lib/SNGBEcomm.php");

require_once("RedBeanORM.php");
use RedBeanORM as R;

R::loadConfig(require_once 'database.php');

$app = new \Slim\Slim(array(
  'mode' => 'development',
      //'log.level' => Slim\Log::ERROR,
      'log.enabled' => true,
      'log.writer' => new Slim\Extras\Log\DateTimeFileWriter(array(
        'path' => 'storage/logs',
        'name_format' => 'y-m-d'
      ))
));

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
  $app->config(array(
    'debug' => false,
  ));
  R::freeze(true);
  //TODO: Сделать настройку для Боевого сервера Магазина
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
  $app->config(array(
    'debug' => true,
  ));
  // Конфигурируем Доступ к тестовому платежному серверу СНГБ
  // Данные беруется из личного кабинета : https://ecm.sngb.ru/ECommerce/Merchant/
  //
  // Merchant (или Merchant ID) берем во вкладки Merchant
  //
  // Terminal Alias берем оттуда же. В таблице достапнух терминалов выбираем
  // терминал с которым будем работать, и получаем информацию.
  //
  // Данные об ApiKey или PSK получаем на welcome page (домик в правом верхнем углу,
  // либо первая страница после входа в личный кабинет). 
  // Нажимаем на ссылку: 
  // "Зарегистрировать сайт для взаимодействия интернет-магазина с сервисом электронной коммерции через протокол HTTP API." 
  // Придумываем и вводим PSK (оставляем hash type sha-1)
  // PSK может быть следующего вида: "qwerty12345!@#$"
  // 
  Sngbecomm::setLiveMode(false);
  SNGBEcomm::setApiKey('64fdfab72758601fbff4dd0ef54fa6e6d96338f5');
  SNGBEcomm::setMerchant('7000');
  SNGBEcomm::setTerminalAlias('7000-alias');
});

// Prepare view
$app->view(new \Slim\Views\Twig());
$app->view->parserOptions = array(
  'charset' => 'utf-8',
  'cache' => realpath('templates/cache'),
  'auto_reload' => true,
  'strict_variables' => false,
  'autoescape' => true);
$app->view->getInstance()->addGlobal('app', $app);
$app->view->parserExtensions = array(new \Slim\Views\TwigExtension());


//SET some globally available view data
$resourceUri = $_SERVER['REQUEST_URI'];
$rootUri = $app->request()->getRootUri();
$assetUri = $rootUri;

$app->view()->appendData(
  array(
    'app' => $app,
    'rootUri' => $rootUri,
    'assetUri' => $assetUri,
    'resourceUri' => $resourceUri
));

// Routes. Url Mapping
require 'routes.php';

// 404 Not Found handler
$app->notFound(function () use ($app) {
  $app->render('404.html.twig');});

// Error handler
$app->error(function () use ($app) {
  $app->render('500.html.twig');
});

$app->run();
