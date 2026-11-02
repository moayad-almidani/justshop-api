<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class GetMarketplaceHomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'max:5'],
            'stores_limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'stories_limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'promotional_banners_limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'categories_limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'featured_products_limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ];
    }
}
