<?php
declare(strict_types=1);
foreach (['core', 'schema', 'apps', 'sso', 'auth', 'view'] as $f) require_once __DIR__ . "/$f.php";
set_exception_handler(function (Throwable $e) {
    $dir = __DIR__ . '/../storage/logs'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($dir . '/error.log', '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n", FILE_APPEND);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
    http_response_code(500); echo 'Something went wrong. Please try again.';
});
if (cfg()) ensure_schema();
