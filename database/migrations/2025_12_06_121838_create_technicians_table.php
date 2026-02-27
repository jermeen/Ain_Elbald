<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
           $table->id('technician_id'); 
        
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->string('phone')->nullable();
        $table->string('address')->nullable();
        $table->date('date_of_birth')->nullable();

        $table->string('work_shift')->nullable();
        $table->string('job_title')->nullable();
        $table->string('specialization')->nullable();
        $table->date('hire_date')->nullable();
        $table->enum('status', ['Active', 'On Leave', 'Terminated'])->default('Active'); // أمثلة لحالات الفني
       
        $table->unsignedBigInteger('supervisor_id');
        $table->foreign('supervisor_id')
              ->references('supervisor_id')
              ->on('supervisors')         
              ->onDelete('restrict'); // نستخدم restrict لمنع حذف المشرف قبل نقل الفنيين
              
        $table->string('password'); 
        $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
