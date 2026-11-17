<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11A — mesin form (docs/SPEC-FASE-11.md bagian 11A).
 * owner_* null = form mandiri BEM (survei/kuisioner); acara & oprec menyusul (11B/11C).
 * correct_answer tidak pernah dikirim ke peserta; IP responden tidak disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('owner');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->string('purpose', 20)->default('survey')->index();
            $table->string('public_id', 16)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->boolean('is_quiz')->default(false);
            $table->json('settings')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('form_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('form_section_id')->nullable()->constrained('form_sections')->nullOnDelete();
            $table->string('type', 20);
            $table->string('label', 500)->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->json('correct_answer')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['form_id', 'position']);
        });

        Schema::create('form_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->nullableMorphs('respondent');
            $table->string('resume_token', 64)->nullable()->unique();
            $table->string('respondent_email', 191)->nullable();
            $table->json('question_order')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tab_switches')->default(0);
            $table->timestamps();
            $table->index(['form_id', 'respondent_email']);
        });

        Schema::create('form_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_response_id')->constrained('form_responses')->cascadeOnDelete();
            $table->foreignId('form_question_id')->constrained('form_questions')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->json('files')->nullable(); // [{path, name, mime, size}] — disk privat
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->text('grader_note')->nullable();
            $table->timestamps();
            $table->unique(['form_response_id', 'form_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_questions');
        Schema::dropIfExists('form_sections');
        Schema::dropIfExists('forms');
    }
};
