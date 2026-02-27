<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
        $table->id('report_id');
        
       
        $table->string('title');
        $table->text('description');
        $table->enum('report_type', ['Internal', 'External'])->nullable(); // (مثال)
        $table->string('location_address')->nullable();
        $table->string('latitude_longitude')->nullable();
        $table->enum('priority_level', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
        $table->boolean('sorted')->default(false); 
        $table->enum('current_status', ['Pending', 'Assigned', 'In Progress', 'Completed', 'Canceled'])->default('Pending');
        $table->float('ai_confidence_score')->nullable(); 
        $table->date('resolution_date')->nullable(); 
        $table->dateTime('report_date')->useCurrent();
        $table->string('photo_url')->nullable(); 

        $table->unsignedBigInteger('user_id');
        $table->foreign('user_id')->references('User_id')->on('users')->onDelete('cascade');
        
      
        $table->unsignedBigInteger('admin_id')->nullable();
        $table->foreign('admin_id')->references('Admin_id')->on('admins')->onDelete('set null');
        
        
        $table->unsignedBigInteger('supervisor_id')->nullable();
        $table->foreign('supervisor_id')->references('supervisor_id')->on('supervisors')->onDelete('set null');
        
        $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
