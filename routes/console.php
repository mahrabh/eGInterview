<?php

use App\Services\BillingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:purge-voided', function (BillingService $billing) {
    $deleted = $billing->purgeExpiredVoidedInvoices();
    $days = BillingService::VOID_RETENTION_DAYS;

    $this->info("Purged {$deleted} voided invoice(s) older than {$days} days.");
})->purpose('Permanently delete voided invoices older than 30 days');

Schedule::command('billing:purge-voided')->daily();
