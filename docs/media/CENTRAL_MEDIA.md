# Central media integration (2026-10-26)

## Architecture

- Six **new** tables: `media`, `media_conversions`, `product_media`, `product_variant_media`, `store_media`, `category_media`.
- Each uploaded file creates a fresh `media` row. Identical SHA-256 checksums **do not** cause reuse across merchants.
- `media.owner_store_id` records ownership. New media is always uploaded under `stores/{storeId}/media` on the `public` disk. `owner_store_id` is nullable only for retention of orphan records when the store is physically deleted.
- Link tables have real FKs with `ON DELETE CASCADE` on entity IDs and `RESTRICT` on media IDs. An attachment may only refer to one media row within its own link table (`UNIQUE(media_id)`); cross-link-table exclusivity is enforced by not exposing attach-by-id operations. Do not introduce such an operation without a transactional, serialized ownership/exclusivity check.
- `is_main = true|NULL` for product and variant links; `UNIQUE(entity_id, is_main)` allows many NULL but only one true. Other columns use strings + PHP enums, never DB enums.
- Shared product images are attached to the parent product; variant images to their own variant. `CentralGallery::forVariant()` concatenates **variant images first, shared images second**.
- Store and category roles are `logo`, `banner`, `gallery`, `icon` where relevant; uploading a new role `logo`/`icon`/`banner` replaces the previous active link.
- `media_conversions` only defines storage metadata; no image transformation worker has been implemented yet.

## API

Existing middleware applies: `auth:sanctum`, merchant identity context, `store.context` and `subscription.active` for writes. Store and entity permissions are enforced via existing StorePolicy/ProductPolicy/CategoryPolicy. Cross-store entity IDs result in 404.

Under `/api/v1/merchant/stores/{store}/media/attachments/{entity}/{entityId}` where `{entity}` is `stores|categories|products|variants`:

| Verb | Relative path | Description |
| --- | --- | --- |
| GET | `/` | List attachments with `id`, `media_id`, `url`, `type`, `is_main`, `role`, `sort_order`. |
| POST | `/` | `multipart/form-data`: required `file`; optional `sort_order`, `alt_text`, `is_main` for products/variants, `role` for stores/categories. |
| PATCH | `/{attachmentId}/main` | Promote product/variant **image** to main. |
| DELETE | `/{attachmentId}` | Detach only; mark media soft-deleted for delayed cleanup. |

Supported uploads: JPG/PNG/WEBP/GIF <= 5 MB; MP4/WEBM <= 30 MB. `is_main` applies only to images. Upload creates a distinct `media` record, then attachment; never accept arbitrary `media_id` from clients. PHP upload limits / disk permissions must be set on host. Uploads use Laravel's `public` disk; serve via `php artisan storage:link` and/or configure `filesystems.disks.public.url`.

**Example:** `POST /api/v1/merchant/stores/1/media/attachments/categories/7` with `file=@category.png` and `role=icon`. For product 12: `.../products/12` and `is_main=1`. For variant 55: `.../variants/55`. For store 1: `.../stores/1` and `role=logo`.

## Migration / legacy compatibility

The original project already has **two pre-existing media systems**:

1. `images` with polymorphic `imageable_id`/`imageable_type`, still used by older product create/update, storefront searches, cart, checkout, orders, and existing demo seeders.
2. `store_assets`, still used for themes and old theme asset upload and branding, populated by `StoreAssetsSeeder`.

The new endpoints and central relationships are **additive**, not a destructive replacement. The product editor resource prefers new links when loaded and falls back to legacy `images`; the storefront detail resource combines new central variant + parent gallery when any new central image is attached. Existing routes/contracts continue to work.

**IMPORTANT remaining consolidation work:** migrate existing `images` and `store_assets` *only after checking real storage availability*, update legacy product create/update media payloads and search/order/cart/checkout resource code to use the central system consistently, and then remove the old tables/routes/seeders. Demo product image seeders intentionally continue to populate legacy records until that migration, to avoid broken URLs because the supplied ZIP excludes real storage. Never blindly create central `media` rows that point at missing files.

The new `CentralStoreMediaSeeder` is called after `StoreAssetsSeeder` and copies real generated brand assets to central `store_media` (logo/icon/banner). It skips missing source files and existing roles; no absent files are faked. Product seeders still generate legacy image URLs. The media schema is split into one table-creation migration per table (`create_media`, `create_media_conversions`, `create_product_media`, `create_product_variant_media`, `create_store_media`, and `create_category_media`) so dependencies and rollbacks are explicit. Marketplace query indexes are defined alongside the original `stores`, `categories`, `products`, and `product_variants` table migrations because this project is still pre-production. For an existing development database that has already recorded these migrations, rebuild/reset the development schema before applying the edited migration history; do not expect Laravel to rerun already-recorded migrations automatically.

## Cleanup and safety

- `php artisan media:prune --days=7`: scans old rows, tests **all four** link tables, permanently deletes byte storage and conversions only for detached media created by this new module (`stores/` prefix). Registered daily at 02:30.
- A nonzero retention period accommodates requests and delayed jobs. Do not directly delete stored files during product/category/store removal.
- Variant/customer order metadata may refer to old image URLs; old image system remains for backward compatibility.
- Soft-deleting a product does not remove media links. Hard delete uses real FK CASCADE and leaves orphan `media` until pruning.
- Upload storage operations are compensated if the DB transaction fails. More robust cross-service asset deletion on remote S3/object storage can later use an outbox/queued retry pattern.

## Tests / runbook

```
composer install
php artisan migrate
php artisan storage:link
php artisan test tests/Feature/Media/CentralMediaTest.php
php artisan test
php artisan media:prune --days=7
```

Media tests cover per-entity attachments, identical checksum isolation, parent/variant merging, main image uniqueness, cross-store lookup, delayed deletion, hard-delete cascade, and orphan cleanup. The provided ZIP has **no vendor directory**, and the local execution PHP environment has **no pdo_sqlite**; the test suite could be syntax-checked but could not be executed here. Run on a Laravel environment with dependencies and SQLite or MySQL.

This ZIP intentionally excludes `.env` (may contain credentials), `vendor/`, transient runtime data and binary storage assets. Retain your local environment file and run migrations after backing up local DB even in development.
