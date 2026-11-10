<?php

namespace App\Http\Requests\Admin;

use App\Models\Gallery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Gallery name + the full, ordered list of items (saved at once).
 */
class UpdateGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gallery = $this->route('gallery');

        return $gallery instanceof Gallery && ($this->user()?->can('update', $gallery) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'items' => ['present', 'array', 'max:300'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.media_id' => ['required', 'integer', 'exists:media,id'],
            'items.*.caption' => ['nullable', 'string', 'max:255'],
            'items.*.focal_x' => ['required', 'integer', 'between:0,100'],
            'items.*.focal_y' => ['required', 'integer', 'between:0,100'],
            'items.*.tags' => ['array', 'max:20'],
            'items.*.tags.*' => ['string', 'max:50'],
            'items.*.badge' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.subtitle' => ['nullable', 'string', 'max:500'],
            'items.*.link_label' => ['nullable', 'string', 'max:255'],
            'items.*.link_url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://|mailto:|tel:|\#)#i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'naziv',
            'items.*.caption' => 'opis',
            'items.*.badge' => 'oznaka',
            'items.*.title' => 'naslov',
            'items.*.subtitle' => 'podnaslov',
            'items.*.link_label' => 'tekst dugmeta',
            'items.*.link_url' => 'link dugmeta',
            'items.*.tags.*' => 'tag',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.link_url.regex' => 'Link mora početi sa /, http://, https://, mailto:, tel: ili #.',
            'items.*.media_id.exists' => 'Fotografija više ne postoji.',
        ];
    }
}
