<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestCase as ApplicationTestCase;

class TestDatabaseSafetyTest extends TestCase
{
    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_configuration_is_rejected(string $environment, string $default, array $connection): void
    {
        $this->expectException(RuntimeException::class);
        ApplicationTestCase::ensureSafeDatabase($environment, $default, $connection);
    }

    public static function unsafeConfigurations(): array
    {
        $safe = ['driver' => 'sqlite', 'database' => ':memory:', 'url' => null];

        return [
            'production environment' => ['production', 'sqlite', $safe],
            'mysql default' => ['testing', 'mysql', $safe],
            'sqlite file' => ['testing', 'sqlite', array_replace($safe, ['database' => 'database.sqlite'])],
            'database url' => ['testing', 'sqlite', array_replace($safe, ['url' => 'sqlite:///other.sqlite'])],
            'read override' => ['testing', 'sqlite', array_replace($safe, ['read' => ['database' => 'other']])],
            'write override' => ['testing', 'sqlite', array_replace($safe, ['write' => ['database' => 'other']])],
            'wrong driver' => ['testing', 'sqlite', array_replace($safe, ['driver' => 'mysql'])],
        ];
    }
}
