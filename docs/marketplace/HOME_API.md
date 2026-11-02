# Marketplace Home API

## Home-page sections

The public Marketplace Home API aggregates data across operational stores for the mobile marketplace. It currently provides:

1. Stores
2. Store-owned Story groups
3. Promotional banners
4. Active root categories
5. Featured products

Search is intentionally not part of this API yet.

## Public endpoints

Base: `/api/v1/public/marketplace`

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/home` | Combined Home sections |
| GET | `/stores` | Operational stores |
| GET | `/stories` | Current Story groups, one group per store |
| GET | `/stores/{storeSlug}/stories` | Current Stories for one operational store |
| GET | `/promotional-banners` | Published banners within their optional schedule |
| GET | `/categories` | Active root categories |
| GET | `/featured-products` | Active featured products with an available variant |

Supported query parameters:

- `locale` (`ar` or `en`)
- `stores_limit` (1–30, default 10)
- `stories_limit` (1–30, default 10)
- `promotional_banners_limit` (1–20, default 10)
- `categories_limit` (1–30, default 12)
- `featured_products_limit` (1–30, default 12)

The combined `/home` response contains `stores`, `stories`, `promotional_banners`, `categories`, and `featured_products`.

## Stories

- A Story belongs to a **Store**. The Store is the publishing account; each `stories` row represents one image/video slide.
- A Story may optionally tag a product, and the tagged product must belong to the same Store.
- Public Stories require `status = published`, `published_at <= now()` and `expires_at > now()`.
- Merchant uploads are published immediately and expire after 24 hours by default.
- Store circles are grouped by Store and use the Store logo. A Store appears in the public Stories collection only when it has at least one current Story.
- Story-group responses use a compact Store summary (`id`, `name`, `slug`, `logo_url`) only. The regular `/stores` endpoint and the top-level `stores` section in `/home` continue using the full Store resource, including `currency` and `active_products_count`.

Merchant endpoints use the store context and StorePolicy:

Base: `/api/v1/merchant/stores/{store}/stories`

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/` | Paginated Stories for the merchant Store, including expired items |
| POST | `/` | Upload and publish a Story |
| DELETE | `/{storyId}` | Delete a Story owned by that Store |

The merchant list accepts `page` and `per_page` (default 20, maximum 100). `POST` accepts multipart form-data:

- `file` (required): JPEG/PNG/WebP/GIF image or MP4/WebM video; images max 5 MB, all media max 30 MB
- `caption` (optional, max 500 characters)
- `product_id` (optional, must belong to the same Store)
- `alt_text` (optional, max 255 characters)

Files are stored under `stores/{storeId}/stories` on the `public` disk. Each upload creates its own `media` row with `owner_store_id`; checksum is diagnostic only and does not deduplicate files.

## Promotional banners — interim contract

- A banner is a display-content record with one central Media image, localized title/subtitle/CTA copy, optional `target_url`, `sort_order`, publication status, and optional `starts_at` / `ends_at` schedule.
- The public API returns only `published` banners whose schedule includes the current time and whose image Media record is not soft-deleted and has image type.
- `GET /promotional-banners` returns the same resource used in `/home`.
- **Ownership and campaign rules are intentionally deferred.** The current model has no `store_id`, owner, campaign, product/category targeting, approval, billing, or sponsored-placement logic. This is a neutral content feed for frontend integration until the client decides whether banners are platform-owned, store-submitted, or mixed.
- `target_url` is an opaque destination for the mobile frontend to interpret. The API does not validate it against a specific frontend route at this stage.
- Titles, subtitles, and button labels use relational rows in `promotional_banner_translations` (`ar` and `en` are seeded), following the same one-row-per-locale pattern as `product_translations` and `category_translations`.

A banner is not a product discount. Any displayed discount must eventually be backed by the actual pricing/promotion domain; the seeded 70% banner is placeholder demo content only.

## Category and product selection

- Categories are active roots (`parent_id IS NULL`). The repository prefers global categories (`store_id IS NULL`). The current demo taxonomy is tenant-scoped, so it falls back to root categories from operational stores if there are no global roots.
- `Accessories` is a root category in the demo taxonomy, matching the supplied Home screenshot.
- Featured products require `is_featured = true`, `is_active = true`, an operational Store, and at least one active variant that is in stock or does not track inventory.
- Featured product resources include `is_new` (a merchant-controlled product badge, default `false`) and `discount_percentage` (computed from the same selected available Variant's `price` and `compare_at_price`; `null` when no valid discount exists).
- `is_new` can be set on product create/update payloads and is returned in merchant product list/detail resources. `discount_percentage` is derived and is never stored separately.
- Marketplace reads intentionally aggregate public records across stores. This is a public-read exception, not a pattern to reuse for merchant CRUD. Merchant writes remain store-scoped and policy-authorized.

## Seed data

`DatabaseSeeder` runs the two new repeatable content seeders after the demo Store, product catalog, and central Store media are available:

- `StorySeeder` creates three current demo Stories for `merchant-store`, each with a bundled vertical SVG and an optional same-Store product tag.
- `PromotionalBannerSeeder` creates two published sample banners with Arabic and English translations.

The source SVGs are committed under `database/seeders/assets/marketplace/`. Seeders copy them to the `public` disk, so the ZIP does not need generated `storage/` contents. Run `php artisan db:seed` to invoke them through `DatabaseSeeder`, or run either seeder individually.

## Data model notes

- `stories.store_id` is the Story owner; `stories.product_id` is an optional same-Store product tag (`ON DELETE SET NULL`).
- `story_media` attaches exactly one central Media row to each Story (`UNIQUE(story_id)` and `UNIQUE(media_id)`).
- `promotional_banners.image_media_id` is a direct FK because each banner has exactly one image. Localized copy is stored relationally in `promotional_banner_translations`, with one row per banner and locale, matching the `product_translations` pattern.
- Media pruning checks both normal attachment tables and direct `promotional_banners.image_media_id` references before removing an orphan.
- Story video transcoding and Media conversion jobs are not included in this Home API slice.
