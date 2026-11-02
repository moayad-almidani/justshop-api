# Marketplace Home API — Implementation Report

## Scope delivered

The Home API supports Stores, Store-owned Story groups, Promotional Banners, active root Categories, and Featured Products. Search and the eventual campaign ownership/approval model remain out of scope.

## Public API

Base: `/api/v1/public/marketplace`

- `GET /home`
- `GET /stores`
- `GET /stories`
- `GET /stores/{storeSlug}/stories`
- `GET /promotional-banners`
- `GET /categories`
- `GET /featured-products`

`/home` returns `stores`, `stories`, `promotional_banners`, `categories`, and `featured_products`. Limits are configurable through query parameters; `locale` selects translated labels.

### Promotional banner contract

The interim banner record has one central Media image, a `promotional_banner_translations` child row per locale for title/subtitle/button text (matching the `product_translations` pattern), an optional `target_url`, display order, status, and optional start/end times. The public endpoint only returns published records within schedule. There is intentionally no store owner, campaign, product selection, approval, billing, or sponsored placement rule yet; the client is to decide that business model. Seeded banners are demo content and do not represent real product discounts.

## Story behavior

- A Story belongs to a Store; optional product tagging is restricted to active products in the same Store.
- Public Stories require a published status, a publication time in the past, a future expiry time, and an existing Media attachment.
- New merchant uploads publish immediately and expire after 24 hours by default.
- Merchant management is paginated and uses StorePolicy for authorization; repository queries are store-scoped.

## Seeders

- `StorySeeder` creates three active demo Stories for `merchant-store` with their own bundled portrait SVG images and optional same-Store product tags.
- `PromotionalBannerSeeder` creates two published sample banners with Arabic and English translations.
- Both seeders are registered in `DatabaseSeeder` after Store/Product setup and the central Store-media import. They are repeatable and can also be run independently.
- The SVG sources are kept under `database/seeders/assets/marketplace/` and copied to the `public` disk. No generated `storage/` folder is required in the source ZIP.
- `SeedsStaticMedia` now accepts an optional disk argument and preserves its previous default-disk behavior for existing seeders.

## Files added

### Models and domain types

- `app/Enums/Marketplace/PromotionalBannerStatusEnum.php`
- `app/Models/Marketplace/PromotionalBanner.php`
- `app/Actions/Marketplace/GetPromotionalBannersAction.php`
- `app/DTOs/Marketplace/GetPromotionalBannersDTO.php`
- `app/Http/Requests/Public/Marketplace/GetPromotionalBannersRequest.php`
- `app/Http/Resources/Marketplace/MarketplacePromotionalBannerResource.php`
- `database/migrations/2026_10_31_000003_create_promotional_banners_table.php`
- `database/migrations/2026_10_31_000004_create_promotional_banner_translations_table.php`
- `app/Models/Marketplace/PromotionalBannerTranslation.php`
- `database/seeders/StorySeeder.php`
- `database/seeders/PromotionalBannerSeeder.php`
- `tests/Feature/Marketplace/PromotionalBannerApiTest.php`
- `tests/Feature/Marketplace/MarketplaceDemoSeedersTest.php`

### Bundled demo assets

- `database/seeders/assets/marketplace/stories/story-new-arrivals.svg`
- `database/seeders/assets/marketplace/stories/story-special-offer.svg`
- `database/seeders/assets/marketplace/stories/story-best-sellers.svg`
- `database/seeders/assets/marketplace/banners/banner-sale.svg`
- `database/seeders/assets/marketplace/banners/banner-new-season.svg`

## Existing files updated

- `app/Actions/Marketplace/GetMarketplaceHomeAction.php` — includes the banner collection.
- `app/Actions/Marketplace/GetPromotionalBannersAction.php` and `app/DTOs/Marketplace/GetPromotionalBannersDTO.php` — dedicated banner-list use case.
- `app/DTOs/Marketplace/GetMarketplaceHomeDTO.php` — includes the banner limit.
- `app/Http/Requests/Public/Marketplace/GetMarketplaceHomeRequest.php` and `GetPromotionalBannersRequest.php` — validate Home and banner query parameters.
- `app/Http/Controllers/Api/Public/Marketplace/MarketplaceHomeController.php` — adds the dedicated banner endpoint and Home resource mapping.
- `app/Repositories/Marketplace/MarketplaceHomeRepository.php` — selects published/scheduled banners and eager-loads banner images and relational translations.
- `routes/api/v1/public/marketplace.php` — registers `GET /promotional-banners`.
- `database/seeders/DatabaseSeeder.php` — invokes the Story and Banner seeders.
- `database/seeders/Concerns/SeedsStaticMedia.php` — supports explicit disk selection.
- `app/Repositories/Media/MediaAttachmentRepository.php` — checks direct banner media references for exclusivity.
- `app/Console/Commands/Media/PruneOrphanMediaCommand.php` — protects attached banner media and recognizes the banner seed prefix.
- `app/Repositories/Marketplace/MarketplaceHomeRepository.php` — adds the read-only banner selection to the existing public Home aggregation.
- Earlier Home API files continue to provide Store/Story/Category/Featured Product resources and merchant Story endpoints.

## Verification

- PHP syntax checks passed for all 21 PHP files changed or added in this pass.
- PHPUnit / Laravel runtime tests were not executed in this environment. The supplied ZIP has no `vendor/` directory, Composer is unavailable in this runtime, and PHP has PDO but no MySQL/SQLite PDO driver.
- Run the documented tests in the project's configured development environment after installing dependencies and configuring the test database.

## Boundaries and follow-ups

- Existing product `images` and Store `store_assets` remain during migration; Home resources use central Media first with legacy fallback where implemented.
- Banner authoring/admin endpoints are not included because banner ownership, approval, and campaign rules are awaiting the client's decision.
- `target_url` is returned as supplied and is intentionally not coupled to an unconfirmed mobile route scheme.
- Demo Categories remain tenant-scoped in the existing schema; the Home repository prefers global categories and falls back to active-store roots.
- The original archive lacked some optional Storefront route files. The existing route loader guards those missing optional includes, but those Storefront APIs are not reconstructed in this change.


## Migration organization

- Every central-media table has its own create-table migration, ordered by FK dependencies.
- `stories` and `story_media` have separate migrations.
- Marketplace Home indexes are included in the original create-table migrations for stores, categories, products, and product variants; no separate add-index migration is needed in this pre-production branch.
- The Accessories root-category correction is represented in the idempotent `CategorySeeder`, not a one-off data migration.
- The existing database must be rebuilt/reset in development for edited historical migrations to run. This is deliberate because the project is not in production; preserve local data before resetting.

## Featured product badges

- `products.is_new` is a persisted merchant-controlled boolean (default `false`), accepted by merchant product create/update requests and returned by admin and marketplace product resources.
- `discount_percentage` is computed from the exact available variant used for the returned `price` and `compare_at_price`; it is `null` when `compare_at_price` is absent or not greater than `price`.
- The demo ProductSeeder marks the sample Flory Summer Dress as new and the Classic Cotton T-Shirt as featured with a 15% compare-at discount.
