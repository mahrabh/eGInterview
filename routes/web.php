<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
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
    Route::post('/{identifier}/live-token', [\App\Http\Controllers\GeminiLiveTokenController::class, 'recruitment'])
        ->name('interview.live-token');
    Route::post('/{identifier}/photo', [InterviewController::class, 'savePhoto'])
        ->name('interview.photo');
    Route::get('/{identifier}/review', [InterviewController::class, 'review'])->name('interview.review.public');
});

Route::prefix('loan-interview')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\LoanInterviewController::class, 'publicSession'])->name('loan-interview.public');
    Route::post('/{token}/start', [\App\Http\Controllers\LoanInterviewController::class, 'start'])->name('loan-interview.start');
    Route::post('/{token}/live-token', [\App\Http\Controllers\GeminiLiveTokenController::class, 'loan'])->name('loan-interview.live-token');
    Route::post('/{token}/transcript', [\App\Http\Controllers\LoanInterviewController::class, 'saveTranscript'])->name('loan-interview.transcript');
});

Route::prefix('bank-opening')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\BankOpeningPublicController::class, 'show'])->name('bank-opening.public');
    Route::post('/{token}/information', [\App\Http\Controllers\BankOpeningPublicController::class, 'submitInformation'])->name('bank-opening.information');
    Route::get('/{token}/interview', [\App\Http\Controllers\BankOpeningInterviewController::class, 'bootstrap'])->name('bank-opening.interview.bootstrap');
    Route::post('/{token}/interview/match', [\App\Http\Controllers\BankOpeningInterviewController::class, 'matchProducts'])->name('bank-opening.interview.match');
    Route::post('/{token}/interview/language', [\App\Http\Controllers\BankOpeningInterviewController::class, 'selectLanguage'])->name('bank-opening.interview.language');
    Route::post('/{token}/interview/select-account', [\App\Http\Controllers\BankOpeningInterviewController::class, 'selectAccount'])->name('bank-opening.interview.select');
    Route::post('/{token}/interview/confirm-account', [\App\Http\Controllers\BankOpeningInterviewController::class, 'confirmAccount'])->name('bank-opening.interview.confirm');
    Route::post('/{token}/interview/answer', [\App\Http\Controllers\BankOpeningInterviewController::class, 'submitAnswer'])->name('bank-opening.interview.answer');
    Route::post('/{token}/interview/clarify', [\App\Http\Controllers\BankOpeningInterviewController::class, 'clarify'])->name('bank-opening.interview.clarify');
    Route::post('/{token}/interview/confirm-summary', [\App\Http\Controllers\BankOpeningInterviewController::class, 'confirmSummary'])->name('bank-opening.interview.confirm-summary');
    Route::post('/{token}/interview/complete-live', [\App\Http\Controllers\BankOpeningInterviewController::class, 'completeLive'])->name('bank-opening.interview.complete-live');
    Route::post('/{token}/live-token', [\App\Http\Controllers\GeminiLiveTokenController::class, 'bankOpening'])->name('bank-opening.live-token');
    Route::post('/{token}/transcript', [\App\Http\Controllers\BankOpeningInterviewController::class, 'saveTranscript'])->name('bank-opening.transcript');
    Route::get('/{token}/documents', [\App\Http\Controllers\BankOpeningInterviewController::class, 'listDocuments'])->name('bank-opening.documents.index');
    Route::post('/{token}/documents', [\App\Http\Controllers\BankOpeningInterviewController::class, 'uploadDocument'])->name('bank-opening.documents.store');
    Route::post('/{token}/documents/account-type', [\App\Http\Controllers\BankOpeningInterviewController::class, 'setDocumentsAccountType'])->name('bank-opening.documents.account-type');
    Route::delete('/{token}/documents/{document}', [\App\Http\Controllers\BankOpeningInterviewController::class, 'removeDocument'])->name('bank-opening.documents.destroy');
    Route::post('/{token}/submit', [\App\Http\Controllers\BankOpeningInterviewController::class, 'submitApplication'])->name('bank-opening.submit');
});

Route::post('/live/transcription-fallback', [\App\Http\Controllers\GeminiLiveTokenController::class, 'logTranscriptionFallback'])
    ->name('live.transcription-fallback');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [ProfileController::class, 'edit'])->name('settings');
    Route::patch('/settings', [ProfileController::class, 'update'])->name('settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/{billing}/invoice', [BillingController::class, 'download'])->name('billing.invoice.download');
    Route::put('/billing/{billing}', [BillingController::class, 'update'])->name('billing.update');
    Route::post('/billing/{billing}/void', [BillingController::class, 'void'])->name('billing.void');
    Route::post('/billing/{billing}/restore', [BillingController::class, 'restore'])->name('billing.restore');
    Route::post('/billing/users/{user}/recreate', [BillingController::class, 'recreate'])->name('billing.recreate');

    /*
    |--------------------------------------------------------------------------
    | Recruitment — Admin + Recruiter
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,recruiter'])->group(function () {
        Route::get('/recruitment', [InterviewController::class, 'index'])->name('recruitment.index');
        Route::get('/interviews/download-template', [InterviewController::class, 'downloadTemplate'])->name('interviews.download-template');
        Route::post('/interviews/import', [InterviewController::class, 'import'])->name('interviews.import');
        Route::get('/interviews/{interview}/photo', [InterviewController::class, 'photo'])->name('interviews.photo');
        Route::get('/interviews/{interview}/details', [InterviewController::class, 'details'])->name('interviews.details');
        Route::post('/interviews/{interview}/generate', [InterviewController::class, 'generateQuestions'])->name('interviews.generate');
        Route::get('/interviews/{interview}/review', [InterviewController::class, 'review'])->name('interviews.review');
        Route::post('/interviews/{interview}/approve', [InterviewController::class, 'approve'])->name('interviews.approve');
        Route::post('/interviews/{interview}/regenerate-link', [InterviewController::class, 'regenerateLink'])->name('interviews.regenerate-link');
        Route::delete('/interviews/{identifier}', [InterviewController::class, 'destroy'])->name('interviews.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Loan Applicants — Admin + Analyst
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:admin,analyst'])->group(function () {
        Route::get('/loan-applications', [\App\Http\Controllers\LoanApplicationController::class, 'index'])->name('loan-applications.index');
        Route::get('/loan-applications/download-template', [\App\Http\Controllers\LoanApplicationController::class, 'downloadTemplate'])->name('loan-applications.download-template');
        Route::get('/loan-applications/status-snapshot', [\App\Http\Controllers\LoanApplicationController::class, 'statusSnapshot'])->name('loan-applications.status-snapshot');
        Route::post('/loan-applications/import', [\App\Http\Controllers\LoanApplicationController::class, 'import'])->name('loan-applications.import');
        Route::post('/loan-applications/{application}/generate-link', [\App\Http\Controllers\LoanApplicationController::class, 'generateLink'])->name('loan-applications.generate-link');
        Route::get('/loan-applications/{application}/report', [\App\Http\Controllers\LoanApplicationController::class, 'report'])->name('loan-applications.report');
        Route::get('/loan-applications/{application}/status', [\App\Http\Controllers\LoanApplicationController::class, 'status'])->name('loan-applications.status');
        Route::post('/loan-applications/{application}/recalculate', [\App\Http\Controllers\LoanApplicationController::class, 'recalculate'])->name('loan-applications.recalculate');
        Route::post('/loan-applications/{application}/retry-extraction', [\App\Http\Controllers\LoanApplicationController::class, 'retryExtraction'])->name('loan-applications.retry-extraction');
        Route::delete('/loan-applications/{applicant}', [\App\Http\Controllers\LoanApplicationController::class, 'destroy'])->name('loan-applications.destroy');

        Route::get('/bank-openings', [\App\Http\Controllers\BankOpeningApplicationController::class, 'index'])->name('bank-openings.index');
        Route::get('/bank-openings/status-snapshot', [\App\Http\Controllers\BankOpeningApplicationController::class, 'statusSnapshot'])->name('bank-openings.status-snapshot');
        Route::post('/bank-openings', [\App\Http\Controllers\BankOpeningApplicationController::class, 'store'])->name('bank-openings.store');
        Route::get('/bank-openings/{application}', [\App\Http\Controllers\BankOpeningApplicationController::class, 'show'])->name('bank-openings.show');
        Route::post('/bank-openings/{application}/generate-link', [\App\Http\Controllers\BankOpeningApplicationController::class, 'generateLink'])->name('bank-openings.generate-link');
        Route::get('/bank-openings/{application}/documents/{document}/download', [\App\Http\Controllers\BankOpeningApplicationController::class, 'downloadDocument'])->name('bank-openings.documents.download');
        Route::post('/bank-openings/{application}/notes', [\App\Http\Controllers\BankOpeningApplicationController::class, 'updateNotes'])->name('bank-openings.notes');
        Route::post('/bank-openings/{application}/request-resubmission', [\App\Http\Controllers\BankOpeningApplicationController::class, 'requestResubmission'])->name('bank-openings.request-resubmission');
        Route::delete('/bank-openings/{application}', [\App\Http\Controllers\BankOpeningApplicationController::class, 'destroy'])->name('bank-openings.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Admin-Only Routes
| Access: Users, Plans
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::resource('users', UserController::class);

    Route::resource('plans', PlanController::class);

    Route::patch('/users/{user}/assign-plan', [UserController::class, 'assignPlan'])->name('users.assign-plan');
});

require __DIR__ . '/auth.php';
