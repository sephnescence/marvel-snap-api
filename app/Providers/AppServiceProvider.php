<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ParallelTesting::setUpProcess(function (int $token) {
            // ...
        });
 
        ParallelTesting::setUpTestCase(function (int $token, TestCase $testCase) {
            // ...
        });
 
        // Executed when a test database is created...
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');

            // I imagine this is where I could restore from a testing db file or something?
            //  I don't think that's how Laravel 10 works though. It sounds like it creates
            //  the testing db with some form of suffix that gets referred to as the token,
            //  which can be seen in the second parameter to this method
        });
 
        ParallelTesting::tearDownTestCase(function (int $token, TestCase $testCase) {
            // ...
        });
 
        ParallelTesting::tearDownProcess(function (int $token) {
            // ...
        });
    }
}
