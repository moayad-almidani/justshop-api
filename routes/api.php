<?php

use App\Http\Controllers\Api\Shared\Auth\Preparation\CsrfOwnershipPreparationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Context-Based Architecture (v1)
|--------------------------------------------------------------------------
|
| The API is organized into explicit application contexts based on the actor
| and their intent. Each context has its own middleware, authentication,
| and identity boundaries.
|
*/

Route::get('/debug/sanctum', function () {
    $request = request();
    
    return response()->json([
        'stateful_domains' => config('sanctum.stateful'),
        'request_origin' => $request->header('origin'),
        'request_referer' => $request->header('referer'),
        'request_host' => $request->getHost(),
        'cookies_received' => array_keys($request->cookies->all()),
        'has_ecommerce_session' => $request->cookies->has('ecommerce_session'),
        'has_xsrf_token' => $request->cookies->has('XSRF-TOKEN'),
        'session_driver' => config('session.driver'),
        'session_id' => session()->getId(),
        'session_keys' => array_keys(session()->all()),
        'auth_merchant_check' => auth('merchant')->check(),
        'auth_merchant_id' => auth('merchant')->id(),
        'auth_web_check' => auth('web')->check(),
        'auth_web_id' => auth('web')->id(),
        'default_guard' => config('auth.defaults.guard'),
        'all_guards' => array_keys(config('auth.guards')),
    ]);
});




// ── 1. PLATFORM CONTEXT ──────────────────────────────────────────────────
// Internal SaaS operator tooling (SUPER_ADMIN only).

// Platform Authentication Routes (without platform.authority middleware)

Route::prefix('/v1/platform')
    ->middleware([
       //  'web',
        'auth:sanctum',
        'identity.route:platform,platform,enforce',
        'platform.context',
    ])
    ->group(function (): void {
        require 'api/v1/platform/auth.php';
    });

// Other Platform Routes (with platform.authority middleware)
Route::prefix('/v1/platform')
    ->middleware([
       //  'web',
        'auth:sanctum',
        'identity.route:platform,platform,enforce',
        'platform.context',
        'platform.authority:platform_admin',
    ])
    ->group(function (): void {
        require 'api/v1/platform/platform.php';
        require 'api/v1/platform/leads.php';
        require 'api/v1/platform/billing/plans.php';
        require 'api/v1/platform/billing/subscriptions.php';
        require 'api/v1/platform/cms/blog.php';
        require 'api/v1/platform/cms/marketing-pages.php';
        require 'api/v1/platform/cms/documentation.php';
        require 'api/v1/platform/notifications.php';
    });

// ── 2. MERCHANT CONTEXT ──────────────────────────────────────────────────
// Tenant/store administration (Store owners and staff).
Route::get('/v1/me', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'bootstrap'])
    ->middleware([
       //  'web',
        'auth:sanctum',
        'identity.route:merchant_users,merchant,enforce',
        'api.deprecated',
    ])
    ->name('merchant.me.legacy');

Route::prefix('/v1/stores')
    ->middleware([
       //  'web',
        'api.deprecated',
    ])
    ->group(function (): void {
        Route::post('/', [\App\Http\Controllers\Api\Merchant\StoreController::class, 'create'])
            ->middleware(['auth:sanctum', 'identity.route:merchant_users,merchant,enforce'])
            ->name('merchant.stores.legacy.create');

        Route::get('/{store}/provisioning-status', [\App\Http\Controllers\Api\Merchant\ProvisioningStatusController::class, '__invoke'])
            ->middleware(['auth:sanctum', 'identity.route:merchant_users,merchant,enforce'])
            ->name('merchant.stores.legacy.provisioning-status');

        Route::middleware(['auth:sanctum', 'identity.route:merchant_admin,merchant,enforce', 'store.context'])
            ->group(function (): void {
                Route::get('/{store}', [\App\Http\Controllers\Api\Merchant\StoreController::class, 'show'])
                    ->name('merchant.stores.legacy.show');

                Route::put('/{store}', [\App\Http\Controllers\Api\Merchant\StoreController::class, 'update'])
                    ->name('merchant.stores.legacy.update');
            });
    });

Route::prefix('/v1/admin/stores/{store}')
    ->middleware([
       //  'web',
        'api.deprecated',
        'auth:sanctum',
        'identity.route:merchant_admin,merchant,enforce',
        'store.context',
    ])
    ->group(function (): void {
        Route::prefix('/dashboard')->group(function (): void {
            Route::get('/stats', [\App\Http\Controllers\Api\Merchant\AdminDashboardController::class, 'stats'])
                ->name('merchant.admin.legacy.dashboard.stats');

            Route::get('/recent-orders', [\App\Http\Controllers\Api\Merchant\AdminDashboardController::class, 'recentOrders'])
                ->name('merchant.admin.legacy.dashboard.recent-orders');

            Route::get('/top-products', [\App\Http\Controllers\Api\Merchant\AdminDashboardController::class, 'topProducts'])
                ->name('merchant.admin.legacy.dashboard.top-products');
        });

        Route::prefix('/products')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'index'])
                ->name('merchant.admin.legacy.products.index');

            Route::get('/{product}', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'show'])
                ->name('merchant.admin.legacy.products.show');

            Route::post('/', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'store'])
                ->name('merchant.admin.legacy.products.store');

            Route::patch('/{product}', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'update'])
                ->name('merchant.admin.legacy.products.update');

            Route::delete('/{product}', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'destroy'])
                ->name('merchant.admin.legacy.products.destroy');

            Route::patch('/{product}/restore', [\App\Http\Controllers\Api\Merchant\AdminProductController::class, 'restore'])
                ->name('merchant.admin.legacy.products.restore');
        });
    });

Route::prefix('/v1')
    ->middleware([
       //  'web',
        'api.deprecated',
    ])
    ->group(function (): void {
        Route::get('/store-slug/check', [\App\Http\Controllers\Api\Merchant\StoreSlugController::class, '__invoke'])
            ->middleware(['auth:sanctum', 'identity.route:merchant_users,merchant,enforce'])
            ->name('merchant.stores.legacy.slug-check');
    });

Route::prefix('/v1/users')
    ->middleware([
       //  'web',
        'identity.route:merchant_users,merchant,observe',
        'api.deprecated',
    ])
    ->group(function (): void {
        Route::get('/bootstrap', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'bootstrap'])
            ->middleware(['auth:sanctum'])
            ->name('merchant.users.legacy.bootstrap');

        Route::prefix('/auth')
            ->name('merchant.users.legacy.auth.')
            ->group(function (): void {
                Route::get('/bootstrap', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'bootstrap'])
                    ->middleware(['auth:sanctum'])
                    ->name('bootstrap');

                Route::get('/me', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'me'])
                    ->middleware(['auth:sanctum'])
                    ->name('me');

                Route::post('/register', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'register'])
                    ->name('register');

                Route::post('/login', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'login'])
                    ->middleware('throttle:login')
                    ->name('login');

                // Keep the legacy Google OAuth callback URL working for existing provider config.
                Route::get('/google/redirect', [\App\Http\Controllers\Api\Merchant\SocialAuthController::class, 'redirect'])
                    ->name('google.redirect');

                Route::get('/google/callback', [\App\Http\Controllers\Api\Merchant\SocialAuthController::class, 'callback'])
                    ->middleware('web')
                    ->name('google.callback');

                Route::post('/logout', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'logout'])
                    ->middleware(['auth:sanctum'])
                    ->name('logout');

                Route::post('/password/validate-token', [\App\Http\Controllers\Api\Merchant\PasswordResetController::class, 'validateToken'])
                    ->name('password.validate-token');

                Route::patch('/active-store', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'updateActiveStore'])
                    ->middleware(['auth:sanctum', 'verified'])
                    ->name('active-store.update');
            });

        Route::prefix('/sessions')
            ->middleware(['auth:sanctum'])
            ->name('merchant.users.legacy.sessions.')
            ->group(function (): void {
                Route::get('/', [\App\Http\Controllers\Api\Merchant\SessionController::class, 'index'])
                    ->name('index');

                Route::delete('/', [\App\Http\Controllers\Api\Merchant\SessionController::class, 'destroyAll'])
                    ->name('destroy-all');

                Route::delete('/{id}', [\App\Http\Controllers\Api\Merchant\SessionController::class, 'destroy'])
                    ->name('destroy');
            });
    });

Route::prefix('/v1/merchant')
    ->middleware([
       //  'web',
        'identity.route:merchant_users,merchant,enforce',
    ])
    ->group(function (): void {
        // Canonical bootstrap endpoint
        Route::get('/me', [\App\Http\Controllers\Api\Merchant\AuthController::class, 'bootstrap'])
            ->middleware(['auth:sanctum','identity.route:merchant_users,merchant,enforce']) // disabled temporarily, use 'identity.route:merchant_users,merchant,enforce'
            ->name('merchant.me');

        require 'api/v1/merchant/auth.php';
        require 'api/v1/merchant/profile.php';
        require 'api/v1/merchant/category.php';
        require 'api/v1/merchant/admin.php';
        require 'api/v1/merchant/stores.php';
        require 'api/v1/merchant/theme.php';
        require 'api/v1/merchant/billing.php'; // Phase 3: Subscription & Billing
        require 'api/v1/merchant/shipping.php'; // Shipping Management
        require 'api/v1/merchant/stripe-connect.php'; // Stripe Connect Onboarding
        require 'api/v1/merchant/notifications.php'; // Push notifications & device tokens
    });

// ── 3. STOREFRONT CONTEXT ────────────────────────────────────────────────
// Public ecommerce APIs (Customers and guests browsing stores).
Route::prefix('/v1/storefront/runtime')
    ->middleware([
       //  'web',
    ])
    ->group(function (): void {
        $runtimeRoutes = __DIR__ . '/api/v1/storefront/runtime.php';
        if (is_file($runtimeRoutes)) {
            require $runtimeRoutes;
        }
    });
Route::prefix('/v1/storefront')
    ->middleware([
       //  'web',
        'identity.route:storefront_commerce,customer,enforce',
    ])
    ->group(function (): void {
        // Additional theme and navigation endpoints
        Route::prefix('theme')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Api\Storefront\StorefrontThemeController::class, 'show']);
        });

        Route::prefix('navigation')->group(function (): void {
            Route::get('/{handle}', [\App\Http\Controllers\Api\Storefront\StorefrontNavigationController::class, 'show']);
        });

        $productsRoutes = __DIR__ . '/api/v1/storefront/products.php';
        if (is_file($productsRoutes)) { require $productsRoutes; }
        $cartRoutes = __DIR__ . '/api/v1/storefront/cart.php';
        if (is_file($cartRoutes)) { require $cartRoutes; }
        $ordersRoutes = __DIR__ . '/api/v1/storefront/orders.php';
        if (is_file($ordersRoutes)) { require $ordersRoutes; }
        $addressesRoutes = __DIR__ . '/api/v1/storefront/addresses.php';
        if (is_file($addressesRoutes)) { require $addressesRoutes; }
        $checkoutRoutes = __DIR__ . '/api/v1/storefront/checkout.php';
        if (is_file($checkoutRoutes)) { require $checkoutRoutes; }
        $searchRoutes = __DIR__ . '/api/v1/storefront/search.php';
        if (is_file($searchRoutes)) { require $searchRoutes; }
        $homepageRoutes = __DIR__ . '/api/v1/storefront/homepage.php';
        if (is_file($homepageRoutes)) { require $homepageRoutes; }
    });

// Storefront webhooks (no auth/identity middleware - Stripe signature verification only)
Route::middleware([
   //  'web',
])
    ->group(function (): void {
        $webhookRoutes = __DIR__ . '/api/v1/storefront/webhooks.php';
        if (is_file($webhookRoutes)) { require $webhookRoutes; }
    });

Route::prefix('/v1/storefront/account')
    ->middleware([
       //  'web',
        'api.deprecated',
        'identity.route:customer_account,customer,enforce',
    ])
    ->group(function (): void {
        Route::get('/bootstrap', [\App\Http\Controllers\Api\Storefront\Account\StorefrontAccountController::class, 'bootstrap'])
            ->middleware(['auth:sanctum'])
            ->name('customer.storefront.legacy.bootstrap');

        Route::post('/logout', [\App\Http\Controllers\Api\Storefront\Account\StorefrontAccountController::class, 'logout'])
            ->middleware(['auth:sanctum'])
            ->name('customer.storefront.legacy.logout');
    });

// ── 4. CUSTOMER CONTEXT ──────────────────────────────────────────────────
// Customer identity and account management.
Route::prefix('/v1/customer')
    ->middleware([
       //  'web',
        'identity.route:customer_account,customer,enforce',
    ])
    ->group(function (): void {
        require 'api/v1/customer/account.php';
        require 'api/v1/customer/notifications.php'; // Push notifications & device tokens
    });

// ── 5. SUPPORT CONTEXT ───────────────────────────────────────────────────
// Internal support operations (SUPPORT_AGENT, SUPER_ADMIN).
Route::prefix('/v1/support')
    ->middleware([
       //  'web',
        'auth:sanctum',
        'identity.route:support,platform,enforce',
        'platform.context',
        'support.authority',
    ])
    ->group(function (): void {
        require 'api/v1/support/support.php';
    });

// ── 6. PUBLIC CONTEXT ────────────────────────────────────────────────────
// Marketing site, CMS, docs, and SEO (Unauthenticated public access).
Route::prefix('/v1/public')
    ->group(function (): void {
        require 'api/v1/public/cms.php';
        require 'api/v1/public/leads.php';
        require 'api/v1/public/marketplace.php';
    });

// ── 7. SYSTEM & SHARED ───────────────────────────────────────────────────
// Routes that cross context boundaries or serve infrastructure needs.

// Stripe webhooks
Route::middleware('identity.route:shared_transitional,merchant,observe')
    ->group(function (): void {
        require 'api/v1/stripe/webhook.php';
    });

// // CSRF Ownership Preparation
// Route::get('/sanctum/csrf-cookie', [CsrfOwnershipPreparationController::class, 'show'])
//     ->middleware(['web', 'identity.route:shared_transitional,merchant,observe']);
