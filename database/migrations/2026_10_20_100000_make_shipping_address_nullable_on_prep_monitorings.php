<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur Preparation (panah): shipping baru dipilih setelah DN terakhir di-prep,
     * jadi selama progress (mis. 1/4) shipping_address masih kosong.
     */
    public function up(): void
    {
        Schema::table('prep_monitorings', function (Blueprint $table) {
            $table->string('shipping_address', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('prep_monitorings')->whereNull('shipping_address')->update(['shipping_address' => '']);

        Schema::table('prep_monitorings', function (Blueprint $table) {
            $table->string('shipping_address', 50)->nullable(false)->change();
        });
    }
};
