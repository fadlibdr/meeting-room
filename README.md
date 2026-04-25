<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Development Login Credentials

After running `php artisan migrate:fresh --seed`, you can log in with these test accounts. **All passwords are `password`** — for development only, never use these in production.

| Role | Email | Notes |
|------|-------|-------|
| Super Admin | superadmin@bpjs-kesehatan.go.id | Full system access |
| System Admin | sysadmin@bpjs-kesehatan.go.id | RBAC + audit |
| GA Admin | ga.admin@bpjs-kesehatan.go.id | Manages rooms + GA approval queue |
| Unit Approver (SDM) | budi.santoso@bpjs-kesehatan.go.id | Approves bookings for SDM dan Umum unit |
| Unit Approver (IT) | siti.rahma@bpjs-kesehatan.go.id | Approves bookings for IT direktorat |
| Unit Approver (Kepesertaan) | ahmad.hidayat@bpjs-kesehatan.go.id | Approves bookings for Kepesertaan |
| Requester (BIRO-UMUM) | dewi.lestari@bpjs-kesehatan.go.id | Books rooms; needs Budi Santoso's approval |
| Requester (IT) | eko.prasetyo@bpjs-kesehatan.go.id | Books rooms; needs Siti Rahma's approval |
| Requester (Kepesertaan) | rina.wulandari@bpjs-kesehatan.go.id | Books rooms; needs Ahmad Hidayat's approval |
| Inactive Requester | hari.nugroho@bpjs-kesehatan.go.id | `is_active = false` — for middleware testing |

The seeder also produces 50 bookings spanning every status (draft, submitted, approved, rejected, cancelled, completed), 8 rooms with mixed approval modes, and 5 organizational units.

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
