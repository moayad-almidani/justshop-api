# Venom Mall — Media implementation review

Date: 2026-10-26. This is a review of the supplied **development** ZIP, not a production cutover.

## Implemented

- Central media schema with actual FKs and six tables.
- One upload operation per attachment; no cross-store checksum reuse.
- Multipart merchant endpoints for product, variant, category, store attachments, with tenant-scoped lookups and existing policies and subscription middleware.
- Validations for MIME, file sizes, image vs video main, category/store roles and sort order.
- DB-safe main-image promotion, attachment deletion, 7-day orphan-file job, remote/public disk URLs through Laravel Storage.
- Central media relationships in Product / ProductVariant / Category / Store; normalized media presentation and variant + parent gallery.
- Category admin/public resources, StoreResource, product admin editor/list, product storefront listing and product detail resource; cart/order resources can read central images (legacy fallback retained).
- Seeder for **existing generated local** store branding when file bytes are present; no fake missing images are inserted. Main product demo seeders stay legacy for compatibility.
- New regression tests for FK cascades, duplicate checksums, cleanup, main-image selection, multi-tenant lookups, and merchant HTTP uploads.
- Blocked the old `AdminMediaController` path deletion operation from removing files managed by the new central media module.
- Found and corrected one pre-existing syntax error in `app/Console/Commands/Billing/ReconcileSubscriptionsCommand.php` while linting the entire codebase.

## Existing incompatible or deferred surfaces

1. **Legacy images** (`Image`, `images` migration, product create/update repositories, original image seeders) remain active. They use MorphMany; there is no automatic conversion of old records to new `media` because many existing rows contain external/placeholder URLs and the archive's storage files were purposely excluded from the deliverable. Do not delete the old migration/table yet.
2. **Legacy theme store_assets** remains in use by theme pages and asset management APIs. Central StoreResource now has `media`, `logo_url`, `banner_url`; theme calls that still query StoreAsset need their own deliberate migration.
3. **Catalog/query consumers**: the normal product listing query now prefers central images, but additional reports/analytics (e.g. `BestSellerService`) and the legacy checkout screenshot fields may still query `images` directly. Reconcile as a separate compatibility wave before deprecating legacy media.
4. **Media conversions**: schema exists, but processing jobs, CDN resizing and video thumbnails are not implemented. Media APIs return original uploaded URLs.
5. **Image metadata editing**: `alt_text` and ordering are set on upload, but a dedicated reorder/metadata-update endpoint is not implemented; detach+re-upload is currently required for these changes. Set-main is supported.
6. **Cross-table exclusivity**: unique media IDs are guaranteed only inside each link table. No API accepts arbitrary media IDs, so normal upload flows cannot share one row among tables. Future internal attach-by-ID flows need a single ownership/exclusivity mechanism.
7. **Existing broad request/DTO/editor workflows** still accept legacy URL arrays. A new frontend should call the new media attachment endpoints; a migration of create/update JSON payload contracts requires coordinated frontend changes.

## Test execution and blockers

- Syntax: `php -l` completed on **1,605 PHP files** (`app`, `routes`, `database`, `tests`): zero errors after fixing the pre-existing billing command parse error.
- PHPUnit: not runnable in this container. The uploaded ZIP **does not include `vendor/autoload.php`**; Composer is not available on this runner, and PHP lacks `pdo_sqlite`. Test files were written and syntax-checked, **not executed**.
- Run on user's dev environment with `composer install`, supported DB/extension, and `php artisan test tests/Feature/Media`; follow with full `php artisan test`. Verify migrations with a throwaway MySQL database before applying to local development data.
- No live S3/CDN upload performed; file storage operations are covered by storage fakes in the new tests but unexecuted.

## Security & operational notes

- Tenant lookups scope product/category/variant by store; returning 404 before the authorization check prevents cross-store ID probing on the new endpoint.
- Ownership columns are nullable **only** after physical store deletion (`ON DELETE SET NULL`) so orphan media remains eligible for cleanup. New uploads always set owner store.
- The media module uses the `public` filesystem disk by default, not the system's legacy default disk. Adjust `filesystems.disks.public` to a shared/CDN-backed disk when deploying to multiple nodes.
- The legacy path-based deletion interface still needs a broader security review for other legacy assets; central `stores/<id>/media/` is protected.
- Deleting files from remote object storage and DB has no atomic distributed transaction; the scheduled job can retry failures. A production-grade transactional outbox may be warranted later.
- **Sensitive data**: `.env`, logs, cache and actual `storage` files are intentionally excluded from the deliverable ZIP; keep the real local `.env` out of source control.
