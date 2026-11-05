<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'      => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $organizer = app('currentOrganizer');
        $coupon = $this->route('coupon');

        return [
            'code'        => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')
                    ->where('organizer_id', $organizer->id)
                    ->ignore($coupon?->id),
            ],
            'type'        => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value'       => ['required', 'numeric', 'min:0.01', $this->input('type') === Coupon::TYPE_PERCENT ? 'max:100' : 'max:99999999'],
            'event_id'    => [
                'nullable',
                Rule::exists('events', 'id')->where('organizer_id', $organizer->id)->whereNull('deleted_at'),
            ],
            'max_uses'    => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'min_tickets' => ['required', 'integer', 'min:1', 'max:50'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after:starts_at'],
            'is_active'   => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code'        => 'código',
            'type'        => 'tipo de descuento',
            'value'       => 'valor',
            'event_id'    => 'evento',
            'max_uses'    => 'usos máximos',
            'min_tickets' => 'mínimo de entradas',
            'starts_at'   => 'inicio',
            'ends_at'     => 'vencimiento',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'  => 'El código solo puede tener letras, números, guiones y guiones bajos (sin espacios).',
            'code.unique' => 'Ya tenés un cupón con ese código.',
            'value.max'   => 'El porcentaje no puede superar 100.',
        ];
    }
}
