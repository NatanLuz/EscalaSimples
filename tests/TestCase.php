<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get('database.connections.sqlite');

        self::ensureSafeDatabase($app->environment(), $config->get('database.default'), $connection);
        $config->set('database.connections', ['sqlite' => $connection]);

        return $app;
    }

    public static function ensureSafeDatabase(?string $environment, ?string $default, mixed $connection): void
    {
        if ($environment !== 'testing' || $default !== 'sqlite'
            || ! is_array($connection) || ($connection['driver'] ?? null) !== 'sqlite'
            || ($connection['database'] ?? null) !== ':memory:'
            || ! empty($connection['url']) || isset($connection['read']) || isset($connection['write'])) {
            throw new RuntimeException('Testes exigem o ambiente testing e SQLite exclusivamente em memória.');
        }
    }
}
