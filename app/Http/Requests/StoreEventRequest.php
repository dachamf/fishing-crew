<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Dozvoli, a pravu kontrolu radi Policy ili middleware (ispod)
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'location_name' => ['nullable', 'string', 'max:200'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'start_at' => ['required', 'date'], // ISO ili "YYYY-MM-DD HH:mm"
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
