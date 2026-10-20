<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kbntmmin_schedule', function (Blueprint $table) {
            // Kosong = berlaku untuk semua route (perilaku sebelumnya)
            $table->string('route', 20)->nullable()->after('id');

            $table->dropUnique(['dock_code', 'cycle']);
            $table->unique(['route', 'dock_code', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('kbntmmin_schedule', function (Blueprint $table) {
            $table->dropUnique(['route', 'dock_code', 'cycle']);
            $table->unique(['dock_code', 'cycle']);
            $table->dropColumn('route');
        });
    }
};
