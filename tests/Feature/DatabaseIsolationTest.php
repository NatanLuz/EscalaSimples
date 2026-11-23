<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_run_on_an_isolated_in_memory_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(1, DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabela ausente: {$table}");
        }

        $this->assertSame(3, DB::table('migrations')->count());
    }
}
