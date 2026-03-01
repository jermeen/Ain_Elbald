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
        'latitude_longitude',
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

    /**
     * تحديث تلقائي لتاريخ البلاغ عند الإنشاء
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->report_date = $model->report_date ?? now();
        });
    }

    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------
    
    // 1. التقرير ينتمي إلى مستخدم واحد أرسله
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'User_id');
    }

    // 2. التقرير قد يتم فرزه من قبل مسؤول واحد
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'Admin_id');
    }
    
    // 3. التقرير قد يُعيَّن لمشرف واحد للمتابعة
    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id', 'supervisor_id');
    }

    // 4. التقرير لديه تحديثات حالة متعددة (للتتبع)
    public function statusUpdates()
    {
        return $this->hasMany(ReportStatusUpdate::class, 'report_id', 'report_id');
    }
}