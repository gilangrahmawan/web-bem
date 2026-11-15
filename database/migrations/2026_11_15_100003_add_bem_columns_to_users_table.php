<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            // Pembina tidak punya divisi, jadi nullable. Divisi yang masih punya
            // anggota tidak boleh dihapus (dijaga policy + restrict di DB).
            $table->foreignId('division_id')->nullable()->after('phone')
                ->constrained()->restrictOnDelete();
            $table->string('position')->nullable()->after('division_id');
            $table->string('photo')->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('photo')->index();
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->text('two_factor_secret')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn([
                'phone', 'position', 'photo', 'is_active',
                'must_change_password', 'two_factor_secret', 'last_login_at',
            ]);
        });
    }
};
