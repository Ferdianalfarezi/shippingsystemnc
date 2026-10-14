<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kbntmmin_print_logs', function (Blueprint $table) {
            $table->string('printer_name')->nullable()->after('manifest_no');
            $table->string('status', 20)->default('printed')->after('printer_name'); // printed | failed
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kbntmmin_print_logs', function (Blueprint $table) {
            $table->dropColumn(['printer_name', 'status', 'error_message']);
        });
    }
};