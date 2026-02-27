<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
        $table->id('Admin_id'); 
        $table->string('First_Name');
        $table->string('Last_Name');
        $table->string('Email')->unique();
        $table->string('Phone_Number')->nullable();
        $table->string('Password'); 
        $table->timestamps();

        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
