<?php

declare(strict_types=1);

namespace App\Actions\Marketplace;

use App\DTOs\Marketplace\GetPromotionalBannersDTO;
use App\Repositories\Marketplace\MarketplaceHomeRepository;
use Illuminate\Support\Collection;

final class GetPromotionalBannersAction
{
    public function __construct(private MarketplaceHomeRepository $repository)
    {
    }

    public function execute(GetPromotionalBannersDTO $dto): Collection
    {
        return $this->repository->promotionalBanners($dto->limit);
    }
}
