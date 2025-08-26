<?php
namespace App\Http\Requests;

use App\Models\Species;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatchStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // pokrij policy kasnije

    protected function prepareForValidation(): void
    {
        // Allow clients to send either species_id or a human-readable species string
        $speciesId = $this->input('species_id');
        $speciesStr = $this->input('species');
        if (!$speciesId && $speciesStr) {
            $q = trim(mb_strtolower($speciesStr));
            $found = Species::query()
                ->whereRaw('LOWER(slug) = ?', [$q])
                ->orWhereRaw('LOWER(name_sr) = ?', [$q])
                ->orWhereRaw('LOWER(name_latin) = ?', [$q])
                ->value('id');
            if ($found) {
                $this->merge(['species_id' => $found]);
            }
        }

        $sid = $this->input('session_id');
        if ($sid === null || $sid === '' || $sid === 'null') {
            $sid = $this->input('fishing_session_id');
        }
        if ($sid !== null && $sid !== '' && $sid !== 'null') {
            $this->merge(['session_id' => (int) $sid]);
        } else {
            // ako ništa nije stiglo, obriši ključ da 'nullable' radi kako treba
            $this->request->remove('session_id');
        }

        if ($this->filled('caught_at')) {
            try {
                $dt = \Carbon\Carbon::parse($this->input('caught_at'));
                $this->merge([
                    'caught_at' => $dt->toISOString(),
                    'season_year' => $this->input('season_year') ?? (int) $dt->year,
                ]);
            } catch (\Throwable $e) {}
        }
    }

    public function rules(): array
    {
        return [
            'group_id'    => ['required','integer','exists:groups,id'],
            'session_id'  => ['nullable','integer',
                Rule::exists('fishing_sessions','id')
                ->where('group_id', (int) $this->input('group_id'))
                ->where('user_id', $this->user()?->id),
                ],
            'event_id'    => ['nullable','integer','exists:events,id'],
            'species'     => ['nullable','string','max:100'],
            'species_id'  => ['nullable','integer'],
            'species_name'=> ['nullable','string','max:100'],
            'count'       => ['required','integer','min:1'],
            'total_weight_kg'   => ['nullable','numeric','min:0'],
            'biggest_single_kg' => ['nullable','numeric','min:0'],
            'note'        => ['nullable','string','max:500'],
            'season_year' => ['nullable','integer','min:2000','max:2100'],
            'caught_at'   => ['nullable','date'],
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
