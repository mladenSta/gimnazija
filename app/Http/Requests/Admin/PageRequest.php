<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Rules\AvailableSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? ($this->user()?->can('update', $page) ?? false)
            : ($this->user()?->can('create', Page::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', new AvailableSlug($page instanceof Page ? $page : null)],
            'content' => ['nullable', 'string', 'max:2000000'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'gallery_ids' => ['array', 'max:200'],
            'gallery_ids.*' => ['integer', 'distinct', 'exists:media,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'naslov',
            'slug' => 'link',
            'content' => 'tekst',
            'status' => 'status',
            'published_at' => 'datum objave',
            'featured_media_id' => 'naslovna slika',
            'gallery_ids' => 'galerija',
            'gallery_ids.*' => 'fotografija u galeriji',
            'meta_title' => 'SEO naslov',
            'meta_description' => 'SEO opis',
        ];
    }
}
