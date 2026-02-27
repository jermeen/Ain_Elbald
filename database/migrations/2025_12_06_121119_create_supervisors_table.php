<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('supervisors', function (Blueprint $table) {
        $table->id('supervisor_id'); 
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->string('phone')->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('work_shift')->nullable();
        $table->string('job_title')->nullable();
        $table->string('department_name')->nullable();
        $table->string('department_number')->nullable();
        $table->string('password'); 
        $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisors');
    }
};
