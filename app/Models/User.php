<?php

namespace App\Models;
use App\Models\Interview;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'plan_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class, 'user_id');
    }

    public function plan()
    {
        return $this->belongsTo(\App\Models\Plan::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRecruiter(): bool
    {
        return $this->role === 'recruiter';
    }

    public function isAnalyst(): bool
    {
        return $this->role === 'analyst';
    }

    public function canAccessRecruitment(): bool
    {
        return $this->isAdmin() || $this->isRecruiter();
    }

    public function canAccessLoans(): bool
    {
        return $this->isAdmin() || $this->isAnalyst();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'analyst' => 'Analyst',
            'recruiter' => 'Recruiter',
            default => ucfirst((string) $this->role),
        };
    }

    /** @return list<string> */
    public static function assignableRoles(): array
    {
        return ['analyst', 'recruiter'];
    }
}
