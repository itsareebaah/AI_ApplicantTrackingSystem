<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    protected $fillable = [
        'user_id',
        'resume_path',
        'status',
        'error',
        'parsed_name',
        'parsed_email',
        'parsed_phone',
        'parsed_education_json',
        'parsed_experience_json',
        'extracted_skills_json',
        'ai_match_percentage',
        'ai_rank',
        'hr_notes',
    ];

    protected $casts = [
        'parsed_education_json' => 'array',
        'parsed_experience_json' => 'array',
        'extracted_skills_json' => 'array',
        'ai_match_percentage' => 'integer',
        'ai_rank' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
