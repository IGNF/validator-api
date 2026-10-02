<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// rate limiter counters are persisted in the test cache (cache.rate_limiter) : reset them for each run
$kernel = new App\Kernel('test', true);
$kernel->boot();
$kernel->getContainer()->get('test.service_container')->get('cache.rate_limiter')->clear();
$kernel->shutdown();
