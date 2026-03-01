<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities


























# 🛡️ Ain Elbald API Documentation

هذا المشروع يوفر نظاماً لإدارة البلاغات (Reports) لمساعدة المواطنين.

## 🚀 الروابط الأساسية (Endpoints)

| الوظيفة | الرابط (URL) | الطريقة (Method) | المتطلبات (Headers) |
| :--- | :--- | :--- | :--- |
| **إنشاء بلاغ** | `/api/reports/create` | `POST` | `Bearer Token` + `multipart/form-data` |
| **عرض بلاغاتي** | `/api/reports/my-tickets` | `GET` | `Bearer Token` |
| **تتبع بلاغ** | `/api/reports/track/{id}` | `GET` | `Bearer Token` |

## 🛠️ تعليمات للتشغيل (للمطورين)
بعد سحب الكود (Pull)، يرجى تنفيذ الأوامر التالية بالترتيب:

1. تحديث المكتبات: `composer install`
2. تحديث قاعدة البيانات: `php artisan migrate`
3. تفعيل رابط الصور: `php artisan storage:link`

## 📸 مثال لبيانات إنشاء بلاغ (Body)
- `title`: (Text) عنوان البلاغ
- `description`: (Text) وصف المشكلة
- `image`: (File) صورة توضيحية


http://127.0.0.1:8000/api/user/register   singup
"first_name": "Abeer",
    "last_name": "Test",
    "email": "abeer.test@example.com",
    "password": "password123",
    "password_confirmation": "password123"



http://127.0.0.1:8000/api/user/login      login
"email": "abeer.test@example.com",
    "password": "password123"


http://127.0.0.1:8000/api/user
Authorization:  Bearer


http://127.0.0.1:8000/api/user/password/forgot      forgot password
"email": "abeer.test@example.com"


http://127.0.0.1:8000/api/user/password/verify       verifypassword
"email": "abeer.test@example.com",
    "code": "الرمز المحفوظ من الخطوة 4"


http://127.0.0.1:8000/api/user/password/reset        resetpassword
"email": "abeer.test@example.com",
    "token": "الرمز المحفوظ من الخطوة 4",
    "password": "newpassword456",
   "password_confirmation": "newpassword456 

   
إنشاء بلاغ (Create Ticket) - "هنا بنجرب الـ Controller الجديد"
الرابط: http://127.0.0.1:8000/api/reports/create

الطريقة: POST

الـ Headers:

Authorization: Bearer [حطي الـ Token هنا]

Accept: application/json

الـ Body (نوعه form-data):

title: كسر في خط مياه

description: يوجد تسريب كبير في المنطقة الرابعة

image: (اختاري ملف صورة من جهازك)

الهدف: حفظ البلاغ في الداتابيز وربطه بـ User_id الخاص بيكِ.

4. عرض بلاغاتي (My Tickets)
الرابط: http://127.0.0.1:8000/api/reports/my-tickets

الطريقة: GET

الـ Headers:

Authorization: Bearer [حطي الـ Token هنا]

الهدف: هيظهرلك قائمة (Array) بكل البلاغات اللي إنتِ رفعتيها قبل كدة. خدي منها رقم الـ report_id للخطوة الجاية.

5. تتبع بلاغ (Track Ticket)
الرابط: http://127.0.0.1:8000/api/reports/track/1 (غيري رقم 1 برقم البلاغ اللي طلعلك)

الطريقة: GET

الـ Headers:

Authorization: Bearer [حطي الـ Token هنا]

الهدف: بيعرض تفاصيل البلاغ وتحديثات الحالة اللي الأدمن هيعملها مستقبلاً


If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
