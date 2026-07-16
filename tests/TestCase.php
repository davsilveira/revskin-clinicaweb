<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Cached config hardcodes DB_CONNECTION=mysql from .env and would make
        // RefreshDatabase run migrate:fresh against the local DDEV database.
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        return parent::createApplication();
    }
}

