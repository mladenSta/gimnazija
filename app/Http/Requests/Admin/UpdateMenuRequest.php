<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole main-menu tree, two levels deep:
 * items: [{ id?, type, page_id?, category_id?, url?, label?, children: [...] }]
 */
class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['items' => ['present', 'array', 'max:100']];

        foreach (['items.*', 'items.*.children.*'] as $prefix) {
            $rules += [
                "{$prefix}.id" => ['nullable', 'integer'],
                "{$prefix}.type" => ['required', Rule::enum(MenuItemType::class)],
                "{$prefix}.page_id" => ["required_if:{$prefix}.type,page", 'nullable', 'integer', 'exists:pages,id'],
                "{$prefix}.category_id" => ["required_if:{$prefix}.type,category", 'nullable', 'integer', 'exists:categories,id'],
                "{$prefix}.url" => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://|mailto:|tel:|\#)#i'],
                "{$prefix}.label" => ["required_if:{$prefix}.type,custom", 'nullable', 'string', 'max:255'],
            ];
        }

        $rules['items.*.children'] = ['array', 'max:50'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $i => $item) {
                foreach ((array) ($item['children'] ?? []) as $j => $child) {
                    if (! empty($child['children'])) {
                        $validator->errors()->add("items.{$i}.children.{$j}.children", 'Meni može imati najviše dva nivoa (stavka i podmeni).');
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.url.regex' => 'Link mora početi sa /, http://, https://, mailto:, tel: ili #.',
            'items.*.url.regex' => 'Link mora početi sa /, http://, https://, mailto:, tel: ili #.',
            'items.*.children.*.url.regex' => 'Link mora početi sa /, http://, https://, mailto:, tel: ili #.',
            'items.*.label.required_if' => 'Unesite naziv stavke.',
            'items.*.children.*.label.required_if' => 'Unesite naziv stavke.',
            'items.*.page_id.exists' => 'Stranica više ne postoji.',
            'items.*.children.*.page_id.exists' => 'Stranica više ne postoji.',
            'items.*.category_id.exists' => 'Kategorija više ne postoji.',
            'items.*.children.*.category_id.exists' => 'Kategorija više ne postoji.',
        ];
    }
}
