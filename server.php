<?php

// Local development router: never log reset tokens or query strings.
$publicPath = getcwd();
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($publicPath.$uri);
if ($uri !== '/' && $file !== false && str_starts_with($file, $publicPath.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
$safeUri = str_starts_with($uri, '/reset-password/') ? '/reset-password/[redacted]' : preg_replace('/[\r\n\t]/', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];
file_put_contents('php://stdout', '['.date('D M j H:i:s Y').'] '.$_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT']." [$method] URI: $safeUri\n");
require_once $publicPath.'/index.php';
