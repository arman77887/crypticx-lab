<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException(
                'TEST SAFETY: application environment is not testing.'
            );
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                'TEST SAFETY: automated tests require isolated SQLite.'
            );
        }

        $database = config(
            "database.connections.{$connection}.database"
        );

        $testDatabase = realpath(
            base_path('database/testing.sqlite')
        );

        $productionDatabase = realpath(
            base_path('database/database.sqlite')
        );

        $resolvedDatabase = is_string($database)
            ? realpath($database)
            : false;

        if (
            $resolvedDatabase === false ||
            $testDatabase === false ||
            $resolvedDatabase !== $testDatabase
        ) {
            throw new RuntimeException(
                'TEST SAFETY: database is not the dedicated testing.sqlite.'
            );
        }

        if (
            $productionDatabase !== false &&
            $resolvedDatabase === $productionDatabase
        ) {
            throw new RuntimeException(
                'TEST SAFETY: production database collision detected.'
            );
        }
    }
}
