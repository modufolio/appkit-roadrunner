<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?: __DIR__.'/public';

if ('/' !== $uri && file_exists($documentRoot.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $documentRoot.'/index.php';
