<?php

use App\Console\Commands\Billing\BillingApplyScheduledDowngradesCommand;
use App\Console\Commands\Billing\BillingReconcileCommand;
use App\Console\Commands\Billing\ExpireStaleIncompleteSubscriptionsCommand;
use App\Console\Commands\Billing\ExpireTrialsCommand;
use App\Console\Commands\Billing\ExpireGracePeriodsCommand;
use App\Console\Commands\Order\ExpireAbandonedOrdersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Billing Lifecycle Scheduled Tasks
Schedule::command(ExpireTrialsCommand::class)
    ->daily()
    ->at('00:00')
    ->timezone('UTC')
    ->name('billing-expire-trials')
    ->withoutOverlapping(3600) // Prevent overlapping for 1 hour
    ->runInBackground();

Schedule::command(ExpireGracePeriodsCommand::class)
    ->hourly()
    ->name('billing-expire-grace-periods')
    ->withoutOverlapping(1800) // Prevent overlapping for 30 minutes
    ->runInBackground();

Schedule::command(ExpireStaleIncompleteSubscriptionsCommand::class)
    ->hourly()
    ->name('billing-expire-stale-incomplete')
    ->withoutOverlapping(1800) // Prevent overlapping for 30 minutes
    ->runInBackground();

Schedule::command(BillingApplyScheduledDowngradesCommand::class)
    ->daily()
    ->at('00:10')
    ->timezone('UTC')
    ->name('billing-apply-scheduled-downgrades')
    ->withoutOverlapping(3600) // Prevent overlapping for 1 hour
    ->runInBackground();

// Billing Reconciliation — Detect and fix drift between local DB and Stripe
Schedule::command(BillingReconcileCommand::class)
    ->daily()
    ->at('03:00')
    ->timezone('UTC')
    ->name('billing-reconcile')
    ->withoutOverlapping(7200) // Prevent overlapping for 2 hours
    ->runInBackground();

// Storefront Checkout — Cancel abandoned draft orders (checkout started but
// payment never completed) so they don't linger forever as "Pending" in the
// customer's order history, and free up their Stripe PaymentIntents.
Schedule::command(ExpireAbandonedOrdersCommand::class, ['--minutes=60'])
    ->everyFifteenMinutes()
    ->name('orders-expire-abandoned')
    ->withoutOverlapping(900) // Prevent overlapping for 15 minutes
    ->runInBackground();

// Central media: detach on writes, delete bytes only after a grace period.
Schedule::command(\App\Console\Commands\Media\PruneOrphanMediaCommand::class)
    ->dailyAt('02:30')
    ->name('media-prune-orphans')
    ->withoutOverlapping();
