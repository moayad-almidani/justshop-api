<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller performs store-scoped policy authorization.
    }

    public function rules(): array
    {
        $entity = $this->route('entity');
        $isCatalog = in_array($entity, ['products', 'variants'], true);

        return [
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm', 'max:30720'],
            'role' => [Rule::prohibitedIf($isCatalog), 'sometimes', 'string', Rule::in(
                $entity === 'stores' ? ['logo', 'banner', 'gallery', 'icon'] : ['icon', 'banner', 'gallery']
            )],
            'is_main' => [Rule::prohibitedIf(! $isCatalog), 'sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');
            if (! $file || ! $file->isValid()) {
                return;
            }
            $mime = $file->getMimeType();
            if (str_starts_with((string) $mime, 'image/') && $file->getSize() > 5 * 1024 * 1024) {
                $validator->errors()->add('file', 'Images must not exceed 5 MB.');
            }
            if (in_array($this->input('role'), ['logo', 'icon'], true)
                && ! str_starts_with((string) $mime, 'image/')) {
                $validator->errors()->add('role', 'Logo and icon attachments must be images.');
            }
            if (in_array($this->route('entity'), ['products', 'variants'], true)
                && $this->boolean('is_main') && ! str_starts_with((string) $mime, 'image/')) {
                $validator->errors()->add('is_main', 'The main product media must be an image.');
            }
        });
    }
}
