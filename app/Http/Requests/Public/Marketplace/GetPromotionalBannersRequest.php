<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class GetPromotionalBannersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'max:5'],
            'promotional_banners_limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
