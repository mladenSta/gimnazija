<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms.images.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = (int) round(config('cms.images.max_upload_kb') / 1024);

        return [
            'file.required' => 'Izaberite fotografiju.',
            'file.uploaded' => "Fotografija nije otpremljena. Najveća dozvoljena veličina je {$mb} MB.",
            'file.image' => 'Fajl mora biti fotografija (JPG, PNG ili WebP).',
            'file.mimes' => 'Dozvoljeni formati su JPG, PNG i WebP.',
            'file.max' => "Fotografija je prevelika. Najveća dozvoljena veličina je {$mb} MB.",
        ];
    }
}
