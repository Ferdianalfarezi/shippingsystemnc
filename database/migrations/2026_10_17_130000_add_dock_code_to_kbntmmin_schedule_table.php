<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kbntmmin_schedule', function (Blueprint $table) {
            // Kosong = berlaku untuk semua dock di cycle tsb (perilaku lama)
            $table->string('dock_code', 10)->nullable()->after('id');

            $table->dropUnique(['cycle']);
            $table->unique(['dock_code', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('kbntmmin_schedule', function (Blueprint $table) {
            $table->dropUnique(['dock_code', 'cycle']);
            $table->unique('cycle');
            $table->dropColumn('dock_code');
        });
    }
};
