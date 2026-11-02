<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * يرفع ملفات seed ثابتة (مرفقة مع الكود بـ database/seeders/assets/)
 * إلى القرص الحالي المُعرَّف بالتطبيق، عشان الـ seeding يضل قابل
 * لإعادة الإنتاج بأي بيئة — بدون أي افتراض إنه الملف موجود مسبقاً بالـ bucket.
 *
 * Uploads static seed assets (bundled with code in database/seeders/assets/)
 * to the application's current configured disk, ensuring seeding remains
 * reproducible in any environment — without assuming the file already
 * exists in the bucket.
 */
trait SeedsStaticMedia
{
    /**
     * Seed a static asset from database/seeders/assets/ to storage.
     *
     * @param string $sourceFilename Filename in database/seeders/assets/
     * @param string $storagePath Target path in storage (e.g., 'variants/default.png')
     * @param string|null $disk Optional disk; defaults to the application's configured default disk.
     * @return string The storage path (for saving to database)
     */
    protected function seedStaticAsset(string $sourceFilename, string $storagePath, ?string $disk = null): string
    {
        $disk ??= config('filesystems.default');
        $storage = Storage::disk($disk);

        if (! $storage->exists($storagePath)) {
            $sourcePath = database_path('seeders/assets/' . $sourceFilename);

            if (! file_exists($sourcePath)) {
                throw new \RuntimeException(
                    "Static asset not found: {$sourcePath}. " .
                    'Please ensure the file exists in database/seeders/assets/ directory.'
                );
            }

            $contents = file_get_contents($sourcePath);
            if ($contents === false || ! $storage->put($storagePath, $contents)) {
                throw new \RuntimeException("Unable to seed static asset [{$sourceFilename}] to disk [{$disk}].");
            }
        }

        return $storagePath;
    }
}
