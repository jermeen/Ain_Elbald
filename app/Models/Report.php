<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';
    protected $primaryKey = 'report_id'; 

    protected $fillable = [
        'title',
        'description',
        'report_type',
        'location_address',
        'latitude_longitude', // العمود الموحد
        'priority_level',
        'sorted',
        'current_status',
        'ai_confidence_score',
        'resolution_date',
        'report_date',
        'photo_url',
        'user_id', 
        'admin_id',
        'supervisor_id',
    ];

    protected $casts = [
        'report_date' => 'datetime',
        'resolution_date' => 'date',
        'sorted' => 'boolean',
        'ai_confidence_score' => 'float',
    ];
    
    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------
    
    // 1. علاقة الإبلاغ (Belongs To User)
    public function user()
    {
        // التقرير ينتمي إلى مستخدم واحد أرسله
        return $this->belongsTo(User::class, 'user_id', 'User_id');
    }

    // 2. علاقة تعيين المسؤول (Belongs To Admin)
    public function admin()
    {
        // التقرير قد يتم فرزه من قبل مسؤول واحد
        return $this->belongsTo(Admin::class, 'admin_id', 'Admin_id');
    }
    
    // 3. علاقة تعيين المشرف (Belongs To Supervisor)
    public function supervisor()
    {
        // التقرير قد يُعيَّن لمشرف واحد للمتابعة
        return $this->belongsTo(Supervisor::class, 'supervisor_id', 'supervisor_id');
    }

    // 4. علاقة تحديثات الحالة (Has Many Status Updates)
    public function statusUpdates()
    {
        // التقرير لديه تحديثات حالة متعددة
        return $this->hasMany(ReportStatusUpdate::class, 'report_id', 'report_id');
    }
}