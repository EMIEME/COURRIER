<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'prod';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ('prod' !== $env), FILTER_VALIDATE_BOOL);

if ($debug) {
    Debug::enable();
}

$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();

if (($_SERVER['VERCEL'] ?? $_ENV['VERCEL'] ?? null) && $request->server->get('REMOTE_ADDR')) {
    Request::setTrustedProxies(
        [$request->server->get('REMOTE_ADDR')],
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
    );
}

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
