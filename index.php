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

  $payment = new SNGBEcomm_Payment('qwe123!@#', '7000', '7000-alias');
  $url = $payment->create($id, 1);
  
  //Redirect user to payment page
  header('Location: ' . $url) ;
  exit();
})->name('checkout');

// Payment Notification handler endpoint
$app->post('/notification', function () use ($app) {
  $request = $app->request;

  //TODO: Fix static value
  $merchant = '7000';
  $psk = 'qwe123!@#';
  $trackid = $request->params("trackid");
  $payment_object = R::load('payment', $trackid);
  $action = $payment_object->action;
  $amount = $payment_object->amount;
  $udf5 = $request->params("udf5");        
  $hash_psk = sha1( $psk );
  $salt = $merchant . $amount . $trackid . $action . $hash_psk;
  $hash = sha1( $salt ); 

  $error = $request->params('Error');

  //TODO: Fix absolute path
  $rootURL = "http://ecm-client.sngb.local/sampleshop-php";

  if ($error) {
    $reply = "REDIRECT=" . $rootURL . "/error?Error=" . $error;
  }
  else 
  {
    //Check SNGB ecommerce server signature
    if ($hash != $udf5) {     
      $app->halt(403, "Операция оплаты не удалась. Причина: неправильный сервер обработки платежа.");
    }
    $payment_object->result = $request->params("result");
    $payment_object->responsecode = $request->params("responsecode");
    $payment_object->paymentid = $request->params("paymentid");
    R::store($payment_object);
    $reply = 'REDIRECT=' . $rootURL .'/payment/'.$trackid;
  }

  //TODO: Надо делать редирект с помощью JS на клиенте,
  // чтобы не было дерганий окна браузера
  echo $reply;
});

// Error payment page 
$app->get('/error', function () use ($app) {
  $request = $app->request;
  $error = $request->params('Error');
  $errorText = $request->params('ErrorText');
  if ($error == '') {
    echo "Оплата не удалась! Обратитесь в службу поддержки сайта.";
    //$app->halt(403, "Что то пошло не так! Обратитесь в службу поддержки сайта.");
  }
  echo $error . "  " . $errorText;
});

// Result payment page 
$app->get('/payment/:trackid', function ($trackid) use ($app) {
  $payment_object = R::load('payment', $trackid);
  switch ($payment_object->result)
  {
    case "CANCELED":
      $outcome = "Операция оплаты отменена.";
      break;
    case "NOT APPROVED":
      switch ($payment_object->responsecode)
      {   
        case "04": 
          $outcome = "Ошибка. Недействительный номер карты.";
          break;
        case "14": 
          $outcome = "Ошибка. Неверный номер карты.";
          break;
        case "33":
        case "54": 
          $outcome = "Ошибка. Истек срок действия карты."; 
          break;
        case "Q1": 
          $outcome = "Ошибка. Неверный срок действия карты или карта просрочена."; 
          break;            
        case "51":
          $outcome = "Ошибка. Недостаточно средств.";
          break;
        case "56":
          $outcome = "Ошибка. Неверный номер карты.";
          break;
        default:
          $outcome = "Ошибка. Обратитесь в банк, выпустивший карту.";
      }
      break;
    case "CAPTURED":
      if ($payment_object->responsecode == "00") {
        $outcome = 'Операция проведена успешно.';      
      }
  }
  echo $outcome . " Номер операции: " . $trackid . " Номер платежа: " . $payment_object->paymentid;
});

// 404 Not Found handler
$app->notFound(function () use ($app) {
  $app->render('404.html.twig');});

// Error handler
$app->error(function () use ($app) {
  $app->render('500.html.twig');
});

$app->run();
