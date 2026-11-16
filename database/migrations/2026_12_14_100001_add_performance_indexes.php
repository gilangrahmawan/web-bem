<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10 — performa: index untuk kolom yang dipakai mengurutkan/menyaring daftar panjang.
 * (status, kategori, starts_at, tracking_code, division_id, dan FK sudah ter-index sejak fase sebelumnya.)
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $indexes = [
        'announcements' => ['created_at'],
        'recruitment_applicants' => ['created_at'],
        'student_activities' => ['created_at'],
        'organizations' => ['is_active'],
        'recruitments' => ['is_active'],
        'dues' => ['due_date'],
        'posts' => ['deleted_at'],
        'files' => ['deleted_at'],
        'folders' => ['deleted_at'],
        'documents' => ['deleted_at'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column, "{$table}_{$column}_perf_index");
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex("{$table}_{$column}_perf_index");
                }
            });
        }
    }
};
