# Nabaad Bank

Nabaad Bank is a full-featured core banking web application built with Laravel 12, Inertia.js, and Vue (via Breeze). It provides separate portals for bank staff (Admin) and customers, covering the core operations of a retail bank branch.

## Features

**Admin Portal**
- Dashboard with key branch/business metrics
- Customer management & KYC verification (document upload/review)
- Account management (opening, status, balances)
- Transactions, approvals, and cheque book / cheque processing
- Loan management with repayment schedules and payments
- Teller till & vault management, cash movements, replenishment requests
- Standing orders
- Business day / End-of-Day (EOD) processing
- Branch, public holiday, and system settings management
- Role & permission management (powered by Spatie Laravel Permission)
- Audit logs and reports (PDF/Excel export)
- Two-factor authentication for admin accounts

**Customer Portal**
- Self-registration and authentication
- Account overview and transactions
- Standing orders
- Loan applications
- Cheque requests
- Profile management

## Tech Stack

- [Laravel 12](https://laravel.com)
- [Inertia.js](https://inertiajs.com) + [Laravel Breeze](https://laravel.com/docs/starter-kits)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) — roles & permissions
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — authentication
- [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) — PDF exports
- [maatwebsite/excel](https://github.com/Maatwebsite/Laravel-Excel) — Excel exports
- SQLite (default) / MySQL

## Getting Started

### Requirements

- PHP ^8.2
- Composer
- Node.js & npm

### Installation

```bash
git clone https://github.com/ismahanmahamedabdulaahi-png/Nabaad-Bank-Management-System.git
cd Nabaad-Bank-Management-System

composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed

npm run build
php artisan serve
```

`composer setup` runs the steps above (install, env, key, migrate, npm) in one command.

For local development with hot-reload, the queue listener, and log tailing all at once, run:

```bash
composer dev
```

### Demo / Default Login

Running `php artisan migrate --seed` creates a default Super Admin account via `AdminUserSeeder`:

| Field    | Value                |
|----------|-----------------------|
| Staff ID | `STF-0001`             |
| Email    | `admin@nabaadbank.so`  |
| Password | `Admin@1234`            |

> **Security note:** This is a seeded demo account for local development/testing only. If you deploy this application anywhere publicly accessible, log in and change this password immediately (or remove/modify `AdminUserSeeder` before seeding), and rotate `APP_KEY`/database credentials in `.env` (which is git-ignored and never committed).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
