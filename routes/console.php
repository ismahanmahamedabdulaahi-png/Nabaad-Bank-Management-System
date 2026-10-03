<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── EOD Master Process (runs all sub-tasks in order) ─────────────────────────
// Runs at midnight; individual commands kept available for manual use
Schedule::command('eod:run')->dailyAt('00:01')->withoutOverlapping();

// ── Approval Escalation ───────────────────────────────────────────────────────
// Flags transactions pending approval for over 24 hours so they surface to Compliance/Super Admin
Schedule::command('approvals:escalate-stale')->hourly()->withoutOverlapping();

// ── Alerts ────────────────────────────────────────────────────────────────────
// Notifies KYC reviewers about documents expiring within 14 days
Schedule::command('alerts:check')->dailyAt('07:30')->withoutOverlapping();

// ── Service Codes ─────────────────────────────────────────────────────────────
// Expires cardless withdrawal/deposit codes past their TTL
Schedule::command('service-codes:expire')->everyFifteenMinutes()->withoutOverlapping();

// ── AML / Compliance ─────────────────────────────────────────────────────────
// Flags customers whose sub-threshold transactions look like structuring
Schedule::command('compliance:detect-structuring')->dailyAt('02:00')->withoutOverlapping();

// Individual commands still available for manual runs / debugging
// Schedule::command('standing-orders:execute')->dailyAt('08:00');
// Schedule::command('loans:mark-overdue')->dailyAt('07:00');
// Schedule::command('cheques:process-clearing')->dailyAt('09:00');
