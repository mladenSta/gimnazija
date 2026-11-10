<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Support\Slug;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? ($this->user()?->can('update', $category) ?? false)
            : ($this->user()?->can('create', Category::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug', ''));

        $this->merge([
            'slug' => $slug !== '' ? $slug : Slug::make((string) $this->input('name', '')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $id = $category instanceof Category ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:200', 'regex:/^[a-z0-9%]+(?:-[a-z0-9%]+)*$/',
                Rule::unique('categories', 'slug')->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => [
                'nullable', 'integer', 'exists:categories,id',
                function (string $attribute, mixed $value, Closure $fail) use ($category) {
                    if ($category instanceof Category && $value !== null && $this->wouldCreateCycle($category, (int) $value)) {
                        $fail('Kategorija ne može biti podkategorija same sebe.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'naziv',
            'slug' => 'link',
            'description' => 'opis',
            'parent_id' => 'nadređena kategorija',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Link može sadržati samo mala slova bez kvačica, brojeve i crtice.',
            'slug.unique' => 'Ovaj link već koristi druga kategorija.',
        ];
    }

    private function wouldCreateCycle(Category $category, int $parentId): bool
    {
        $current = $parentId;
        $guard = 0;

        while ($current !== 0 && $guard++ < 100) {
            if ($current === $category->id) {
                return true;
            }

            $current = (int) Category::query()->whereKey($current)->value('parent_id');
        }

        return false;
    }
}
