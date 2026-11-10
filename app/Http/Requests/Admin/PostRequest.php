<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use App\Models\Post;
use App\Rules\AvailableSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post
            ? ($this->user()?->can('update', $post) ?? false)
            : ($this->user()?->can('create', Post::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', new AvailableSlug($post instanceof Post ? $post : null)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'gallery_ids' => ['array', 'max:200'],
            'gallery_ids.*' => ['integer', 'distinct', 'exists:media,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
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
            'excerpt' => 'kratak opis',
            'content' => 'tekst',
            'status' => 'status',
            'published_at' => 'datum objave',
            'featured_media_id' => 'naslovna slika',
            'gallery_ids' => 'galerija',
            'gallery_ids.*' => 'fotografija u galeriji',
            'category_ids' => 'kategorije',
            'category_ids.*' => 'kategorija',
            'meta_title' => 'SEO naslov',
            'meta_description' => 'SEO opis',
        ];
    }
}
