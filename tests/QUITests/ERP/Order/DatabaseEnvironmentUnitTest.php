<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;

class DatabaseEnvironmentUnitTest extends TestCase
{
    public function testLocalExecutionUsesSqliteWithoutGitLabCiEnvironment(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([]));
    }

    public function testLocalExecutionUsesSqliteWhenGitLabCiIsNotTrue(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'false'
        ]));
    }

    public function testGitLabExecutionUsesConfiguredDatabase(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_CI_DATABASE, DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'true'
        ]));
    }
}
