<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\Store;
use App\Services\Storefront\Runtime\RuntimeCacheService;
use Database\Seeders\Theme\DefaultThemeSeeder;
use Database\Seeders\Theme\RichThemeSeeder;
use Database\Seeders\Theme\StoreAssetsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            PermissionSeeder::class,
            PlansTableSeeder::class,
            StoreSeeder::class,
            TrialSubscriptionSeeder::class, // Add trial subscription for merchant-store
            StoreAddressSettingsSeeder::class, // Must run after StoreSeeder
            ShippingMethodsSeeder::class, // Must run after StoreSeeder
            PlatformUsersSeeder::class, // Add diverse test users for platform dashboard
            PlatformAuditLogsSeeder::class, // Add audit logs for platform dashboard
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            DemoStoreVolumeSeeder::class,
            DemoStorePresentationSeeder::class,
            FakeSalesSeeder::class,
            ReviewSeeder::class,
            SectionSchemaSeeder::class,
            DefaultTemplateSeeder::class,
            StorefrontRuntimeDevSeeder::class,
            CmsBlogSeeder::class,
            CmsDocumentationSeeder::class,
            CmsMarketingSeeder::class,
            // DefaultThemeSeeder::class, // Basic version
            StoreAssetsSeeder::class, // Must run before themes (for logo/favicon)
            CentralStoreMediaSeeder::class, // Mirror generated local brand assets to central store_media only when files exist
            StorySeeder::class, // Seed active demo stories after products and stores exist
            PromotionalBannerSeeder::class, // Seed public Marketplace Home carousel content
            RichThemeSeeder::class, // Rich version with multiple themes
            SystemTemplateSeeder::class, // Creates ThemeTemplate records (one per system page per theme)
            DefaultSectionSeeder::class, // Creates default ThemeSection, ThemeBlock, ThemeSectionGroup
        ]);

        $store = Store::query()->where('slug', 'merchant-store')->first();

        if ($store instanceof Store) {
            $invalidated = app(RuntimeCacheService::class)->invalidateTenantArtifacts($store);

            $this->command?->info(sprintf(
                'Storefront runtime cache invalidated for %s (%d entries).',
                $store->slug,
                $invalidated,
            ));
        }

        // ── Fix entitlement counters after seeding ──────────────────────
        // Seeders may create stores before billing_accounts exist, causing drift.
        // Reconcile to sync counters with actual data.
        $this->call(ReconcileEntitlementsSeeder::class);

        // ── Sync Stripe catalog after seeding ────────────────────────────
        // Sync plans and prices to Stripe after all seeders complete
        if (env('BILLING_PROVIDER') === 'stripe') {
            $this->command?->info('🔄 Syncing Stripe catalog...');
            
            try {
                \Illuminate\Support\Facades\Artisan::call('billing:sync-stripe-catalog');
                $output = \Illuminate\Support\Facades\Artisan::output();
                $this->command?->info($output);
            } catch (\Exception $e) {
                $this->command?->warn('⚠️  Failed to sync Stripe catalog: ' . $e->getMessage());
                $this->command?->warn('   You can manually run: php artisan billing:sync-stripe-catalog');
            }
        }
    }
}
