<?php

declare(strict_types=1);

namespace App\Actions\Marketplace;

use App\DTOs\Marketplace\GetMarketplaceHomeDTO;
use App\Repositories\Marketplace\MarketplaceHomeRepository;

final class GetMarketplaceHomeAction
{
    public function __construct(private MarketplaceHomeRepository $repository) {}

    public function execute(GetMarketplaceHomeDTO $dto): array
    {
        return [
            'stores' => $this->repository->stores($dto->storesLimit),
            'stories' => $this->repository->storyGroups($dto->storiesLimit),
            'promotional_banners' => $this->repository->promotionalBanners($dto->promotionalBannersLimit),
            'categories' => $this->repository->categories($dto->categoriesLimit),
            'featured_products' => $this->repository->featuredProducts($dto->featuredProductsLimit),
        ];
    }
}
