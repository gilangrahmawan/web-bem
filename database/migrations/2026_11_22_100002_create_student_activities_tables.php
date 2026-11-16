<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan sebagai pusat (keputusan Fase 7): proposal & LPJ adalah dua tahap dalam satu kegiatan.
 * Berkas proposal/LPJ di disk privat (nama UUID, nama asli disimpan).
 * Juga menambah target pengumuman/surat ke organisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete(); // divisi pelaksana (kegiatan BEM)
            $table->string('name', 200);
            $table->foreignId('pic_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->index();
            $table->date('ends_on');
            $table->string('location', 200)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('budget_estimate')->default(0);
            $table->string('proposal_path')->nullable();
            $table->string('proposal_name')->nullable();
            $table->unsignedBigInteger('budget_realization')->nullable();
            $table->string('lpj_path')->nullable();
            $table->string('lpj_name')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('review_round')->default(0);
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('proposal_submitted_at')->nullable();
            $table->timestamp('proposal_approved_at')->nullable();
            $table->timestamp('lpj_submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('lpj_reminded_h3_at')->nullable();
            $table->timestamp('lpj_reminded_due_at')->nullable();
            $table->timestamp('lpj_last_overdue_reminder_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_activity_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_pic_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('target_all_organizations')->default(false)->after('target_all');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('target_all_organizations');
        });

        Schema::dropIfExists('activity_pic_transfers');
        Schema::dropIfExists('student_activity_logs');
        Schema::dropIfExists('student_activities');
    }
};
