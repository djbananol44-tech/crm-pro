<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

// Health check для Docker/Kubernetes
Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]));

// Главная страница
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        return redirect($user->isAdmin() ? '/admin' : '/deals');
    }

    return redirect('/login');
});

// Авторизация
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Маршруты для авторизованных пользователей
Route::middleware(['auth'])->group(function () {
    // Дашборд со списком сделок
    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/dashboard', [DealController::class, 'index'])->name('dashboard');

    // Карточка клиента
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');

    // Обновление сделки
    Route::patch('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');

    // Назначить себя ответственным
    Route::post('/deals/{deal}/assign', [DealController::class, 'assignToMe'])->name('deals.assign');

    // Обновить AI-анализ
    Route::post('/deals/{deal}/refresh-ai', [DealController::class, 'refreshAiSummary'])->name('deals.refresh-ai');

    // Перевести переписку
    Route::post('/deals/{deal}/translate', [DealController::class, 'translateMessages'])->name('deals.translate');

    // Экспорт и отчёты
    Route::prefix('export')->name('export.')->group(function () {
        // Асинхронный экспорт
        Route::post('/start', [ExportController::class, 'startExport'])->name('start');
        Route::get('/status/{exportId}', [ExportController::class, 'checkStatus'])->name('status');
        Route::get('/download/{exportId}', [ExportController::class, 'download'])->name('download');

        // Синхронный экспорт (до 1000 записей)
        Route::get('/quick', [ExportController::class, 'quickExport'])->name('quick');

        // Отчёт
        Route::get('/report', [ExportController::class, 'getReport'])->name('report');
    });
});
