<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);

        return $app;
    }

    protected function beforeRefreshingDatabase()
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);

        $this->app->make('db')->purge();

        $database = config('database.connections.'.config('database.default').'.database');

        if ($database !== ':memory:') {
            throw new \RuntimeException("Refusing to refresh a non in-memory database [{$database}].");
        }
    }
}
