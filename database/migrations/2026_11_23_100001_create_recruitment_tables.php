<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oprec (AGENTS.md Fase 7B + keputusan pemilik):
 * - recruitments: gelombang; open_divisions = id divisi yang bisa dipilih; results_published_at = hasil diumumkan.
 * - recruitment_applicants: CV & foto di disk privat; NIM unik per gelombang; email & WA nullable karena
 *   dihapus otomatis (bersama CV & foto) untuk yang tidak diterima setelah masa simpan.
 * - recruitment_interviews: nilai 1–5 + catatan INTERNAL per pewawancara.
 * - recruitment_applicant_logs: riwayat status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->dateTime('opens_at')->index();
            $table->dateTime('closes_at')->index();
            $table->json('open_divisions');
            $table->boolean('is_active')->default(true);
            $table->timestamp('results_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recruitment_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_id')->constrained()->restrictOnDelete();
            $table->string('registration_code', 20)->unique();
            $table->string('name', 100);
            $table->string('nim', 20);
            $table->string('prodi', 100);
            $table->unsignedSmallInteger('batch_year');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->foreignId('first_choice_division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('second_choice_division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            $table->text('motivation');
            $table->text('experience')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('registered')->index();
            $table->dateTime('interview_at')->nullable();
            $table->string('interview_location', 255)->nullable();
            $table->foreignId('accepted_division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('consent_at');
            $table->timestamp('data_purged_at')->nullable();
            $table->timestamps();

            $table->unique(['recruitment_id', 'nim']);
        });

        Schema::create('recruitment_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['recruitment_applicant_id', 'interviewer_id'], 'rec_interviews_applicant_interviewer_unique');
        });

        Schema::create('recruitment_applicant_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_applicant_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_applicant_logs');
        Schema::dropIfExists('recruitment_interviews');
        Schema::dropIfExists('recruitment_applicants');
        Schema::dropIfExists('recruitments');
    }
};
