<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('report_status_updates', function (Blueprint $table) {
        $table->id('update_id'); 
        
        $table->unsignedBigInteger('report_id');
        $table->foreign('report_id')->references('report_id')->on('reports')->onDelete('cascade');
        //  من قام بالتحديث (قد يكون مستخدماً عادياً، أو مشرفاً، أو فنياً/إدارياً
        // ID المستخدم الذي أجرى التحديث (قابلة للفراغ إذا كان التحديث من فني/مشرف)
        $table->unsignedBigInteger('user_id')->nullable(); 
        $table->foreign('user_id')->references('User_id')->on('users')->onDelete('set null');
        // ID المشرف الذي أجرى التحديث (قابلة للفراغ إذا كان التحديث من مستخدم/فني)
        $table->unsignedBigInteger('supervisor_id')->nullable();
        $table->foreign('supervisor_id')->references('supervisor_id')->on('supervisors')->onDelete('set null');
        //  تفاصيل التحديث
        $table->string('new_status');
        $table->enum('update_type', ['Status Change', 'Comment', 'Assignment', 'Resolution'])->default('Status Change');
        $table->text('content')->nullable();
        $table->text('notes')->nullable();
        $table->integer('time_spent_days')->nullable(); // المدة المستغرقة بالأيام
        $table->dateTime('timestamp')->useCurrent();
        $table->timestamps();
        });
    }

 
    public function down(): void
    {
        Schema::dropIfExists('report_status_updates');
    }
};
