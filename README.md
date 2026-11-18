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

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## cPanel deployment

Set PHP limits in the domain's `php.ini` or `.user.ini` to values appropriate
for your deployment and the configured upload limits. For example:

```ini
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 300
memory_limit = 512M
```

Create the public storage link with `php artisan storage:link`. If cPanel does
not permit Artisan to create symlinks, create the equivalent `public/storage`
symlink in its File Manager or terminal. Keep the `private` disk outside the
web root and configure the `cloud` disk credentials for the S3-compatible
video bucket.

Configure cron to run the Laravel scheduler every minute:

```cron
* * * * * cd /home/USER/path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

Run a queue worker under cPanel's process manager (or a supervised cron
worker) for queued image conversions and other queued jobs:

```sh
php artisan queue:work --sleep=3 --tries=3 --timeout=300
```

## Performance

### Benchmark dataset

`PerformanceSeeder` builds a repeatable dataset for profiling: 5,000 students,
100,000 invoices, 150,000 posted journal entries and 300,000 balanced journal
lines (all rows are prefixed `PERF-`/`PINV-`):

```sh
php artisan db:seed --class=PerformanceSeeder
```

Run it only on a disposable development database. It writes in chunks and does
not modify existing records.

### Benchmark command

```sh
php artisan performance:benchmark            # default 30-day window
php artisan performance:benchmark --days=90 --explain
```

The command flushes the cache, times the heavy report queries (ledger, trial
balance, cash book, daily collection, month-wise collection, student due list,
dashboard metrics, students by course) and reports elapsed time and query
count for each. `--explain` prints the MySQL `EXPLAIN` plan for the heaviest
queries so you can confirm index usage.

Measured on the seeded dataset (cold cache / warm cache):

| Query | Cold | Warm |
| --- | --- | --- |
| Ledger | ~2.3 s | ~34 ms |
| Trial balance | ~2.1 s | <1 ms |
| Cash book | ~3.1 s | ~40 ms |
| Student due list | ~125 ms | ~12 ms |
| Daily / month-wise collection | <10 ms | <10 ms |

Cold timings are first-hit aggregate scans. Reports are cached after the first
run, so repeat views are cheap; the `journal_entries(status, voucher_type,
entry_date)`, `receipts(student_id, status, date)` and
`student_invoices(batch_id, status, due)` indexes keep the range scans
sargable.

### Queues

Long-running work is dispatched to the queue and must be processed by a
worker (see the cPanel section above):

- `GenerateBatchInvoicesJob` – batch invoice runs.
- `GenerateExamFeesJob` – exam fee generation in chunks.
- `GenerateTeacherSalariesJob` – salary generation.
- `ImportExamMarksCsvJob` – exam marks CSV imports.
- `ExportFinancialReportJob` – large financial report PDF/CSV exports.
- `SendNotificationBatch` – bulk notifications.

Short-lived, user-triggered PDFs (single invoice/receipt/admit card) are still
generated synchronously. Image uploads convert to WebP before storage; Media
Library derivatives run through the queue.

## Student registration

Public visitors can open `/{locale}/register` (linked from the homepage hero,
the admission CTA and the navbar) and submit their identity, present/permanent
address and academic background. Each submission is stored in
`student_registrations` with a `REG-…` number.

Admins review submissions in **Admissions → Student registrations**. Approving
creates the student record and, when an email address was supplied, a student
user account whose credentials are emailed automatically. Approval does **not**
create an enrollment or an invoice — those remain separate admission/finance
steps.

## REST API

The Flutter app talks to the versioned JSON API under `/api/v1`, authenticated
with Sanctum bearer tokens and limited to 60 requests/minute per user or IP.
See [docs/api-v1.md](docs/api-v1.md) for endpoints, payloads, pagination and
error handling, and `tests/Feature/ApiV1Test.php` for executable examples.
