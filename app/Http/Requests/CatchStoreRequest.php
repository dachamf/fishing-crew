<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatchStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // pokrij policy kasnije

    public function rules(): array
    {
        return [
            'group_id' => ['required','exists:groups,id'],
            'event_id' => ['nullable','exists:events,id'],
            'species'  => ['nullable','string','max:100'],
            'count'    => ['required','integer','min:1'],
            'total_weight_kg'   => ['nullable','numeric','min:0'],
            'biggest_single_kg' => ['nullable','numeric','min:0'],
            'note'     => ['nullable','string','max:2000'],
            'caught_at'=> ['nullable','date'],
            'season_year' => ['nullable','integer','min:1900','max:3000'],
        ];
    }

    public function withValidator($v)
    {
        $v->after(function($v){
            $w = $this->input('total_weight_kg');
            $b = $this->input('biggest_single_kg');
            if ($w !== null && $b !== null && $b > $w) {
                $v->errors()->add('biggest_single_kg','Ne može biti veća od total_weight_kg.');
            }
        });
    }
}
