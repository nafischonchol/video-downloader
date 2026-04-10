<?php

use App\Http\Controllers\VideoDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoDownloadController::class, 'index']);
Route::post('/fetch', [VideoDownloadController::class, 'fetch'])->name('video.fetch');
Route::post('/download', [VideoDownloadController::class, 'download'])->name('video.download');
