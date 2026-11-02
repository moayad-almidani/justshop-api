<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductTranslation;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'is_active' => true,
            'is_new' => false,
        ];
    }


    public function configure()
    {
        return $this->afterCreating(function (Product $product)
        {
            $name = fake()->unique()->words(3, true);

            ProductTranslation::create([
                'product_id' => $product->id,
                'locale' => 'en',
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $product->id,
                'description' => fake()->sentence(12),
            ]);

            Image::factory()->create([
                'imageable_type' => 'App\Models\Product',
                'imageable_id' => $product->id,
                'image_url' => '/storage/products/default.png',
                'is_primary' => true
            ]);
        });
    }
}
