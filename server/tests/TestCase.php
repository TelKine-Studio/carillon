<?php

namespace Tests;

use App\Support\Installation;
use App\Support\NetworkGuard;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Installation::flush();
        // No DNS in tests: every name is a public address unless a test says otherwise.
        NetworkGuard::$resolver = fn (string $host) => filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ['93.184.216.34'];
        $this->app->make(Settings::class)->refresh();
    }
}
