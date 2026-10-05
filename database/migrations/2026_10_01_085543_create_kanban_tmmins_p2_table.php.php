<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_tmmins_p2', function (Blueprint $table) {
            $table->id();
            $table->string('qr_code')->nullable();
            $table->string('manifest_no')->nullable()->index();
            $table->string('keterangan')->nullable();
            $table->dateTime('departure_time')->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->string('dock_code')->nullable()->index();
            $table->string('part_address')->nullable();
            $table->string('part_no')->nullable()->index();
            $table->string('order_no')->nullable();
            $table->string('unique_no')->nullable();
            $table->integer('pcs')->default(0);
            $table->string('route')->nullable();
            $table->string('part_name')->nullable();
            $table->string('supplier')->nullable();
            $table->string('supplier_code')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('dock')->nullable();
            $table->string('cycle')->nullable();
            $table->string('address')->nullable();
            $table->integer('plo')->nullable();
            $table->dateTime('out_time')->nullable();
            $table->string('conveyance_no', 25)->nullable();
            $table->timestamp('last_upload_at')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_tmmins_p2');
    }
};