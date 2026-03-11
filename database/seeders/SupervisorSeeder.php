<?php

namespace Database\Seeders;

use App\Models\Supervisor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SupervisorSeeder extends Seeder
{
    public function run(): void
    {
        // أسماء الحكومات الـ 11ا
        $departments = [
            'Electricity', 
            'Water', 
            'Fire fighting', 
            'Sewage', 
            'Roads', 
            'Traffic', 
            'Cleaning & Environment', 
            'Public Facilities Maintenance', 
            'Parks & Trees', 
            'Illegal Construction', 
            'General Emergency'
        ];

        foreach ($departments as $index => $dept) {
            // تنظيف الاسم عشان نعمل منه إيميل (مثلاً: Roads -> roads@system.com)
            $emailPrefix = strtolower(str_replace([' ', '&'], ['_', 'n'], $dept));

            Supervisor::updateOrCreate(
                ['email' => $emailPrefix . '@system.com'], // عشان لو شغلتيه مرتين ميكررش البيانات
                [
                    'first_name' => 'Supervisor',
                    'last_name' => $dept,
                    'phone' => '010123456' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'job_title' => 'Department Head',
                    'department_name' => $dept,
                    'department_number' => 'GOV-' . (100 + $index + 1),
                    'password' => Hash::make('password123'), // الباسورد الموحد لكل المشرفين
                    'work_shift' => 'Morning',
                ]
            );
        }
    }
}