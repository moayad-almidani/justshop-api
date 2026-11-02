<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Public\Marketplace\MarketplaceHomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketplace')->name('public.marketplace.')->group(function (): void {
    Route::get('/home', [MarketplaceHomeController::class, 'show'])->name('home');
    Route::get('/stores', [MarketplaceHomeController::class, 'stores'])->name('stores.index');
    Route::get('/stories', [MarketplaceHomeController::class, 'stories'])->name('stories.index');
    Route::get('/promotional-banners', [MarketplaceHomeController::class, 'promotionalBanners'])->name('promotional-banners.index');
    Route::get('/stores/{storeSlug}/stories', [MarketplaceHomeController::class, 'storeStories'])
        ->where('storeSlug', '[A-Za-z0-9-]+')
        ->name('stores.stories');
    Route::get('/categories', [MarketplaceHomeController::class, 'categories'])->name('categories.index');
    Route::get('/featured-products', [MarketplaceHomeController::class, 'featuredProducts'])->name('products.featured');
});
