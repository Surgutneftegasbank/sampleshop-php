<?php require 'vendor/autoload.php';

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
});

$app->get('/hello/:name', function ($name) {
    echo "Hello, $name";
});

$app->run();
