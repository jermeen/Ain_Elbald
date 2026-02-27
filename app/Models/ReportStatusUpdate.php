<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportStatusUpdate extends Model
{
    use HasFactory;

    protected $table = 'report_status_updates';
    protected $primaryKey = 'update_id'; 

    protected $fillable = [
        'report_id',
        'user_id',
        'supervisor_id',
        'new_status',
        'update_type',
        'content',
        'notes',
        'time_spent_days',
        'timestamp', // تم استخدام useCurrent() في Migration
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
    
    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------
    
    // 1. علاقة التقرير (Belongs To Report)
    public function report()
    {
        // التحديث ينتمي إلى تقرير واحد
        return $this->belongsTo(Report::class, 'report_id', 'report_id');
    }

    // 2. علاقة المستخدم المُحدِّث (Belongs To User)
    public function user()
    {
        // الشخص الذي قام بالتحديث قد يكون مستخدماً عادياً
        return $this->belongsTo(User::class, 'user_id', 'User_id');
    }
    
    // 3. علاقة المشرف المُحدِّث (Belongs To Supervisor)
    public function supervisor()
    {
        // الشخص الذي قام بالتحديث قد يكون مشرفاً
        return $this->belongsTo(Supervisor::class, 'supervisor_id', 'supervisor_id');
    }
}