<?php require 'vendor/autoload.php';

$app = new Slim();

$app->get('/', function () {
    echo "Hello, Yura";
});

$app->get('/hello/:name', function ($name) {
    echo "Hello, $name";
});

$app->run();
