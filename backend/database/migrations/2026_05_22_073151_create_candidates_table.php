<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resume_path');
            $table->string('status')->default('parsing');
            $table->text('error')->nullable();
            $table->string('parsed_name')->nullable();
            $table->string('parsed_email')->nullable();
            $table->string('parsed_phone')->nullable();
            $table->json('parsed_education_json')->nullable();
            $table->json('parsed_experience_json')->nullable();
            $table->json('extracted_skills_json')->nullable();
            $table->unsignedTinyInteger('ai_match_percentage')->default(0);
            $table->unsignedInteger('ai_rank')->default(0);
            $table->text('hr_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
