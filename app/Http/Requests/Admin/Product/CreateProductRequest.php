<?php

namespace App\Http\Requests\Admin\Product;

use App\Enums\Product\ProductStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
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

            'status' => [
                'required',
                new Enum(ProductStatusEnum::class),
            ],

            'is_featured' => [
                'sometimes',
                'boolean',
            ],

            'is_new' => [
                'sometimes',
                'boolean',
            ],

            /*
            |------------------------------------------------------------------
            | Translations
            |------------------------------------------------------------------
            */

            'translations' => [
                'required',
                'array',
                'min:1',
            ],

            'translations.*.locale' => [
                'required',
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
                'required',
                'string',
                'max:255',
            ],

            'translations.*.slug' => [
                'required',
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
            |
            | Source of truth for variant composition.
            | Example:
            |
            | options: [
            |   {
            |     "name": "Color",
            |     "position": 1,
            |     "values": ["Red", "Blue"]
            |   }
            | ]
            |------------------------------------------------------------------
            */

            'options' => [
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
                'required',
                'array',
                'min:1',
            ],

            /*
            |------------------------------------------------------------------
            | Variant Core
            |------------------------------------------------------------------
            */

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
                'boolean',
            ],

            'variants.*.is_active' => [
                'required',
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
            |
            | Semantic ownership. No DB IDs exposed.
            |
            | Example:
            |
            | "options": {
            |   "Color": "Red",
            |   "Size": "XL"
            | }
            |------------------------------------------------------------------
            */

            'variants.*.options' => [
                'required',
                'array',
            ],

            'variants.*.options.*' => [
                'required',
                'string',
                'max:100',
            ],

            /*
            |------------------------------------------------------------------
            | Variant Media
            |------------------------------------------------------------------
            */

            'variants.*.media' => [
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
            | The tag must exist in the tags table (store-scoped validation
            | is enforced at the Action layer via store membership checks).
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $variants = $this->input('variants', []);
            $options  = $this->input('options', []);

            /*
            |------------------------------------------------------------------
            | Canonical Option Map
            |------------------------------------------------------------------
            */

            $canonicalOptions = [];

            foreach ($options as $option) {

                $name = $option['name'] ?? null;

                if (!$name) {
                    continue;
                }

                $canonicalOptions[$name] = collect(
                    $option['values'] ?? []
                )->values()->toArray();
            }

            /*
            |------------------------------------------------------------------
            | Duplicate Variant Combination Check
            |------------------------------------------------------------------
            */

            $seenCombinations = [];

            foreach ($variants as $index => $variant) {

                $variantOptions = $variant['options'] ?? [];

                ksort($variantOptions);

                $signature = json_encode($variantOptions);

                if (in_array($signature, $seenCombinations, true)) {

                    $validator->errors()->add(
                        "variants.$index.options",
                        __('product.duplicate_variant_combination')
                    );
                }

                $seenCombinations[] = $signature;

                /*
                |--------------------------------------------------------------
                | Validate Option Keys
                |--------------------------------------------------------------
                */

                foreach ($variantOptions as $optionName => $value) {

                    if (!array_key_exists($optionName, $canonicalOptions)) {

                        $validator->errors()->add(
                            "variants.$index.options.$optionName",
                            __('product.invalid_variant_option')
                        );

                        continue;
                    }

                    if (!in_array($value, $canonicalOptions[$optionName], true)) {

                        $validator->errors()->add(
                            "variants.$index.options.$optionName",
                            __('product.invalid_variant_option_value')
                        );
                    }
                }

                /*
                |--------------------------------------------------------------
                | Validate Missing Dimensions
                |--------------------------------------------------------------
                */

                foreach (array_keys($canonicalOptions) as $requiredOption) {

                    if (!array_key_exists($requiredOption, $variantOptions)) {

                        $validator->errors()->add(
                            "variants.$index.options",
                            __('product.missing_variant_option')
                        );
                    }
                }
            }
        });
    }
}
