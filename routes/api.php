<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::match(['post'], 'software/migrate', [UserController::class, 'runMigrationsAndSeeders']);
Route::middleware(['auth:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::match(['post'], 'software/reset', [UserController::class, 'resetDatabase'])->middleware('permission:software.reset');
    Route::match(['post'], 'permissions/assign-to-user', [UserController::class, 'assignToUser'])->middleware('permission:assign.user');

    Route::match(['get', 'post'], 'user-list', [UserController::class, 'list'])
        ->middleware('permission:user.list');

    Route::match(['post', 'get'], 'users', [UserController::class, 'store'])
        ->middleware('permission:user.create');

    Route::match(['post'], 'users/{id}', [UserController::class, 'update'])
        ->middleware('permission:user.update');

    Route::match(['delete'], 'users/{id}', [UserController::class, 'destroy'])
        ->middleware('permission:user.delete');
});
