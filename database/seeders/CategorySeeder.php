<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Concerns\SeedsDemoStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    use SeedsDemoStore;

    public function run(): void
    {
        $storeId = $this->demoStoreId();
        $sortOrder = 0;

        $tree = [
            'Electronics' => [
                'en' => 'Electronics',
                'ar' => 'الإلكترونيات',
                'children' => [
                    'Phones' => ['en' => 'Phones', 'ar' => 'الهواتف'],
                    'Laptops' => ['en' => 'Laptops', 'ar' => 'أجهزة الكمبيوتر المحمولة'],
                ],
            ],
            'Accessories' => [
                'en' => 'Accessories',
                'ar' => 'الإكسسوارات',
                'children' => [],
            ],
            'Fashion' => [
                'en' => 'Fashion',
                'ar' => 'الأزياء',
                'children' => [
                    'Men Clothing' => ['en' => 'Men Clothing', 'ar' => 'ملابس رجالية'],
                    'Women Clothing' => ['en' => 'Women Clothing', 'ar' => 'ملابس نسائية'],
                    'Shoes' => ['en' => 'Shoes', 'ar' => 'الأحذية'],
                ],
            ],
            'Home Living' => [
                'en' => 'Home Living',
                'ar' => 'المنزل والمعيشة',
                'children' => [
                    'Appliances' => ['en' => 'Appliances', 'ar' => 'الأجهزة المنزلية'],
                    'Furniture' => ['en' => 'Furniture', 'ar' => 'الأثاث'],
                    'Decor' => ['en' => 'Decor', 'ar' => 'الديكور'],
                ],
            ],
            'Beauty Health' => [
                'en' => 'Beauty Health',
                'ar' => 'الجمال والصحة',
                'children' => [
                    'Skincare' => ['en' => 'Skincare', 'ar' => 'العناية بالبشرة'],
                    'Hair Care' => ['en' => 'Hair Care', 'ar' => 'العناية بالشعر'],
                    'Perfumes' => ['en' => 'Perfumes', 'ar' => 'العطور'],
                ],
            ],
            'Sports & Outdoors' => [
                'en' => 'Sports & Outdoors',
                'ar' => 'الرياضة والهواء الطلق',
                'children' => [
                    'Fitness Equipment' => ['en' => 'Fitness Equipment', 'ar' => 'معدات اللياقة البدنية'],
                    'Outdoor Gear' => ['en' => 'Outdoor Gear', 'ar' => 'معدات الهواء الطلق'],
                    'Sportswear' => ['en' => 'Sportswear', 'ar' => 'الملابس الرياضية'],
                ],
            ],
            'Toys Games' => [
                'en' => 'Toys Games',
                'ar' => 'الألعاب والتسلية',
                'children' => [
                    'Action Figures' => ['en' => 'Action Figures', 'ar' => 'أشكال الحركة'],
                    'Board Games' => ['en' => 'Board Games', 'ar' => 'ألعاب اللوح'],
                    'Educational Toys' => ['en' => 'Educational Toys', 'ar' => 'ألعاب تعليمية'],
                ],
            ],
        ];

        foreach ($tree as $parentData) {
            // Accessories is intentionally a root category, like the other Home categories.
            $parentCategory = $this->syncCategory(
                data: $parentData,
                storeId: $storeId,
                parentId: null,
                sortOrder: $sortOrder++,
            );

            $childSortOrder = 0;
            foreach ($parentData['children'] as $childData) {
                $this->syncCategory(
                    data: $childData,
                    storeId: $storeId,
                    parentId: $parentCategory->id,
                    sortOrder: $childSortOrder++,
                );
            }
        }
    }

    /**
     * Upsert categories and translations so this seeder can safely repair existing
     * development data as well as populate a fresh database.
     *
     * @param array{en: string, ar: string} $data
     */
    private function syncCategory(array $data, int $storeId, ?int $parentId, int $sortOrder): Category
    {
        $category = Category::withTrashed()->updateOrCreate(
            ['slug' => Str::slug($data['en'])],
            [
                'store_id' => $storeId,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );

        if ($category->trashed()) {
            $category->restore();
        }

        foreach (['en', 'ar'] as $locale) {
            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $data[$locale],
                    'slug' => Str::slug($data[$locale]),
                ],
            );
        }

        return $category;
    }
}
