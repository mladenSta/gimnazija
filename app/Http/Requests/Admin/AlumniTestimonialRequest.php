<?php

namespace App\Http\Requests\Admin;

use App\Models\AlumniTestimonial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlumniTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $testimonial = $this->route('alumnus');

        return $testimonial instanceof AlumniTestimonial
            ? ($this->user()?->can('update', $testimonial) ?? false)
            : ($this->user()?->can('create', AlumniTestimonial::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'generation' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'profession' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'max:1000'],
            'photo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ime i prezime',
            'generation' => 'generacija',
            'profession' => 'zanimanje',
            'quote' => 'poruka',
            'photo_media_id' => 'fotografija',
        ];
    }
}
