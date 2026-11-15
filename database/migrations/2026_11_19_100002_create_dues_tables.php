<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iuran tambahan (PDH, tabungan jalan-jalan, dll.) + peserta + pembayaran (boleh dicicil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedInteger('amount');
            $table->string('type', 20)->index();
            $table->date('due_date');
            $table->boolean('applies_to_all')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('due_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['due_id', 'user_id']);
        });

        Schema::create('due_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamp('paid_at');
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['due_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_payments');
        Schema::dropIfExists('due_members');
        Schema::dropIfExists('dues');
    }
};
