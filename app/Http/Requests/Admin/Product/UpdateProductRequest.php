<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        Log::info('hereNEW',[]);
               
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |------------------------------------------------------------------
            | Product
            |------------------------------------------------------------------
            */

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
            ],

            'is_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'is_featured' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'is_new' => [
                'sometimes',
                'boolean',
            ],

            'sync_variants' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            /*
            |------------------------------------------------------------------
            | Translations
            |------------------------------------------------------------------
            */

            'translations' => [
                'sometimes',
                'nullable',
                'array',
                'min:1',
            ],

            'translations.*.locale' => [
                'required_with:translations',
                'string',
                'size:2',
                Rule::in(
                    config(
                        'content.editable_locales',
                        config('app.supported_locales', [])
                    )
                ),
            ],

            'translations.*.name' => [
                'required_with:translations',
                'string',
                'max:255',
            ],

            'translations.*.slug' => [
                'required_with:translations',
                'string',
                'max:255',
            ],

            'translations.*.description' => [
                'nullable',
                'string',
            ],

            'translations.*.seo_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'translations.*.seo_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            |------------------------------------------------------------------
            | Canonical Product Options
            |------------------------------------------------------------------
            */

            'options' => [
                'sometimes',
                'nullable',
                'array',
                'max:3',
            ],

            'options.*.name' => [
                'required',
                'string',
                'max:100',
            ],

            'options.*.position' => [
                'required',
                'integer',
                'min:1',
            ],

            'options.*.values' => [
                'required',
                'array',
                'min:1',
            ],

            'options.*.values.*' => [
                'required',
                'string',
                'max:100',
            ],

            /*
            |------------------------------------------------------------------
            | Variants
            |------------------------------------------------------------------
            */

            'variants' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'variants.*.id' => [
                'sometimes',
                'nullable',
                'integer',
            ],

            /*
            |------------------------------------------------------------------
            | Variant Core
            |------------------------------------------------------------------
            */

            // FIX: was ['required', 'string', 'max:100']
            // Changed to nullable + distinct to match CreateProductRequest
            // and to prevent 422 on structure saves with auto-generated variants
            // that have no SKU yet.
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
            ],

            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:100',
            ],

            'variants.*.price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'variants.*.compare_at_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.cost_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.quantity' => [
                'required',
                'integer',
                'min:0',
            ],

            'variants.*.low_stock_threshold' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.track_inventory' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'variants.*.is_active' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            /*
            |------------------------------------------------------------------
            | Physical / Shipping
            |------------------------------------------------------------------
            */

            'variants.*.weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.weight_unit' => [
                'nullable',
                'string',
                Rule::in(['g', 'kg', 'lb']),
            ],

            /*
            |------------------------------------------------------------------
            | Batch / Expiry
            |------------------------------------------------------------------
            */

            'variants.*.manufacture_date' => [
                'nullable',
                'date',
            ],

            'variants.*.expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:variants.*.manufacture_date',
            ],

            'variants.*.batch_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |------------------------------------------------------------------
            | Variant Option Mapping
            |------------------------------------------------------------------
            */

            'variants.*.options' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'variants.*.options.*' => [
                'sometimes',
                'string',
                'max:100',
            ],

            /*
            |------------------------------------------------------------------
            | Variant Media
            |------------------------------------------------------------------
            */

            'variants.*.media' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'variants.*.media.*.url' => [
                'required',
                'string',
            ],

            'variants.*.media.*.alt' => [
                'nullable',
                'string',
                'max:255',
            ],

            'variants.*.media.*.position' => [
                'nullable',
                'integer',
                'min:0',
            ],

            /*
            |------------------------------------------------------------------
            | Product-Level Media
            |------------------------------------------------------------------
            */

            'media' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'media.*.url' => [
                'required',
                'string',
            ],

            'media.*.alt' => [
                'nullable',
                'string',
                'max:255',
            ],

            'media.*.position' => [
                'nullable',
                'integer',
                'min:0',
            ],

            /*
            |------------------------------------------------------------------
            | Tags
            |------------------------------------------------------------------
            |
            | Products reference tags by integer ID only.
            | Tag creation and translation management are handled by the
            | dedicated tag management API, not by product endpoints.
            | Absence of this key = no change to existing tag assignments.
            | Sending an empty array = detach all tags from this product.
            |------------------------------------------------------------------
            */

            'tags' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'tags.*' => [
                'integer',
                'exists:tags,id',
            ],
        ];
    }
}
