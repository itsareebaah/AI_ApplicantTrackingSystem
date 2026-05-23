<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank')->default(0);
            $table->unsignedTinyInteger('match_percentage')->default(0);
            $table->string('status')->default('needs_review');
            $table->json('skill_comparison_json')->nullable();
            $table->json('interview_questions_json')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
