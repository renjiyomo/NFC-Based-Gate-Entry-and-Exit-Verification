<?php

use App\Support\DevelopmentRequestLog;

// Local development router: log route categories, never arbitrary URL contents.
require_once __DIR__.'/app/Support/DevelopmentRequestLog.php';
$publicPath = getcwd();
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('/[\x00-\x1F\x7F]/', $uri)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Invalid request path.';

    return true;
}
if (preg_match('#(?:^|/)\.[^/]*#', $uri)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Not found.';

    return true;
}
$file = realpath($publicPath.$uri);
$types = ['js' => 'text/javascript; charset=UTF-8', 'css' => 'text/css; charset=UTF-8', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'txt' => 'text/plain; charset=UTF-8', 'webmanifest' => 'application/manifest+json'];
$extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
if ($uri !== '/' && $file !== false && str_starts_with($file, $publicPath.DIRECTORY_SEPARATOR) && is_file($file) && isset($types[$extension])) {
    // Do not return false: PHP's built-in static access log includes raw queries.
    header('Content-Type: '.$types[$extension]);
    header('X-Content-Type-Options: nosniff');
    if (! in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        header('Content-Length: 0');

        return true;
    }
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: '.filesize($file));
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        return true;
    }
    readfile($file);

    return true;
}
$safeUri = DevelopmentRequestLog::path($_SERVER['REQUEST_URI']);
$method = DevelopmentRequestLog::method($_SERVER['REQUEST_METHOD']);
file_put_contents('php://stdout', '['.date('D M j H:i:s Y').'] '.$_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT']." [$method] URI: $safeUri\n");
require_once $publicPath.'/index.php';
