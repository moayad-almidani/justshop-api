<?php

namespace App\Console\Commands\Billing;

use App\Actions\Entitlement\RecomputeEntitlementsAction;
use App\Contracts\Billing\BillingProviderInterface;
use App\DTOs\Entitlement\RecomputeEntitlementsDTO;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:reconcile 
                            {--dry-run : Show drift without fixing}
                            {--subscription-id= : Reconcile specific subscription only}';

    protected $description = 'Reconcile subscription plans with Stripe to fix drift';

    public function handle(
        BillingProviderInterface $billingProvider,
        RecomputeEntitlementsAction $recomputeEntitlements
    ): int {
        $dryRun = $this->option('dry-run');
        $subscriptionId = $this->option('subscription-id');

        $this->info('🔄 Reconciling subscriptions with Stripe...');
        $this->newLine();

        $query = Subscription::whereNotNull('provider_subscription_id')
            ->whereIn('status', ['active', 'trialing']);

        if ($subscriptionId) {
            $query->where('id', $subscriptionId);
        }

        $total = 0;
        $drifts = 0;
        $fixed = 0;
        $errors = 0;

        $query->chunk(50, function ($subscriptions) use (
            $billingProvider,
            $recomputeEntitlements,
            $dryRun,
            &$total,
            &$drifts,
            &$fixed,
            &$errors
        ) {
            foreach ($subscriptions as $subscription) {
                $total++;

                try {
                    // Fetch actual state from Stripe
                    $stripeSubscription = $billingProvider->getSubscription(
                        $subscription->provider_subscription_id
                    );

                    if (!$stripeSubscription) {
                        $this->warn("⚠️  Subscription #{$subscription->id}: Not found on Stripe");
                        continue;
                    }

                    // Get Stripe price ID
                    $items = $stripeSubscription['items']['data'] ?? [];
                    if (empty($items)) {
                        $this->warn("⚠️  Subscription #{$subscription->id}: No items in Stripe subscription");
                        continue;
                    }

                    $stripePriceId = $items[0]['price']['id'] ?? null;
                    if (!$stripePriceId) {
                        $this->warn("⚠️  Subscription #{$subscription->id}: No price ID in Stripe");
                        continue;
                    }

                    // Find matching local plan
                    $actualPlan = Plan::whereHas('prices', function ($query) use ($stripePriceId) {
                        $query->where('provider_price_id', $stripePriceId);
                    })->first();

                    if (!$actualPlan) {
                        $this->warn("⚠️  Subscription #{$subscription->id}: No matching plan for price {$stripePriceId}");
                        continue;
                    }

                    // Check for drift
                    if ($actualPlan->id !== $subscription->plan_id) {
                        $drifts++;

                        $this->warn("❌ Drift detected: Subscription #{$subscription->id}");
                        $this->line("   Account: #{$subscription->billing_account_id}");
                        $localPlanName = data_get($subscription->plan, 'name.en', 'Unknown');
                        $stripePlanName = data_get($actualPlan, 'name.en', 'Unknown');
                        $this->line("   Local plan:  {$localPlanName} (ID: {$subscription->plan_id})");
                        $this->line("   Stripe plan: {$stripePlanName} (ID: {$actualPlan->id})");

                        if (!$dryRun) {
                            // Fix the drift
                            $subscription->update([
                                'plan_id' => $actualPlan->id,
                                'provider_synced_at' => now(),
                            ]);

                            // Recompute entitlements for all stores
                            $stores = Store::where('owner_id', $subscription->billingAccount->owner_user_id)->get();
                            
                            foreach ($stores as $store) {
                                $recomputeEntitlements->execute(
                                    new RecomputeEntitlementsDTO(
                                        billingAccountId: $subscription->billing_account_id,
                                        storeId: $store->id,
                                    )
                                );
                            }

                            $fixed++;
                            $this->info("   ✅ Fixed");

                            Log::channel('billing')->warning('subscription.drift.fixed', [
                                'subscription_id' => $subscription->id,
                                'billing_account_id' => $subscription->billing_account_id,
                                'from_plan_id' => $subscription->plan_id,
                                'to_plan_id' => $actualPlan->id,
                                'source' => 'reconciliation',
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("❌ Error processing subscription #{$subscription->id}: {$e->getMessage()}");
                    
                    Log::channel('billing')->error('subscription.reconcile.error', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->newLine();
        $this->info('📊 Reconciliation Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Subscriptions Checked', $total],
                ['Drifts Detected', $drifts],
                ['Drifts Fixed', $dryRun ? 'N/A (dry-run)' : $fixed],
                ['Errors', $errors],
            ]
        );

        if ($drifts > 0 && $dryRun) {
            $this->newLine();
            $this->warn('⚠️  Run without --dry-run to fix the drifts');
        }

        if ($drifts === 0 && $errors === 0) {
            $this->newLine();
            $this->info('✅ All subscriptions are in sync with Stripe!');
        }

        return self::SUCCESS;
    }
}
