<?php

declare(strict_types=1);

namespace App\DTOs\Marketplace;

use App\Http\Requests\Public\Marketplace\GetPromotionalBannersRequest;

final readonly class GetPromotionalBannersDTO
{
    public function __construct(public int $limit)
    {
    }

    public static function fromRequest(GetPromotionalBannersRequest $request): self
    {
        $data = $request->validated();

        return new self((int) ($data['promotional_banners_limit'] ?? 10));
    }
}
