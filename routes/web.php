<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlanController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// TEMP: fix admin password (double-hash fix)
Route::get('/fix-admin-seed', function () {
    try {
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@egen.com'],
            [
                'name' => 'Super Admin',
                'password' => '12345678',
                'role' => 'admin',
            ]
        );
        return 'Admin user fixed. Email: admin@egen.com / Password: 12345678';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

/*
|--------------------------------------------------------------------------
| Public Candidate Interview Routes
|--------------------------------------------------------------------------
*/
Route::prefix('join')->group(function () {
    Route::get('/{identifier}', [InterviewController::class, 'publicSession'])
        ->name('interview.public');
    Route::post('/{identifier}/transcript', [InterviewController::class, 'saveTranscript'])
        ->name('interview.transcript');
    Route::post('/{identifier}/photo', [InterviewController::class, 'savePhoto'])
        ->name('interview.photo');
});

/*
|--------------------------------------------------------------------------
| Recruiter Routes (all authenticated users)
| Access: Dashboard + full interview system + own account settings
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard & Interview Management
    Route::get('/dashboard', [InterviewController::class, 'index'])->name('dashboard');
    Route::get('/interviews/download-template', [InterviewController::class, 'downloadTemplate'])->name('interviews.download-template');
    Route::post('/interviews/import', [InterviewController::class, 'import'])->name('interviews.import');
    Route::post('/interviews/{interview}/generate', [InterviewController::class, 'generateQuestions'])->name('interviews.generate');
    Route::get('/interviews/{interview}/review', [InterviewController::class, 'review'])->name('interviews.review');
    Route::post('/interviews/{interview}/approve', [InterviewController::class, 'approve'])->name('interviews.approve');
    Route::post('/interviews/{interview}/regenerate-link', [InterviewController::class, 'regenerateLink'])->name('interviews.regenerate-link');
    Route::delete('/interviews/{identifier}', [InterviewController::class, 'destroy'])->name('interviews.destroy');

    // Own account settings (name, email, password) — available to ALL logged-in users
    Route::get('/settings', [ProfileController::class, 'edit'])->name('settings');
    Route::patch('/settings', [ProfileController::class, 'update'])->name('settings.update');

    // Keep old profile routes for Breeze compatibility
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin-Only Routes
| Access: Users management + Plans management
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    // User/Recruiter management
    Route::resource('users', UserController::class);

    // Plans management (create, assign to recruiters)
    Route::resource('plans', PlanController::class);

    // Assign plan to a recruiter
    Route::patch('/users/{user}/assign-plan', [UserController::class, 'assignPlan'])->name('users.assign-plan');
});





Route::get('/zip-check', function () {
    return [
        'php_version' => PHP_VERSION,
        'php_ini' => php_ini_loaded_file(),
        'zip_extension' => extension_loaded('zip'),
        'zip_class' => class_exists(\ZipArchive::class),
    ];
});

require __DIR__ . '/auth.php';