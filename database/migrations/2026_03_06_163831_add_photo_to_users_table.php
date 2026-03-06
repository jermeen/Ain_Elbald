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
        Schema::table('users', function (Blueprint $table) {
            // إضافة عمود الصورة بعد عمود الإيميل أو في الآخر
            $table->string('photo')->nullable()->after('email'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('users', function (Blueprint $table) {
            // حذف العمود لو حبين نرجع في كلامنا
            $table->dropColumn('photo');
        });
    }
};
