<?php

declare(strict_types=1);

namespace App\DTOs\Marketplace;

use App\Http\Requests\Public\Marketplace\GetMarketplaceHomeRequest;

final readonly class GetMarketplaceHomeDTO
{
    public function __construct(
        public string $locale,
        public int $storesLimit,
        public int $storiesLimit,
        public int $promotionalBannersLimit,
        public int $categoriesLimit,
        public int $featuredProductsLimit,
    ) {}

    public static function fromRequest(GetMarketplaceHomeRequest $request): self
    {
        $data = $request->validated();

        return new self(
            locale: $data['locale'] ?? app()->getLocale(),
            storesLimit: (int) ($data['stores_limit'] ?? 10),
            storiesLimit: (int) ($data['stories_limit'] ?? 10),
            promotionalBannersLimit: (int) ($data['promotional_banners_limit'] ?? 10),
            categoriesLimit: (int) ($data['categories_limit'] ?? 12),
            featuredProductsLimit: (int) ($data['featured_products_limit'] ?? 12),
        );
    }
}
