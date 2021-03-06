<?php

declare(strict_types=1);

namespace Umbrellio\LTree\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Umbrellio\LTree\LTreeServiceProvider;
use Umbrellio\Postgres\UmbrellioPostgresProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [UmbrellioPostgresProvider::class, LTreeServiceProvider::class];
    }
}
