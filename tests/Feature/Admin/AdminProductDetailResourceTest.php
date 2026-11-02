<?php

namespace Tests\Feature\Admin;

use App\Http\Resources\Admin\Product\AdminProductDetailResource;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminProductDetailResourceTest extends TestCase
{
    public function test_it_returns_editor_translations_for_all_available_locales(): void
    {
        config()->set('content.editable_locales', ['ar', 'en', 'hi']);
        config()->set('content.default_locale', 'ar');

        $product = new Product([
            'store_id' => 5,
            'category_id' => 12,
            'brand_id' => 17,
            'is_active' => true,
            'is_new' => true,
        ]);
        $product->id = 33;
        $product->created_at = now();
        $product->updated_at = now();

        $product->setRelation('translations', new Collection([
            new ProductTranslation([
                'locale' => 'ar',
                'name' => 'كنبة حديثة',
                'slug' => 'knbh',
                'description' => 'وصف عربي',
                'seo_title' => 'عنوان SEO',
                'seo_description' => 'وصف SEO',
            ]),
            new ProductTranslation([
                'locale' => 'en',
                'name' => 'Modern Sofa',
                'slug' => 'modern-sofa',
                'description' => 'English description',
                'seo_title' => 'Modern Sofa SEO',
                'seo_description' => null,
            ]),
        ]));

        $variant = new ProductVariant([
            'sku' => 'SOFA-001',
            'price' => 299.99,
            'quantity' => 8,
            'is_active' => true,
            'barcode' => '123456789',
            'weight' => 12.5,
            'weight_unit' => 'kg',
        ]);
        $variant->id = 91;
        $variant->setRelation('attributeValues', new Collection());
        $variant->setRelation('images', new Collection());

        $product->setRelation('variants', new Collection([$variant]));
        $product->setRelation('tags', new Collection());

        $payload = (new AdminProductDetailResource($product))
            ->toArray(Request::create('/'));

        $this->assertSame(['ar', 'en', 'hi'], $payload['available_locales']);
        $this->assertSame('ar', $payload['default_locale']);
        $this->assertTrue($payload['is_new']);

        $this->assertArrayNotHasKey('name', $payload);
        $this->assertArrayNotHasKey('slug', $payload);
        $this->assertArrayNotHasKey('description', $payload);

        $this->assertSame('كنبة حديثة', $payload['translations']['ar']['name']);
        $this->assertTrue($payload['translations']['ar']['is_complete']);

        $this->assertSame('Modern Sofa', $payload['translations']['en']['name']);
        $this->assertFalse($payload['translations']['en']['is_complete']);

        $this->assertSame('hi', $payload['translations']['hi']['locale']);
        $this->assertNull($payload['translations']['hi']['name']);
        $this->assertFalse($payload['translations']['hi']['is_complete']);
    }
}
