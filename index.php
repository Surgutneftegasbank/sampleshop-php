<?php require 'vendor/autoload.php';

//require "configuration.php";

$app = new \Slim\Slim(array(
    'mode' => 'development'
));

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
    $app->config(array(
        'debug' => false,
    ));
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

// 404 Not Found handler
$app->notFound(function () use ($app) {
  $app->render('404.html.twig');});

// Error handler
$app->error(function () use ($app) {
    $app->render('500.html.twig');
});

$app->get('/', function () use ($app) {
    $parameters = array(
      'var'    => "Variable",
    );
    // Render view
    $app->render('index.html.twig', $parameters);
})->name('index');

$app->get('/checkout', function () use ($app) {

    //$Config = new Configuration('config.txt');
	
    $merchant = "1071";// $Config->get('settings.merchant');
    //alias
	$terminal = "1071-jazzshop";//$Config->get('settings.terminal');
    $password = "qwe123!@#";//$Config->get('settings.password');
    $amount = 1000;
    
    $trackid = 2;//time();
    
    $action = '1';
    
    if ( isset($_REQUEST['amount']) ) $amount = $_REQUEST['amount'];
    if ( isset($_REQUEST['action']) ) $action = $_REQUEST['action'];
        
    // Hash signature
    $hash_pass = sha1( $password );
    $solt = $merchant . $amount . $trackid . $hash_pass;         
    $hash = sha1($solt);
    
    // Http POST request
    $url = 'https://ecm.sngb.ru:443/ECommerce/PaymentInitServlet';      
    
    $params = array(
        'merchant' => $merchant ,
        'terminal' => $terminal ,
        'action' => $action ,
        'amt' => $amount ,
        'trackid' => $trackid ,
        'hash_str' => $hash ,
        'udf1' => 'Test PaymentInit' 
        );
    
    // Param string
    $postdata = "";
    foreach ( $params as $key => $value ) $postdata .= "&".rawurlencode($key)."=".rawurlencode($value);

    // Do POST
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, $url );
    curl_setopt ($ch, CURLOPT_POST, 1 );
    curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata );
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec ($ch);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    if ($curl_errno > 0) {
        echo "cURL Error ($curl_errno): $curl_error";
        //print_r(curl_getinfo($ch));
    } else {
        echo "Data received ";
    }
    curl_close($ch);
     
    $app->redirect($result);
    //echo 'result = ' . $result;
})->name('checkout');

$app->run();
