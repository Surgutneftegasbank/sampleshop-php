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
  $request = $app->request;
  if ($request->getHost() != 'ecm-client.sngb.local')
    $app->halt(403, 'You shall not pass!');

  $payment_object = R::dispense('payment');

  $amount = $request->params("price");
  // Цена в бд хранится в INTEGER.(или в другом числовом формате)
  // 100.00 RUB или 100.00$, хранится как 10000.
  // Используем фиксированную точку с двумя знаками после запятой
  $payment_object->amount = $amount; 
  $payment_object->action = SNGBEcomm_Payment::$PURCHASE;
  $id = R::store($payment_object);

  $payment = new SNGBEcomm_Payment();
  $additional_fields = array(
    // Номер заказа
    "udf1" => $id,
    // Наш номер тех. поддержки
    "udf2" => "8 800 xxx xxx 88"
  );
  $url = $payment->create($id, $payment_object->amount, $additional_fields);
  
  //Redirect user to payment page
  header('Location: ' . $url) ;
  exit();
})->name('checkout');

$app->get('/payments', function () use ($app) {
  $payments = R::findAll('payment');
  //foreach ($payments as $key=>$value) {
    //echo $key . ': ' . $value['id'] . ' '. $value['paymentid'] . ' '. $value['trackid'] . '<br>';
  //}
  $parameters = array(
    'payments' => $payments
  );

  // Render view
  $app->render('payments.html.twig', $parameters);
})->name('payments');

$app->get('/payment/manage/credit', function () use ($app) {
  $log = $app->getLog();
  $request = $app->request;
  if ($request->getHost() != 'ecm-client.sngb.local')
    $app->halt(403, 'You shall not pass!');

  $amount = SNGBEcomm_Payment::convertMoneyToString($request->params("amount"));
  $trackid = $request->params("trackid");
  $tranid = $request->params("tranid");
  $paymentid = $request->params("paymentid");
  $action = SNGBEcomm_Payment::$CREDIT;

  $payment = new SNGBEcomm_Payment();
  $result = $payment->manage($amount, $trackid, $tranid, $paymentid, $action);
  $log->info("Result manage tran:" . $result);
  echo $result;
});

$app->get('/payment/tran', function () use ($app) {
  $request = $app->request;
  $log = $app->getLog();
  $log->info("REQUEST BODY: " . $request->getBody());
});

// URL обратного ответа о состоянии  операции по платежу
// 
// Конечная точка ответа СНГБ сервера электронной коммерции
// Регистрируется в личном кабинете (см. выше)
$app->post('/payment/notification', function () use ($app) {
  $request = $app->request;

  // Проверяем сервер СНГБ
  //if ($request->getIP() != '191.209.17.98')
    //$app->halt(403, 'You shall not pass!');

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

  $log = $app->getLog();
  $log->info("REQUEST BODY: " . $request->getBody());
  if ($errormessage) {
    $reply = 'REDIRECT=' . $rootURL . '/payment/error?trackid=' . $trackid . '&errormessage=' .urlencode($errormessage);
  }
  else {
    $reply = 'REDIRECT=' . $rootURL . '/payment/success/' . $trackid;
  }

  $payment_object->errormessage = $errormessage;

  $payment_object->paymentid = $request->params("paymentid");
  $payment_object->pan = $request->params("pan");
  $payment_object->panhash = $request->params("panhash");
  $payment_object->tranid = $request->params("tranid");
  $payment_object->result = $request->params("result");

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

  $parameters = array(
    'trackid' => $trackid,
    'payment_object' => $payment_object,
    'paymentid' => $payment_object->paymentid
  );

  // Render view
  $app->render('success.html.twig', $parameters);
});


