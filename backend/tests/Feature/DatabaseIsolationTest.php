<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseIsolationTest extends TestCase
{
    public function test_testing_uses_isolated_mysql_database(): void
    {
        $connection = DB::connection();
        $row = (array) $connection->selectOne('select version() as version');

        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertSame('agent_millow_test', $connection->getDatabaseName());
        $this->assertStringStartsWith('8.4.', (string) ($row['version'] ?? ''));
    }
}
