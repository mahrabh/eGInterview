<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$a = App\Models\LoanApplication::whereNotNull('public_token_expiry')->first();
if($a){
    $originalExpiry = $a->getRawOriginal('public_token_expiry');
    $carbonExpiry = $a->public_token_expiry->timestamp;
    $hash = hash('sha256', hash_hmac('sha256', $a->id . $a->public_token_expiry->timestamp, config('app.key')));
    
    echo "ID: " . $a->id . "\n";
    echo "DB Original Expiry: " . $originalExpiry . "\n";
    echo "DB Expiry: " . $carbonExpiry . "\n";
    echo "DB Hash: " . $a->public_token_hash . "\n";
    echo "New Hash: " . $hash . "\n";
}
