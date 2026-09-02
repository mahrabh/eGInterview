<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class LoanApplicant extends Model
{
    use HasUuids;

    protected $fillable = [
        'application_reference',
        'name',
        'phone',
        'phone_hash',
        'phone_masked',
        'nid',
        'nid_hash',
        'nid_last_four',
        'created_by'
    ];

    public function applications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setPhoneAttribute($value)
    {
        if (!$value) {
            return;
        }

        $normalized = preg_replace('/\D/', '', (string) $value);
        if (str_starts_with($normalized, '880') && strlen($normalized) > 3) {
            $normalized = '0' . substr($normalized, 3);
        }

        $this->attributes['phone'] = Crypt::encryptString($normalized);
        $this->attributes['phone_hash'] = hash('sha256', $normalized);
        $this->attributes['phone_masked'] = self::maskPhone($normalized);
    }

    public function getPhoneAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            $digits = preg_replace('/\D/', '', $value);

            return strlen($digits) >= 6 ? $digits : null;
        }
    }

    public function getMaskedPhoneAttribute(): ?string
    {
        if (!empty($this->attributes['phone_masked'])) {
            return $this->attributes['phone_masked'];
        }

        $phone = $this->phone;

        return $phone ? self::maskPhone($phone) : null;
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '880') && strlen($digits) > 3) {
            $digits = '0' . substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) > 6) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) < 6) {
            return 'N/A';
        }

        return substr($digits, 0, 3) . '****' . substr($digits, -3);
    }

    public function setNidAttribute($value)
    {
        if ($value) {
            $this->attributes['nid'] = Crypt::encryptString($value);
            $this->attributes['nid_hash'] = hash('sha256', $value);
            $this->attributes['nid_last_four'] = substr($value, -4);
        }
    }

    public function getNidAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $value;
    }
}
