<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        // Tests need an app key but none is committed: use a throwaway one per run
        // unless the environment (.env / CI) already provides it.
        if (! getenv('APP_KEY') && ! file_exists(__DIR__.'/../.env')) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            putenv("APP_KEY={$key}");
            $_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = $key;
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
