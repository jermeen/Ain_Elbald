<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Supervisor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'supervisors';
    protected $primaryKey = 'supervisor_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'work_shift',
        'job_title',
        'department_name',
        'department_number',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    // 🔨 تحويل الأعمدة (Casts)
    protected $casts = [
        'password' => 'hashed',
        'date_of_birth' => 'date',
    ];

    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------

    // المشرف مسؤول عن فنيين متعددين (One-to-Many)
    public function technicians()
    {
        // المشرف لديه فنيين (Technicians) متعددين، مرتبطين بعمود supervisor_id
        return $this->hasMany(Technician::class, 'supervisor_id', 'supervisor_id');
    }

    // المشرف مسؤول عن تقارير متعددة (في حال تم تعيينه عليها)
    public function reports()
    {
        return $this->hasMany(Report::class, 'supervisor_id', 'supervisor_id');
    }
}
