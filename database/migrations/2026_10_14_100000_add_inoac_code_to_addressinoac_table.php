<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addressinoac', function (Blueprint $table) {
            // kode INOAC yang ada di QR kanban (mis. RET-00-B01A), beda dari part_no customer
            $table->string('inoac_code')->nullable()->index()->after('part_no');
            $table->string('customer_code')->nullable()->after('inoac_code');
        });
    }

    public function down(): void
    {
        Schema::table('addressinoac', function (Blueprint $table) {
            $table->dropColumn(['inoac_code', 'customer_code']);
        });
    }
};
