<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $thisYear = (int) now()->format('Y');

        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.$thisYear],
            'location' => ['nullable', 'string', 'max:255'],
            'favorite_species' => ['nullable', 'string', 'max:255'],
            'gear' => ['nullable', 'string', 'max:255'], // ili 'array'
            'bio' => ['nullable', 'string', 'max:2000'],
            'settings' => ['nullable', 'array'], // npr. { theme: 'dark', tz: '...' }
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
