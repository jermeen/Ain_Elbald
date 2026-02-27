<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admins';
    protected $primaryKey = 'Admin_id';

    protected $fillable = [
        'First_Name',
        'Last_Name',
        'Email',
        'Phone_Number',
        'Password',
    ];

    protected $hidden = [
        'Password',
    ];

    protected $casts = [
        'Password' => 'hashed',
    ];

    // ----------------------------------------------------
    // (Relationships)
    // ----------------------------------------------------

    // المسؤول يمكنه الإشراف على مستخدمين متعددين
    public function users()
    {
        return $this->hasMany(User::class, 'admin_id', 'Admin_id');
    }

    // المسؤول يمكنه فرز وتقسيم تقارير متعددة
    public function reports()
    {
        return $this->hasMany(Report::class, 'admin_id', 'Admin_id');
    }
}