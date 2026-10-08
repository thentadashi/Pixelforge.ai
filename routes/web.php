<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\WorkspaceController;
use App\Services\SiteContent;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('content', fn () => response()->json(SiteContent::all()));
    Route::get('me', [AuthController::class, 'me']);
    Route::get('availability', [BookingController::class, 'availability'])->middleware('throttle:60,1');
    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:5,10');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,10');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1');
    Route::post('accept-invitation', [AuthController::class, 'invitation'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('workspace', [WorkspaceController::class, 'index']);
        Route::post('quotations/{id}/decision', [WorkspaceController::class, 'quotationDecision']);
        Route::post('milestones/{id}/decision', [WorkspaceController::class, 'milestoneDecision']);
        Route::post('tickets', [WorkspaceController::class, 'ticket']);
        Route::post('tickets/{id}/replies', [WorkspaceController::class, 'reply']);
        Route::patch('tickets/{id}/status', [WorkspaceController::class, 'ticketStatus']);
        Route::post('files', [FileController::class, 'store']);
        Route::get('files/{id}/download', [FileController::class, 'download']);
        Route::delete('files/{id}', [FileController::class, 'destroy']);
        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('data', [AdminController::class, 'index']);
            Route::put('content', [AdminController::class, 'content']);
            Route::post('founder-image', [AdminController::class, 'founderImage']);
            Route::post('clients', [AdminController::class, 'client']);
            Route::put('clients/{id}', [AdminController::class, 'client']);
            Route::post('clients/{id}/invitation', [AdminController::class, 'invite']);
            Route::patch('bookings/{id}', [BookingController::class, 'update']);
            Route::post('{resource}', [AdminController::class, 'save'])->where('resource', 'projects|quotations|milestones|updates|invoices');
            Route::put('{resource}/{id}', [AdminController::class, 'save'])->where('resource', 'projects|quotations|milestones|updates|invoices');
        });
    });
});
Route::get('/reset-password/{token}', fn () => view('app'))->name('password.reset');
Route::get('/login', fn () => view('app'))->name('login');
Route::get('/{path?}',fn () => view('app'))->where('path','(?!api(?:/|$)).*');
