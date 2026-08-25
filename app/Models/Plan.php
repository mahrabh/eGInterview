<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'slug', 'price', 'candidate_limit', 'interview_limit', 'ai_generation_limit', 'team_member_limit', 'is_active'])]
class Plan extends Model
{
    use HasFactory;
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
