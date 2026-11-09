<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;
use Tests\DatabaseTestCase;

class DevelopmentRouterTest extends DatabaseTestCase
{
    public function test_owned_loopback_router_serves_assets_without_raw_query_logs_or_private_files(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $error);
        $this->assertNotFalse($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $env = Dotenv::parse(file_get_contents(base_path('.env.testing')));
        $env['APP_DEBUG'] = 'false';
        $env['SESSION_DRIVER'] = 'array';
        $env['CACHE_STORE'] = 'array';
        $server = new Process([PHP_BINARY, '-S', $address, '-t', public_path(), base_path('server.php')], public_path(), $env);
        $server->setTimeout(20);
        $server->start();
        $marker = 'private-probe-'.str_repeat('q', 24);
        try {
            $ready = false;
            $deadline = microtime(true) + 4;
            do {
                $probe = @stream_socket_client('tcp://'.$address, $code, $error, 0.1);
                if ($probe) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline && $server->isRunning());
            $this->assertTrue($ready, 'Owned HTTP test process did not start.');
            $request = function (string $path, string $method = 'GET') use ($address): array {
                $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 4, 'follow_location' => 0]]);
                $body = file_get_contents('http://'.$address.$path, false, $context);

                return ['headers' => implode("\n", http_get_last_response_headers()), 'body' => $body];
            };
            $asset = $request('/images/nfcheck-logo.png?secret='.$marker);
            $this->assertStringContainsString('200 OK', $asset['headers']);
            $this->assertStringContainsString('image/png', $asset['headers']);
            $this->assertStringStartsWith("\x89PNG", $asset['body']);
            $head = $request('/images/nfcheck-logo.png?secret='.$marker, 'HEAD');
            $this->assertSame('', $head['body']);
            $post = $request('/images/nfcheck-logo.png', 'POST');
            $this->assertStringContainsString('405 Method Not Allowed', $post['headers']);
            $this->assertSame('', $post['body']);
            foreach (['/..%2f.env', '/.tools/secrets/ai-provider.json', '/.htaccess', '/'.$marker] as $path) {
                $response = $request($path);
                $this->assertStringContainsString('404 Not Found', $response['headers']);
                $this->assertStringNotContainsString('APP_KEY=', $response['body']);
                $this->assertStringNotContainsString('api_key', $response['body']);
            }
            $invalid = $request('/%00'.$marker);
            $this->assertStringContainsString('400 Bad Request', $invalid['headers']);
            $this->assertStringNotContainsString($marker, $server->getOutput().$server->getErrorOutput());
        } finally {
            // This foreground test owns only this newly created child; shared servers
            // and all existing app/services/worker processes are left untouched.
            $server->stop(0.1);
        }
    }
}
