<?php
// database/seeders/ProductSeeder.php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoStore;
use Database\Seeders\Concerns\SeedsStaticMedia;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Image;
use App\Models\ProductVariant;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\Store;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use SeedsDemoStore, SeedsStaticMedia;

    /**
     * Product name → Brand name mapping.
     */
    private array $productBrands = [
        // Phones
        'iPhone 14'              => 'Apple',
            'Samsung Galaxy S23'              => 'Samsung',
            'Google Pixel 8'                 => 'Google',
            'Xiaomi Redmi Note 12'   => 'Xiaomi',
        // Laptops
            'MacBook Pro'            => 'Apple',
            'Dell XPS 13'            => 'Dell',
            'Dell XPS 15'            => 'Dell',
        'HP Spectre x360'        => 'HP',
        'Lenovo ThinkPad X1'     => 'Lenovo',
        'ASUS ROG Zephyrus'      => 'ASUS',
        // Shoes
        'Running Sneakers'       => 'Nike',
        // Furniture
        'Modern Sofa 3-Seater'   => 'IKEA',
        'Bookshelf 5-Tier'       => 'IKEA',
        // Toys
        'Superhero Action Figure' => 'Hasbro',
        'Dinosaur Figure Set'     => 'Mattel',
        'Strategy Board Game'     => 'Hasbro',
        'STEM Building Blocks'    => 'LEGO',
        'Learning Tablet for Kids' => 'LeapFrog',
    ];

    /**
     * Product name → Tags mapping.
     */
    private array $productTags = [
        'iPhone 14'              => ['Electronics', 'Mobile', 'New Arrival'],
            'Samsung Galaxy S23'     => ['Electronics', 'Mobile', 'Bestseller'],
            'Google Pixel 8'         => ['Electronics', 'Mobile', 'New Arrival'],
            'MacBook Pro'            => ['Electronics', 'Laptop', 'Pro'],
            'Dell XPS 15'            => ['Electronics', 'Laptop', 'Pro'],
        'Classic Cotton T-Shirt' => ['Fashion', 'T-Shirt', 'Sale'],
        'Slim Fit Jeans'         => ['Fashion', 'Jeans', 'Bestseller'],
        'Flory Summer Dress'     => ['Fashion', 'Dress', 'New Arrival'],
        'Running Sneakers'       => ['Shoes', 'Sport', 'Bestseller'],
        'Modern Sofa 3-Seater'   => ['Furniture', 'Home', 'Sale'],
        'Superhero Action Figure' => ['Toys', 'Action', 'Kids'],
        'Dinosaur Figure Set'    => ['Toys', 'Educational', 'Kids'],
        'Strategy Board Game'    => ['Board Games', 'Family', 'Bestseller'],
        'STEM Building Blocks'   => ['Toys', 'Educational', 'STEM'],
        'Learning Tablet for Kids' => ['Electronics', 'Educational', 'Kids'],
    ];

    /**
     * Product name → Tags mapping (Arabic).
     */
    private array $productTagsArabic = [
        'Electronics' => 'إلكترونيات',
        'Mobile' => 'موبايل',
        'New Arrival' => 'وصل حديثا',
        'Bestseller' => 'الأكثر مبيعا',
        'Laptop' => 'لابتوب',
        'Pro' => 'برو',
        'Fashion' => 'موضة',
        'T-Shirt' => 'تي شيرت',
        'Sale' => 'تخفيضات',
        'Jeans' => 'جينز',
        'Dress' => 'فستان',
        'Shoes' => 'أحذية',
        'Sport' => 'رياضة',
        'Furniture' => 'أثاث',
        'Home' => 'منزل',
        'Toys' => 'ألعاب',
        'Action' => 'حركة',
        'Kids' => 'أطفال',
        'Board Games' => 'ألعاب لوحية',
        'Family' => 'عائلة',
        'Educational' => 'تعليمية',
        'STEM' => 'علوم وتكنولوجيا',
    ];

    /**
     * Product configuration with options and their values
     * This demonstrates the "Option + Values → Generate Combinations" pattern
     */
    private array $productsConfig = [
        'Men Clothing' => [
            'Classic Cotton T-Shirt' => [
                'options' => [
                    'Color' => ['Red', 'Blue', 'Black', 'White'],
                    'Size' => ['S', 'M', 'L', 'XL'],
                ],
                'base_price' => 29.99,
                'description' => 'Premium cotton t-shirt for everyday comfort',
                'is_featured' => true,
                'discount_percent' => 15,
            ],
            'Slim Fit Jeans' => [
                'options' => [
                    'Color' => ['Blue', 'Black', 'Light Blue'],
                    'Size' => ['30', '32', '34', '36'],
                    'Length' => ['Regular', 'Long'],
                ],
                'base_price' => 59.99,
                'description' => 'Modern slim fit jeans with stretch comfort',
            ],
        ],
        'Women Clothing' => [
            'Flory Summer Dress' => [
                'options' => [
                    'Color' => ['Pink', 'Blue', 'White', 'Yellow'],
                    'Size' => ['S', 'M', 'L', 'XL'],
                ],
                'base_price' => 49.99,
                'description' => 'Floral summer dress perfect for warm days',
                'is_featured' => true,
                'is_new' => true,
            ],
            'Yoga Pants' => [
                'options' => [
                    'Color' => ['Black', 'Dark Grey', 'Burgundy', 'Navy'],
                    'Size' => ['S', 'M', 'L', 'XL'],
                    'Length' => ['Capri', 'Regular', 'Long'],
                ],
                'base_price' => 39.99,
                'description' => 'High-waist yoga pants with moisture-wicking fabric',
            ],
        ],
        'Shoes' => [
            'Running Sneakers' => [
                'options' => [
                    'Color' => ['White', 'Black', 'Blue', 'Red'],
                    'Size' => ['38', '39', '40', '41', '42'],
                ],
                'base_price' => 89.99,
                'description' => 'Lightweight running sneakers with cushioned sole',
            ],
            'Casual Loafers' => [
                'options' => [
                    'Color' => ['Brown', 'Black', 'Navy', 'Tan'],
                    'Size' => ['38', '39', '40', '41', '42', '43'],
                ],
                'base_price' => 69.99,
                'description' => 'Comfortable casual loafers for everyday wear',
            ],
        ],
        'Phones' => [
            'iPhone 14' => [
                'options' => [
                    'Storage' => ['128GB', '256GB', '512GB'],
                    'Color' => ['Black', 'Blue', 'Purple', 'Red'],
                ],
                'base_price' => 799.99,
                'description' => 'Apple iPhone 14 with advanced camera system',
            ],
            'Samsung Galaxy S23' => [
                'options' => [
                    'Storage' => ['128GB', '256GB', '512GB'],
                    'Color' => ['Black', 'Green', 'Lavender', 'White'],
                ],
                'base_price' => 699.99,
                'description' => 'Samsung Galaxy S23 with dynamic AMOLED display',
            ],
            'Google Pixel 8' => [
                'options' => [
                    'Storage' => ['128GB', '256GB', '512GB'],
                    'Color' => ['Obsidian', 'Porcelain', 'Mint', 'Rose'],
                ],
                'base_price' => 749.99,
                'description' => 'Google Pixel 8 with advanced AI camera features',
            ],
        ],
        'Laptops' => [
            'MacBook Pro' => [
                'options' => [
                    'Storage' => ['256GB', '512GB', '1TB', '2TB'],
                    'RAM' => ['8GB', '16GB', '32GB', '64GB'],
                    'Color' => ['Space Gray', 'Silver'],
                ],
                'base_price' => 1299.99,
                'description' => 'MacBook Pro with M2 chip and Retina display',
            ],
            'Dell XPS 15' => [
                'options' => [
                    'Storage' => ['512GB', '1TB', '2TB'],
                    'RAM' => ['16GB', '32GB'],
                    'Color' => ['Graphite', 'Silver'],
                ],
                'base_price' => 1599.99,
                'description' => 'Dell XPS 15 with infinity edge display and Intel i9',
            ],
        ],
        'Furniture' => [
            'Modern Sofa 3-Seater' => [
                'options' => [
                    'Color' => ['Grey', 'Beige', 'Navy Blue', 'Dark Green'],
                    'Material' => ['Fabric', 'Leather', 'Velvet'],
                ],
                'base_price' => 499.99,
                'description' => 'Modern 3-seater sofa with comfortable cushions',
            ],
        ],
        'Accessories' => [
            'Wireless Earbuds' => [
                'options' => [
                    'Color' => ['White', 'Black', 'Blue'],
                    'Battery' => ['6h', '8h', '12h'],
                ],
                'base_price' => 79.99,
                'description' => 'High-quality wireless earbuds with noise cancellation',
            ],
        ],
        'Action Figures' => [
            'Superhero Action Figure' => [
                'options' => [
                    'Character' => ['Spider-Man', 'Batman', 'Iron Man', 'Superman'],
                    'Size' => ['6 inch', '12 inch'],
                ],
                'base_price' => 24.99,
                'description' => 'Detailed superhero action figure with movable joints',
            ],
            'Dinosaur Figure Set' => [
                'options' => [
                    'Set Size' => ['3-Pack', '6-Pack', '12-Pack'],
                    'Type' => ['Herbivores', 'Carnivores', 'Mixed'],
                ],
                'base_price' => 19.99,
                'description' => 'Realistic dinosaur figures for educational play',
            ],
        ],
        'Board Games' => [
            'Strategy Board Game' => [
                'options' => [
                    'Edition' => ['Standard', 'Deluxe', 'Travel'],
                    'Players' => ['2-4', '2-6', '4-8'],
                ],
                'base_price' => 34.99,
                'description' => 'Engaging strategy board game for family fun',
            ],
            'Classic Card Game Set' => [
                'options' => [
                    'Type' => ['Poker', 'Bridge', 'Multi-Game'],
                    'Quality' => ['Standard', 'Premium'],
                ],
                'base_price' => 14.99,
                'description' => 'Classic card game set with durable cards',
            ],
        ],
        'Educational Toys' => [
            'STEM Building Blocks' => [
                'options' => [
                    'Pieces' => ['100', '200', '500'],
                    'Theme' => ['City', 'Space', 'Vehicles'],
                ],
                'base_price' => 39.99,
                'description' => 'Educational building blocks for creative STEM learning',
            ],
            'Learning Tablet for Kids' => [
                'options' => [
                    'Age Group' => ['3-5 years', '6-8 years', '9-12 years'],
                    'Color' => ['Blue', 'Pink', 'Green'],
                ],
                'base_price' => 89.99,
                'description' => 'Interactive learning tablet with educational games',
            ],
        ],
    ];

    public function run()
    {
        $storeId = $this->demoStoreId();
        DB::beginTransaction();

        foreach ($this->productsConfig as $categoryName => $products) {
            $categoryName = trim($categoryName);
            $category = Category::query()
                ->where('store_id', $storeId)
                ->whereHas('translations', function ($query) use ($categoryName) {
                    $query->where('name', $categoryName)->where('locale', 'en');
                })
                ->first();

            if (!$category) {
                $this->command->error("❌ Category '{$categoryName}' not found!");
                continue;
            }

            foreach ($products as $productName => $config) {
                $this->command->info("📦 Processing: $productName");

                // ── Resolve brand ──────────────────────────────
                $brandId = null;
                if (isset($this->productBrands[$productName])) {
                    $brand =$brandName = $this->productBrands[$productName];
                    $brand = Brand::updateOrCreate(
                        [
                            'slug' => Str::slug($brandName),
                            'store_id' => $storeId,
                        ],
                        [
                            'name' => $brandName,
                            'is_active' => true,
                        ],
                    );
                    $brandId = $brand->id;
                }

                // ── Create product ─────────────────────────────
                $productSlug = Str::slug($productName);
                $product = Product::query()
                    ->where('store_id', $storeId)
                    ->whereHas('translations', function ($query) use ($productSlug) {
                        $query->where('slug', $productSlug)->where('locale', 'en');
                    })
                    ->first();

                if (!$product) {
                    $product = Product::create([
                        'category_id' => $category->id,
                        'brand_id'    => $brandId,
                        'store_id'    => $storeId,
                        'is_active'   => true,
                        'is_featured' => (bool) ($config['is_featured'] ?? false),
                        'is_new'      => (bool) ($config['is_new'] ?? false),
                    ]);

                    // ── Product translations ───────────────────────
                    $product->translations()->create([
                        'locale'      => 'en',
                        'name'        => $productName,
                        'description' => $config['description'] ?? "$productName — premium quality, fast shipping.",
                        'slug'        => $productSlug,
                    ]);

                    $product->translations()->create([
                        'locale'      => 'ar',
                        'name'        => $this->getArabicProductName($productName),
                        'description' => $this->getArabicDescription($productName),
                        'slug'        => Str::slug($this->getArabicProductName($productName)),
                    ]);
                } else {
                    $product->update([
                        'is_featured' => (bool) ($config['is_featured'] ?? false),
                        'is_new' => (bool) ($config['is_new'] ?? false),
                    ]);
                }

                // ── STEP 1: Create Options and their Values ────
                $createdOptions = [];
                $allCombinations = [];

                foreach ($config['options'] as $optionName => $optionValues) {
                    // Create ProductOption
                    $option = ProductOption::create([
                        'product_id' => $product->id,
                        'name'       => $optionName,
                        'position'   => count($createdOptions),
                    ]);

                    // Create ProductOptionValues
                    $optionValueModels = [];
                    foreach ($optionValues as $position => $value) {
                        $optionValue = ProductOptionValue::create([
                            'option_id' => $option->id,
                            'value'     => $value,
                        ]);
                        $optionValueModels[] = $optionValue;
                    }

                    $createdOptions[$optionName] = [
                        'option' => $option,
                        'values' => $optionValueModels,
                    ];
                }

                // ── STEP 2: Generate All Combinations (Cartesian Product) ──
                $combinations = $this->generateCombinations($createdOptions);
                $this->command->info("   ✓ Generated " . count($combinations) . " variant combinations");

                // ── STEP 3: Create Product Variants from Combinations ──
                $firstVariant = null;
                $variantPrice = $config['base_price'] ?? rand(50, 999);

                foreach ($combinations as $index => $combination) {
                    // Generate SKU from combination values
                    $skuParts = [$productName];
                    foreach ($combination['values'] as $value) {
                        $skuParts[] = $value->value;
                    }
                    $sku = strtoupper(Str::slug(implode('-', $skuParts))) . '-' . rand(1000, 9999);

                    // Adjust price slightly for different combinations (optional)
                    $priceAdjustment = $this->calculatePriceAdjustment($combination['values']);
                    $finalPrice = max(0.99, $variantPrice + $priceAdjustment);

                    // Create variant
                    $sellingPrice = round($finalPrice, 2);
                    $costPrice = round($sellingPrice * 0.6, 2); // Cost is 60% of selling price
                    $discountPercent = (float) ($config['discount_percent'] ?? 0);
                    $compareAtPrice = $discountPercent > 0 && $discountPercent < 100
                        ? round($sellingPrice / (1 - ($discountPercent / 100)), 2)
                        : null;

                    $variant = ProductVariant::create([
                        'product_id'       => $product->id,
                        'sku'              => $sku,
                        'price'            => $sellingPrice,
                        'compare_at_price' => $compareAtPrice,
                        'cost_price'       => $costPrice,
                        'quantity'         => rand(5, 100),
                        'batch_number'     => 'BATCH-' . date('Ymd') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                        'manufacture_date' => now()->subMonths(rand(1, 6)),
                        'expiry_date'      => now()->addMonths(rand(12, 36)),
                        'is_active'        => true,
                    ]);

                    if (!$firstVariant) {
                        $firstVariant = $variant;
                    }

                    // Add default image
                    Image::create([
                        'imageable_id'   => $variant->id,
                        'imageable_type' => ProductVariant::class,
                        'image_url'      => $this->seedStaticAsset('default-variant.png', 'variants/default.png'),
                        'is_primary'     => true,
                    ]);

                    // Link variant to its option values
                    foreach ($combination['mapping'] as $optionName => $value) {
                        DB::table('variant_option_values')->insert([
                            'variant_id'       => $variant->id,
                            'option_value_id'  => $value->id,
                            'option_id'        => $createdOptions[$optionName]['option']->id,
                        ]);
                    }
                }

                // ── Set default variant ────────────────────────
                if ($firstVariant) {
                    $product->update(['product_variant_id' => $firstVariant->id]);
                }

                // ── Attach tags ────────────────────────────────
                if (isset($this->productTags[$productName])) {
                    $tagIds = [];
                    foreach ($this->productTags[$productName] as $tagName) {
                        $tag = Tag::where('store_id', $product->store_id)
                            ->whereHas('translations', function ($query) use ($tagName) {
                                $query->where('name', $tagName)->where('locale', 'en');
                            })->first();

                        if (!$tag) {
                            $tag = Tag::create([
                                'store_id' => $product->store_id,
                                'is_active' => true,
                            ]);

                            $tag->translations()->create([
                                'locale' => 'en',
                                'name' => $tagName,
                                'slug' => Str::slug($tagName),
                            ]);

                            if (isset($this->productTagsArabic[$tagName])) {
                                $arabicTagName = $this->productTagsArabic[$tagName];
                                $tag->translations()->create([
                                    'locale' => 'ar',
                                    'name' => $arabicTagName,
                                    'slug' => Str::slug($arabicTagName),
                                ]);
                            }
                        }

                        $tagIds[] = $tag->id;
                    }
                    $product->tags()->sync($tagIds);
                    $this->command->info("   ✓ Attached " . count($tagIds) . " tags");
                }

                $this->command->info("   ✅ Created " . count($combinations) . " variants for $productName");
            }
        }

        DB::commit();
        $this->command->info("\n✅ Products seeded successfully with Option + Value → Generate Combinations pattern!");
        $this->command->info("   Each product has options and all possible combinations were generated automatically.");
    }

    /**
     * Generate all possible combinations of option values (Cartesian product)
     * 
     * @param array $createdOptions Format: ['OptionName' => ['option' => $option, 'values' => [$value1, $value2]]]
     * @return array List of combinations, each containing 'mapping' and 'values'
     * 
     * Example Output:
     * [
     *     [
     *         'mapping' => ['Color' => $redValue, 'Size' => $smallValue],
     *         'values' => [$redValue, $smallValue]
     *     ],
     *     ...
     * ]
     */
    private function generateCombinations(array $createdOptions): array
    {
        $combinations = [];
        $optionNames = array_keys($createdOptions);
        $optionValuesList = array_map(function ($option) use ($createdOptions) {
            return $createdOptions[$option]['values'];
        }, $optionNames);

        // Generate Cartesian product
        $combinationsData = $this->cartesianProduct($optionValuesList);

        foreach ($combinationsData as $combinationValues) {
            $mapping = [];
            foreach ($optionNames as $index => $optionName) {
                $mapping[$optionName] = $combinationValues[$index];
            }
            $combinations[] = [
                'mapping' => $mapping,
                'values' => $combinationValues,
            ];
        }

        return $combinations;
    }

    /**
     * Calculate Cartesian product of multiple arrays
     * 
     * @param array $arrays List of arrays to combine
     * @return array All possible combinations
     */
    private function cartesianProduct(array $arrays): array
    {
        $result = [[]];
        foreach ($arrays as $array) {
            $temp = [];
            foreach ($result as $combination) {
                foreach ($array as $item) {
                    $temp[] = array_merge($combination, [$item]);
                }
            }
            $result = $temp;
        }
        return $result;
    }

    /**
     * Calculate price adjustment based on option values
     * Example: Higher storage or RAM increases price
     */
    private function calculatePriceAdjustment(array $optionValues): float
    {
        $adjustment = 0;

        foreach ($optionValues as $value) {
            $valueString = $value->value;
            
            // Storage adjustments
            if (str_contains($valueString, 'GB') || str_contains($valueString, 'TB')) {
                if ($valueString === '256GB') $adjustment += 50;
                elseif ($valueString === '512GB') $adjustment += 100;
                elseif ($valueString === '1TB') $adjustment += 200;
                elseif ($valueString === '2TB') $adjustment += 400;
            }
            
            // RAM adjustments
            if (str_contains($valueString, 'GB') && !str_contains($valueString, 'Storage')) {
                if ($valueString === '16GB') $adjustment += 80;
                elseif ($valueString === '32GB') $adjustment += 160;
                elseif ($valueString === '64GB') $adjustment += 320;
            }
            
            // Material adjustments
            if ($valueString === 'Leather') $adjustment += 100;
            if ($valueString === 'Velvet') $adjustment += 80;
            
            // Size doesn't affect price, length might
            if ($valueString === 'Long') $adjustment += 10;
        }

        return $adjustment;
    }

    /**
     * Display combinations in a readable format (for debugging)
     */
    private function displayCombinations(array $combinations): void
    {
        foreach ($combinations as $index => $combination) {
            $values = [];
            foreach ($combination['mapping'] as $optionName => $value) {
                $values[] = "$optionName: {$value->value}";
            }
            $this->command->info("      Combo " . ($index + 1) . ": " . implode(', ', $values));
        }
    }

    private function getArabicProductName($englishName)
    {
        $translations = [
            'iPhone 14' => 'آيفون 14',
            'Samsung Galaxy S23' => 'سامسونج جالاكسي S23',
            'Google Pixel 8' => 'جوجل بيكسل 8',
            'Xiaomi Redmi Note 12' => 'شاومي ريدمي نوت 12',
            'Wireless Earbuds' => 'سماعات لاسلكية',
            'Smartwatches' => 'ساعات ذكية',
            'Laptop Bags' => 'حقائب لابتوب',
            'Superhero Action Figure' => 'شخصية خارقة متحركة',
            'Dinosaur Figure Set' => 'مجموعة أشكال الديناصورات',
            'Strategy Board Game' => 'لعبة لوحة استراتيجية',
            'Classic Card Game Set' => 'مجموعة لعبة ورق كلاسيكية',
            'STEM Building Blocks' => 'مكعبات بناء تعليمية',
            'Learning Tablet for Kids' => 'جهاز لوحي تعليمي للأطفال',
            'External Monitors' => 'شاشات خارجية',
            'Mechanical Keyboards' => 'لوحات مفاتيح ميكانيكية',
            'Wireless Mice' => 'فئران لاسلكية',
            'USB-C Hubs' => 'موزعات USB-C',
            'Portable SSDs' => 'أقراص SSD محمولة',
            'MacBook Pro' => 'ماك بوك برو',
            'Dell XPS 13' => 'ديل XPS 13',
            'Dell XPS 15' => 'ديل XPS 15',
            'HP Spectre x360' => 'إتش بي سبيكتر x360',
            'Lenovo ThinkPad X1' => 'لينوفو ثينك باد X1',
            'ASUS ROG Zephyrus' => 'آسوس ROG زيفيروس',
            'Classic Cotton T-Shirt' => 'تي شيرت قطني كلاسيكي',
            'Slim Fit Jeans' => 'جينز بقصة ضيقة',
            'Winter Hoodie' => 'هودي شتوي',
            'Flory Summer Dress' => 'فستان صيفي زهري',
            'High-Waist Jeans' => 'جينز عالي الخصر',
            'Oversized Sweater' => 'كنزة صوف كبيرة',
            'Classic Blazer' => 'بليزر كلاسيكي',
            'Yoga Pants' => 'بنطلون يوجا',
            'Running Sneakers' => 'حذاء جري رياضي',
            'Leather Boots' => 'جزمة جلدية',
            'High Heels' => 'كعب عالي',
            'Casual Loafers' => 'لوففرز كاجوال',
            'Sports Sandals' => 'صنادل رياضية',
            'Air Fryer 4L' => 'مقلاة هوائية 4 لتر',
            'Electric Kettle 1.7L' => 'غلاية كهربائية 1.7 لتر',
            'Blender 1200W' => 'خلاط 1200 واط',
            'Modern Sofa 3-Seater' => 'كنبة حديثة 3 مقاعد',
            'Wooden Dining Table' => 'طاولة طعام خشبية',
            'Office Ergonomic Chair' => 'كرسي مكتب مريح',
            'Queen Size Bed Frame' => 'هيكل سرير مقاس كوين',
            'Bookshelf 5-Tier' => 'رف كتب 5 طبقات',
            'Ceramic Table Vase' => 'مزهرية سيراميك للطاولة',
            'Wall Clock Modern' => 'ساعة حائط عصرية',
            'LED Floor Lamp' => 'مصباح أرضي LED',
            'Abstract Wall Art' => 'لوحة حائط تجريدية',
            'Decorative Throw Pillows' => 'وسائد زخرفية',
            'Indoor Plant Set' => 'مجموعة نباتات داخلية',
            'Adjustable Dumbbells' => 'دمبلز قابل للتعديل',
            'Yoga Mat Pro' => 'سجادة يوجا احترافية',
            'Pull-Up Bar' => 'بار عقلة',
        ];

        return $translations[$englishName] ?? $englishName;
    }

    private function getArabicDescription($productName)
    {
        $descriptions = [
            'Classic Cotton T-Shirt' => 'تي شيرت قطني فاخر مريح للاستخدام اليومي',
            'Slim Fit Jeans' => 'جينز عصري بقصة ضيقة مع قماش مطاطي مريح',
            'Flory Summer Dress' => 'فستان صيفي زهري مثالي للأيام الدافئة',
            'Yoga Pants' => 'بنطلون يوجا عالي الخصر بقماش يمتص الرطوبة',
            'Running Sneakers' => 'حذاء جري رياضي خفيف الوزن بنعل مبطن',
            'Casual Loafers' => 'لوففرز كاجوال مريحة للاستخدام اليومي',
            'iPhone 14' => 'آيفون 14 من أبل مع نظام كاميرا متقدم',
            'Samsung Galaxy S23' => 'سامسونج جالاكسي S23 بشاشة AMOLED ديناميكية',
            'Google Pixel 8' => 'جوجل بيكسل 8 مع كاميرا ذكاء اصطناعي متقدمة',
            'MacBook Pro' => 'ماك بوك برو مع شريحة M2 وشاشة ريتينا',
            'Dell XPS 15' => 'ديل XPS 15 مع شاشة إنفينيتي إيدج ومعالج إنتل i9',
            'Modern Sofa 3-Seater' => 'كنبة عصرية بثلاثة مقاعد مع وسائد مريحة',
            'Wireless Earbuds' => 'سماعات لاسلكية عالية الجودة مع إلغاء الضوضاء',
            'Superhero Action Figure' => 'شخصية خارقة متحركة مفصلة بمفاصل قابلة للحركة',
            'Dinosaur Figure Set' => 'مجموعة أشكال ديناصورات واقعية للعب التعليمي',
            'Strategy Board Game' => 'لعبة لوحة استراتيجية ممتعة للعائلة',
            'Classic Card Game Set' => 'مجموعة ألعاب ورق كلاسيكية ببطاقات متينة',
            'STEM Building Blocks' => 'مكعبات بناء تعليمية للتعلم الإبداعي',
            'Learning Tablet for Kids' => 'جهاز لوحي تعليمي تفاعلي مع ألعاب تعليمية',
        ];

        return $descriptions[$productName] ?? 'وصف ' . $this->getArabicProductName($productName) . ' — جودة ممتازة، شحن سريع.';
    }
}