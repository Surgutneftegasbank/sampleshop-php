<?php require 'vendor/autoload.php';

require_once("../sngbecomm-php/lib/SNGBEcomm.php");
require_once("RedBeanORM.php");
use RedBeanORM as R;

R::loadConfig(require_once 'database.php');

$app = new \Slim\Slim(array(
  'mode' => 'development'
));

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
  $app->config(array(
    'debug' => false,
  ));
  R::freeze(true);
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
  $app->config(array(
    'debug' => true,
  ));
  SNGBEcomm::setApiKey('qwe123!@#');
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

$app->get('/', function () use ($app) {
  $parameters = array(
    'var'    => "Variable",
  );

  $app->render('index.html.twig', $parameters);
})->name('index');

// Payment initial endpoint
$app->get('/checkout', function () use ($app) {
  $payment_object = R::dispense('payment');
  $payment_object->amount = 1.0;
  $payment_object->action = SNGBEcomm_Payment::$PURCHASE;
  $id = R::store($payment_object);

  $payment = new SNGBEcomm_Payment();
  $url = $payment->create($id, $payment_object->amount);
  
  //Redirect user to payment page
  header('Location: ' . $url) ;
  exit();
})->name('checkout');

// Payment Notification handler endpoint
$app->post('/payment/notification', function () use ($app) {
  $request = $app->request;
  $trackid = $request->params("trackid");

  // Получаем из бд нужную операцию платежа,
  // если конечно у нас есть trackid
  $payment_object = R::load('payment', $trackid);
  $action = $payment_object->action;
  $amount = $payment_object->amount;

  //TODO: Fix absolute path
  $rootURL = "http://ecm-client.sngb.local/sampleshop-php";

  $error = $request->params('Error');
  $result = $request->params('result');
  $responsecode = $request->params('responsecode');
  $hashresponse = $request->params('udf5');

  $errorhandler = new SNGBEcomm_Error($error, $result, $responsecode, $hashresponse);
  $errormessage = $errorhandler->isError($trackid, $amount, $action);
  if ($errormessage) {
    $reply = 'REDIRECT=' . $rootURL . '/payment/error?trackid=' . $trackid . '&errormessage=' . $errormessage;
  }
  else {
    $reply = 'REDIRECT=' . $rootURL . '/payment/success/' . $trackid;
  }

  $payment_object->errormessage = $errormessage;
  $payment_object->paymentid = $request->params("paymentid");

  // У нас может не быть trackid,
  // а если его нет то изменить нужную сущность не получится
  try {
    R::store($payment_object);
  } catch (Exception $e) {
    //echo $e->getMessage();
  }

  //TODO: Надо делать редирект с помощью JS на клиенте,
  // чтобы не было дерганий окна браузера
  echo $reply;
});

// Error payment page 
$app->get('/payment/error', function () use ($app) {
  $request = $app->request;
  $errormessage = $request->params("errormessage");
  if ($errormessage) 
    echo $errormessage;
  else 
    echo "Оплата не удалась! Обратитесь в службу поддержки сайта.";
});

// Result payment page 
$app->get('/payment/success/:trackid', function ($trackid) use ($app) {
  $payment_object = R::load('payment', $trackid);
  echo "Успешная оплата. Номер операции: " . $trackid . " Номер платежа: " . $payment_object->paymentid;
});

// 404 Not Found handler
$app->notFound(function () use ($app) {
  $app->render('404.html.twig');});

// Error handler
$app->error(function () use ($app) {
  $app->render('500.html.twig');
});

$app->run();
