<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'user_id',
        'method',
        'scenario',
        'is_legitimate',
        'verification_passed',
        'attack_succeeded',
        'completion_time_ms',
        'failure_cause',
        'metadata',
    ];

    protected $casts = [
        'is_legitimate' => 'boolean',
        'verification_passed' => 'boolean',
        'attack_succeeded' => 'boolean',
        'completion_time_ms' => 'integer',
        'metadata' => 'array',
    ];
}
