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
        // بنتشيك لو العمود مش موجود أصلاً، ضيفه
        if (!Schema::hasColumn('reports', 'technician_id')) {
            $table->unsignedBigInteger('technician_id')->nullable()->after('supervisor_id');
        }

        if (!Schema::hasColumn('reports', 'supervisor_comment')) {
            $table->text('supervisor_comment')->nullable()->after('technician_id');
        }

        if (!Schema::hasColumn('reports', 'target_hours')) {
            $table->integer('target_hours')->nullable()->after('supervisor_comment');
        }
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
        $table->dropForeign(['technician_id']);
        $table->dropColumn(['technician_id', 'supervisor_comment', 'target_hours']);
        });
    }
};
