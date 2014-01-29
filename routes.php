<?php

/**
 * Routes. Url Mapping
 */

require_once("RedBeanORM.php");
use RedBeanORM as R;

$app->get('/', function () use ($app) {
  $app->render('index.html.twig');
})->name('index');

// URL инициализации платежа
//
// Конечную точку инициализации платежа надо зарегестрировать
// в личном кабинете Merchat'а.
// Регистрируются URLs там же где регистрируется PSK/ApiKey (см. index.php)
$app->get('/checkout', function () use ($app) {
  $payment_object = R::dispense('payment');

  // Цена в бд хранится в INTEGER.(или в другом числовом формате)
  // 100.00 RUB или 100.00$, хранится как 10000.
  // Используем фиксированную точку с двумя знаками после запятой
  $payment_object->amount = 10000; 
  $payment_object->action = SNGBEcomm_Payment::$PURCHASE;
  $id = R::store($payment_object);

  $payment = new SNGBEcomm_Payment();
  $url = $payment->create($id, $payment_object->amount);
  
  //Redirect user to payment page
  header('Location: ' . $url) ;
  exit();
})->name('checkout');

// URL обратного ответа о состоянии  операции по платежу
// 
// Конечная точка ответа СНГБ сервера электронной коммерции
// Регистрируется в личном кабинете (см. выше)
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
    $reply = 'REDIRECT=' . $rootURL . '/payment/error?trackid=' . $trackid . '&errormessage=' .urlencode($errormessage);
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

// URL ошибок операции по платежу
// 
// Конечная точка ошибок по платежу СНГБ сервера электронной коммерции
// Регистрируется в личном кабинете (см. выше)
// Также вы можете сюда перенаправить пользователя с помощью
// ответа на запрос Notification в формате 'REDIRECT=url'
$app->get('/payment/error', function () use ($app) {
  $request = $app->request;
  $errormessage = $request->params("errormessage");
  if ($errormessage) 
    echo $errormessage;
  else 
    echo "Оплата не удалась! Обратитесь в службу поддержки сайта.";
});

// URL успешной операции по платежу.
// Этот url передается в формате 'REDIRECT=url' на запрос на 
// страницу Notification
$app->get('/payment/success/:trackid', function ($trackid) use ($app) {
  $payment_object = R::load('payment', $trackid);
  echo "Успешная оплата. Номер операции: " . $trackid . " Номер платежа: " . $payment_object->paymentid;
});


