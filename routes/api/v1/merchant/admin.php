<?php

/**
 * Merchant Admin Routes
 * 
 * Protection Strategy:
 * - All routes require: auth:sanctum + identity.route:merchant_admin + store.context
 * - Write operations (POST/PATCH/PUT/DELETE) additionally require: subscription.active middleware
 * - Read operations (GET) are allowed even with expired subscription (read-only access)
 * - This allows merchants to view their data and access billing pages to renew subscription
 * 
 * Subscription Check Logic:
 * - subscription.active middleware checks via FeatureGateService::ensureWriteAccess()
 * - Returns 402 Payment Required if entitlement_status != (ENTITLED|TRIAL|GRANDFATHERED)
 * - Statuses like NONE, RESTRICTED block write access but allow read access
 * - This is enforced at route level (not Policy) for consistency across all write operations
 */

use App\Http\Controllers\Api\Merchant\AdminBrandController;
use App\Http\Controllers\Api\Merchant\AdminCategoryController;
use App\Http\Controllers\Api\Merchant\AdminDashboardController;
use App\Http\Controllers\Api\Merchant\AdminFileUploadController;
use App\Http\Controllers\Api\Merchant\AdminMarketingSectionTypeController;
use App\Http\Controllers\Api\Merchant\AdminMediaController;
use App\Http\Controllers\Api\Merchant\AdminStoreMarketingPageController;
use App\Http\Controllers\Api\Merchant\AdminOrderController;
use App\Http\Controllers\Api\Merchant\AdminProductController;
use App\Http\Controllers\Api\Merchant\AdminTagController;
use App\Http\Controllers\Api\Merchant\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::name('merchant.')
    ->prefix('stores/{store}')
    ->withoutMiddleware(['identity.route:merchant_users,merchant,enforce'])
    ->middleware(['auth:sanctum', 'identity.route:merchant_admin,merchant,enforce', 'store.context'])
    ->group(function () {

        // ── User Management ────────────────────────────────────
        Route::prefix('users')->group(function () {
            // Read operations - no subscription check
            Route::get('/', [AdminUserController::class, 'index'])
                ->name('users.index');

            Route::get('/{user}', [AdminUserController::class, 'show'])
                ->name('users.show');

            // Write operations - require active subscription
            Route::post('/', [AdminUserController::class, 'store'])
                ->middleware('subscription.active')
                ->name('users.store');

            Route::patch('/{user}/block', [AdminUserController::class, 'block'])
                ->middleware('subscription.active')
                ->name('users.block');

            Route::patch('/{user}/unblock', [AdminUserController::class, 'unblock'])
                ->middleware('subscription.active')
                ->name('users.unblock');

            Route::delete('/{user}', [AdminUserController::class, 'destroy'])
                ->middleware('subscription.active')
                ->name('users.destroy');

            Route::patch('/{user}/restore', [AdminUserController::class, 'restore'])
                ->middleware('subscription.active')
                ->name('users.restore');
        });

        // ── Product Management ─────────────────────────────────
        Route::prefix('products')->group(function () {
            // Read operations - no subscription check (allow read-only access)
            Route::get('/', [AdminProductController::class, 'index'])
                ->name('products.index');

            Route::get('/{product}', [AdminProductController::class, 'show'])
                ->name('products.show');

            // Write operations - require active subscription
            Route::post('/', [AdminProductController::class, 'store'])
                ->middleware('subscription.active')
                ->name('products.store');

            Route::patch('/{product}', [AdminProductController::class, 'update'])
                ->middleware('subscription.active')
                ->name('products.update');

            Route::delete('/{product}', [AdminProductController::class, 'destroy'])
                ->middleware('subscription.active')
                ->name('products.destroy');

            Route::patch('/{product}/restore', [AdminProductController::class, 'restore'])
                ->middleware('subscription.active')
                ->name('products.restore');
        });

        // ── Order Management ───────────────────────────────────
        Route::prefix('orders')->group(function () {
            // Read operations - no subscription check
            Route::get('/', [AdminOrderController::class, 'index'])
                ->name('orders.index');

            Route::get('/{order}', [AdminOrderController::class, 'show'])
                ->name('orders.show');

            // Write operations - require active subscription
            Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])
                ->middleware('subscription.active')
                ->name('orders.status');

            Route::patch('/{order}/cancel', [AdminOrderController::class, 'cancel'])
                ->middleware('subscription.active')
                ->name('orders.cancel');

            Route::patch('/{order}/refund', [AdminOrderController::class, 'refund'])
                ->middleware('subscription.active')
                ->name('orders.refund');
        });

        // ── Dashboard ──────────────────────────────────────────
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [AdminDashboardController::class, 'stats'])
                ->name('dashboard.stats');

            Route::get('/recent-orders', [AdminDashboardController::class, 'recentOrders'])
                ->name('dashboard.recent-orders');

            Route::get('/top-products', [AdminDashboardController::class, 'topProducts'])
                ->name('dashboard.top-products');
        });

        // ── Category Management ────────────────────────────────
        Route::prefix('categories')->group(function () {
            // Read operations - no subscription check
            Route::get('/', [AdminCategoryController::class, 'index'])
                ->name('categories.index');

            Route::get('/{category}', [AdminCategoryController::class, 'show'])
                ->name('categories.show');

            // Write operations - require active subscription
            Route::post('/', [AdminCategoryController::class, 'store'])
                ->middleware('subscription.active')
                ->name('categories.store');

            Route::patch('/{category}', [AdminCategoryController::class, 'update'])
                ->middleware('subscription.active')
                ->name('categories.update');

            Route::delete('/{category}', [AdminCategoryController::class, 'destroy'])
                ->middleware('subscription.active')
                ->name('categories.destroy');

            Route::patch('/{category}/restore', [AdminCategoryController::class, 'restore'])
                ->middleware('subscription.active')
                ->name('categories.restore');
        });

        // ── Brand Management ───────────────────────────────────
        Route::prefix('brands')->group(function () {
            // Read operations - no subscription check
            Route::get('/', [AdminBrandController::class, 'index'])
                ->name('brands.index');

            Route::get('/{brand}', [AdminBrandController::class, 'show'])
                ->name('brands.show');

            // Write operations - require active subscription
            Route::post('/', [AdminBrandController::class, 'store'])
                ->middleware('subscription.active')
                ->name('brands.store');

            Route::patch('/{brand}', [AdminBrandController::class, 'update'])
                ->middleware('subscription.active')
                ->name('brands.update');

            Route::delete('/{brand}', [AdminBrandController::class, 'destroy'])
                ->middleware('subscription.active')
                ->name('brands.destroy');

            Route::patch('/{brand}/restore', [AdminBrandController::class, 'restore'])
                ->middleware('subscription.active')
                ->name('brands.restore');
        });

        // ── Tag Management ───────────────────────────────────
        Route::prefix('tags')->group(function () {
            // Read operations - no subscription check
            Route::get('/', [AdminTagController::class, 'index'])
                ->name('tags.index');

            Route::get('/{tag}', [AdminTagController::class, 'show'])
                ->name('tags.show');

            // Write operations - require active subscription
            Route::post('/', [AdminTagController::class, 'store'])
                ->middleware('subscription.active')
                ->name('tags.store');

            Route::match(['put', 'patch'], '/{tag}', [AdminTagController::class, 'update'])
                ->middleware('subscription.active')
                ->name('tags.update');

            Route::delete('/{tag}', [AdminTagController::class, 'destroy'])
                ->middleware('subscription.active')
                ->name('tags.destroy');
        });

        // ── Store Marketing CMS ────────────────────────────────
        Route::prefix('cms/pages')
            ->controller(AdminStoreMarketingPageController::class)
            ->group(function () {
                // Read operations - no subscription check
                Route::get('/', 'index')
                    ->name('cms.pages.index');
                Route::get('/check-homepage', 'checkHomepage')
                    ->name('cms.pages.check-homepage');
                Route::get('/{id}', 'show')
                    ->name('cms.pages.show');
                
                // Write operations - require active subscription
                Route::post('/', 'store')
                    ->middleware('subscription.active')
                    ->name('cms.pages.store');
                Route::put('/{id}', 'update')
                    ->middleware('subscription.active')
                    ->name('cms.pages.update');
                Route::delete('/{id}', 'destroy')
                    ->middleware('subscription.active')
                    ->name('cms.pages.destroy');
                
                // Publish workflow - require active subscription
                Route::post('/{id}/publish', 'publish')
                    ->middleware('subscription.active')
                    ->name('cms.pages.publish');
                Route::post('/{id}/unpublish', 'unpublish')
                    ->middleware('subscription.active')
                    ->name('cms.pages.unpublish');
            });

        // ── Marketing Section Types (backend-driven) ──────────
        Route::get('cms/section-types', [AdminMarketingSectionTypeController::class, 'index'])
            ->name('cms.section-types');

        // ── Navigation Permissions ─────────────────────────────
        Route::get('navigation-permissions', [\App\Http\Controllers\Api\Merchant\NavigationPermissionsController::class, 'index'])
            ->name('navigation-permissions');

        // ── Central media attachments (new; legacy image routes remain operational) ──
        Route::prefix('media/attachments/{entity}/{entityId}')
            ->where(['entity' => 'products|variants|categories|stores', 'entityId' => '[0-9]+'])
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Merchant\Media\MediaAttachmentController::class, 'index'])
                    ->name('media.attachments.index');
                Route::post('/', [\App\Http\Controllers\Api\Merchant\Media\MediaAttachmentController::class, 'store'])
                    ->middleware('subscription.active')->name('media.attachments.store');
                Route::patch('/{attachmentId}/main', [\App\Http\Controllers\Api\Merchant\Media\MediaAttachmentController::class, 'setMain'])
                    ->whereNumber('attachmentId')->middleware('subscription.active')
                    ->name('media.attachments.main');
                Route::delete('/{attachmentId}', [\App\Http\Controllers\Api\Merchant\Media\MediaAttachmentController::class, 'destroy'])
                    ->whereNumber('attachmentId')->middleware('subscription.active')
                    ->name('media.attachments.destroy');
            });

        // ── Store Stories (each slide belongs to this store) ──
        Route::prefix('stories')->controller(\App\Http\Controllers\Api\Merchant\Story\StoreStoryController::class)->group(function () {
            Route::get('/', 'index')->name('stories.index');
            Route::post('/', 'store')->middleware('subscription.active')->name('stories.store');
            Route::delete('/{storyId}', 'destroy')->whereNumber('storyId')->middleware('subscription.active')->name('stories.destroy');
        });

        // ── Generic Media Upload ───────────────────────────────
        Route::prefix('media')->group(function () {
            // Write operations - require active subscription
            Route::post('/upload', [AdminMediaController::class, 'upload'])
                ->middleware('subscription.active')
                ->name('media.upload');
            
            Route::delete('/delete', [AdminMediaController::class, 'delete'])
                ->middleware('subscription.active')
                ->name('media.delete');
        });
    });
