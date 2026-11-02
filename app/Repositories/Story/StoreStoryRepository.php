<?php

declare(strict_types=1);

namespace App\Repositories\Story;

use App\Models\Story\Story;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StoreStoryRepository
{
    /** Merchant view: include drafts and expired stories for management. */
    public function paginateForStore(int $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return Story::query()
            ->where('store_id', $storeId)
            ->with(['mediaAttachment.media', 'product.translations'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
