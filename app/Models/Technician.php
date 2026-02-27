<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // ⬅️ تغيير مهم للمصادقة
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Technician extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'technicians';
    protected $primaryKey = 'technician_id'; 

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'work_shift',
        'job_title',
        'specialization',
        'hire_date',
        'status',
        'supervisor_id', 
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
    ];

    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------

    // 1. علاقة الانتماء للمشرف (Belongs To Relationship)
    public function supervisor()
    {
        // الفني ينتمي إلى مشرف واحد
        return $this->belongsTo(Supervisor::class, 'supervisor_id', 'supervisor_id');
    }

    // 2. علاقة التقارير (One-to-Many)
    public function reports()
    {
        // الفني مسؤول عن تقارير متعددة
        return $this->hasMany(Report::class, 'technician_id', 'technician_id');
    }
}