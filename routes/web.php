<?php

use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Not doing a resource as yet
Route::get('/test', [TestController::class, 'test']);

// Testing a json response
Route::get('/testJson', [TestController::class, 'testJson']);

Route::get('/card/{cardName}', [CardController::class, 'show']);