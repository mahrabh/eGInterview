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
        if ($value) {
            $this->attributes['phone'] = Crypt::encryptString($value);
            $this->attributes['phone_hash'] = hash('sha256', $value);
        }
    }

    public function getPhoneAttribute($value)
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
