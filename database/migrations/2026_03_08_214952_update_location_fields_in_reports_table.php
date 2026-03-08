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
        // هنشيل الحقل القديم ونضيف حقلين أدق للخريطة
        $table->dropColumn('latitude_longitude');
        $table->decimal('latitude', 10, 8)->nullable()->after('location_address');
        $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            //
        });
    }
};
