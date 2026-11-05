<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'buyer_email'              => strtolower(trim((string) $this->input('buyer_email'))),
            'buyer_email_confirmation' => strtolower(trim((string) $this->input('buyer_email_confirmation'))),
            'buyer_dni'                => preg_replace('/\D/', '', (string) $this->input('buyer_dni')),
        ]);
    }

    public function rules(): array
    {
        return [
            'quantities'   => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:50'],
            'seats'        => ['nullable', 'array', 'max:50'],
            'seats.*'      => ['integer'],
            'coupon'       => ['nullable', 'string', 'max:40'],

            'buyer_name'   => ['required', 'string', 'max:120'],
            'buyer_email'  => ['required', 'email', 'max:150', 'confirmed'],
            'buyer_dni'    => ['required', 'digits_between:7,9'],
            'buyer_phone'  => ['nullable', 'string', 'max:30'],
            'terms'        => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'buyer_name'  => 'nombre y apellido',
            'buyer_email' => 'email',
            'buyer_dni'   => 'DNI',
            'buyer_phone' => 'teléfono',
            'coupon'      => 'cupón',
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_email.confirmed' => 'Los emails no coinciden. Revisalo: ahí te llegan las entradas.',
            'terms.accepted'        => 'Tenés que aceptar los términos y condiciones de compra.',
        ];
    }

    public function buyer(): array
    {
        return $this->only(['buyer_name', 'buyer_email', 'buyer_dni', 'buyer_phone']);
    }
}
