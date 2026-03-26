<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'test';
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'];

$_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? ('test' === $_SERVER['APP_ENV'] ? '1' : '0');
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'];

putenv('APP_ENV='.$_SERVER['APP_ENV']);
putenv('APP_DEBUG='.$_SERVER['APP_DEBUG']);

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
