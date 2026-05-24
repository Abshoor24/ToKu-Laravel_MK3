<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tambah setelah user_id, kalau kolom belum ada
            if (!Schema::hasColumn('transactions', 'name')) {
                $table->string('name')->after('user_id');
            }
            if (!Schema::hasColumn('transactions', 'phone')) {
                $table->string('phone')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['name', 'phone']);
        });
    }
};