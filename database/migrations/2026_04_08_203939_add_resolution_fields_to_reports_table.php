<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
        $table->string('after_photo_url')->nullable(); // صورة بعد الإصلاح
        $table->text('technician_final_note')->nullable(); // تعليق الفني النهائي
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
        $table->dropColumn(['after_photo_url', 'technician_final_note']);
        });
    }
};
