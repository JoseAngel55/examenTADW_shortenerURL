<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../models/Url.php';
require_once __DIR__ . '/../models/Visit.php';
require_once __DIR__ . '/../resources/v1/UrlResource.php';
require_once __DIR__ . '/../resources/v1/StatsResource.php';

$router = new Router('v1');

$router->addRoute('POST', '/shorten', function () {
    (new UrlResource())->shorten();
});

$router->addRoute('GET', '/stats/{code}', function ($code) {
    (new StatsResource())->show($code);
});

$router->addRawRoute('GET', '/{code}', function ($code) {
    (new UrlResource())->redirect($code);
});

$router->dispatch();