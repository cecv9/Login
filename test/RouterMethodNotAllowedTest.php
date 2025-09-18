<?php

declare(strict_types=1);

namespace TestSupport {
    final class HeaderStack
    {
        /**
         * @var string[]
         */
        private static array $headers = [];

        public static function reset(): void
        {
            self::$headers = [];
        }

        public static function push(string $header): void
        {
            self::$headers[] = $header;
        }

        /**
         * @return string[]
         */
        public static function all(): array
        {
            return self::$headers;
        }
    }
}

namespace Enoc\Login\Core {
    function header(string $header, bool $replace = true, ?int $http_response_code = null): void
    {
        \TestSupport\HeaderStack::push($header);
    }
}

namespace {
    require __DIR__ . '/../vendor/autoload.php';

    use Enoc\Login\Core\Router;
    use TestSupport\HeaderStack;

    HeaderStack::reset();
    http_response_code(200);

    $router = new Router();
    $router->loadRoutes(__DIR__ . '/../app/Config/routes.php');

    ob_start();
    $response = $router->dispatch('/logout', 'GET');
    ob_end_clean();

    $allowHeader = null;
    foreach (HeaderStack::all() as $header) {
        if (stripos($header, 'Allow:') === 0) {
            $allowHeader = $header;
            break;
        }
    }

    if (http_response_code() !== 405) {
        throw new RuntimeException('Expected 405 status code for GET /logout');
    }

    if ($allowHeader !== 'Allow: POST') {
        $message = 'Expected Allow header to list POST, got ' . ($allowHeader ?? 'none');
        throw new RuntimeException($message);
    }

    if ($response !== '405 Method Not Allowed') {
        throw new RuntimeException('Unexpected response body: ' . $response);
    }

    echo "GET /logout -> 405 with Allow: POST\n";
}