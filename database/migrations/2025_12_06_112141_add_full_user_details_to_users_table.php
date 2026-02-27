<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
        
      
        $table->dropColumn('name');

        
        $table->string('first_name'); 
        $table->string('last_name');  
        $table->string('phone')->nullable()->unique();
        $table->string('location')->nullable();
        $table->date('date_of_birth')->nullable();
        
        
        $table->boolean('is_verified')->default(false);
        $table->string('verification_code')->nullable();
        $table->timestamp('code_expires_at')->nullable();
        
        $table->unsignedBigInteger('admin_id')->nullable(); 
        $table->foreign('admin_id')->references('Admin_id')->on('admins')->onDelete('cascade');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
         $table->string('name'); 
        $table->dropForeign(['admin_id']);
        $table->dropColumn(['first_name', 'last_name', 'phone', 'location', 'date_of_birth', 'is_verified', 'verification_code', 'admin_id']);
        });
    }
};
