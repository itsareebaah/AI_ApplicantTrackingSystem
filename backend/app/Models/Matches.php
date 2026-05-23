<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matches extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'candidate_id',
        'job_id',
        'rank',
        'match_percentage',
        'status',
        'skill_comparison_json',
        'interview_questions_json',
        'raw_json',
    ];

    protected $casts = [
        'rank' => 'integer',
        'match_percentage' => 'integer',
        'skill_comparison_json' => 'array',
        'interview_questions_json' => 'array',
        'raw_json' => 'array',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
