<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant\Story;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateStoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Store authorization is performed by StorePolicy in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm',
                'max:30720',
            ],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'product_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if (! $file || ! $file->isValid()) {
                return;
            }

            $mime = (string) $file->getMimeType();
            if (str_starts_with($mime, 'image/') && $file->getSize() > 5 * 1024 * 1024) {
                $validator->errors()->add('file', 'Story images must not exceed 5 MB.');
            }
        });
    }
}
