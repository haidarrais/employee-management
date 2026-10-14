<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Variables that decide which database and environment the suite runs
     * against. These are forced before the application boots.
     */
    private const TEST_ENV = [
        'APP_ENV'          => 'testing',
        'APP_DEBUG'        => 'true',
        'DB_CONNECTION'    => 'sqlite',
        'DB_DATABASE'      => ':memory:',
        'DB_HOST'          => '',
        'DB_PORT'          => '',
        'DB_USERNAME'      => '',
        'DB_PASSWORD'      => '',
        'SESSION_DRIVER'   => 'array',
        'CACHE_DRIVER'     => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER'      => 'array',
        'BCRYPT_ROUNDS'    => '4',
    ];

    protected function setUp(): void
    {
        $this->forceTestEnvironment();

        parent::setUp();

        $this->guardAgainstRunningOnARealDatabase();
    }

    /**
     * Pin the environment before the framework boots.
     *
     * phpunit.xml already declares these with force="true", which sets getenv()
     * and $_ENV — but NOT $_SERVER, and Laravel's env() helper reads $_SERVER
     * first (via Env::get, which checks $_ENV then $_SERVER then getenv()).
     * Inside the deployed container DB_CONNECTION=mysql is a real $_SERVER
     * entry, so it won over phpunit.xml and the whole suite ran against the
     * production database. Writing all three superglobals here is what makes
     * the pinning actually take effect regardless of how the suite is invoked.
     */
    private function forceTestEnvironment(): void
    {
        foreach (self::TEST_ENV as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        // A key is needed for encryption (sessions, the QR token) but none is
        // committed; generate a throwaway one per run.
        if (! getenv('APP_KEY')) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            putenv("APP_KEY={$key}");
            $_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = $key;
        }
    }

    /**
     * Refuse to run if the suite is pointed at anything but a throwaway database.
     *
     * This is the second layer behind forceTestEnvironment(). If someone runs
     * the suite with a different config file, or the forcing above is ever
     * removed, the run aborts before a single write rather than quietly
     * mutating live data. It exists because of a real incident: the suite once
     * executed against production MySQL and only survived because
     * `migrate:fresh` refuses to run when APP_ENV=production and each test's
     * writes were rolled back — neither of which is a safety mechanism.
     */
    private function guardAgainstRunningOnARealDatabase(): void
    {
        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");

        $isThrowaway = $connection === 'sqlite'
            && in_array($database, [':memory:', '', null], true);

        if (! $isThrowaway) {
            $this->fail(sprintf(
                'Refusing to run tests against the "%s" connection (database "%s"). '
                . 'Tests must use an in-memory SQLite database. This usually means '
                . 'DB_CONNECTION/APP_ENV from the shell are overriding the test '
                . 'configuration; run the suite with `php vendor/bin/phpunit -c phpunit.xml`.',
                $connection,
                $database ?? 'null'
            ));
        }

        if (app()->environment('production')) {
            $this->fail('Refusing to run tests with APP_ENV=production.');
        }
    }
}
