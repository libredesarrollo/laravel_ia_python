<?php

use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.chat.index');
});

/**
 * Knowledge-base administration.
 *
 * The panel is intentionally open during core development; once the product
 * ships, attach the `auth` middleware group to every ADMIN route below.
 */
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/documents', [DocumentController::class, 'index'])
        ->name('documents.index');

    Route::post('/documents', [DocumentController::class, 'store'])
        ->name('documents.store');

    Route::post('/documents/{document}/ingest', [DocumentController::class, 'ingest'])
        ->name('documents.ingest');

    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');

    Route::get('/chat', [ChatController::class, 'index'])
        ->name('chat.index');

    Route::post('/chat/stream', [ChatController::class, 'stream'])
        ->name('chat.stream');

    Route::get('/chat/status', [ChatController::class, 'status'])
        ->name('chat.status');
});
