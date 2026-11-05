<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'address'   => ['nullable', 'string', 'max:255'],
            'city'      => ['nullable', 'string', 'max:100'],
            'province'  => ['nullable', Rule::in(config('geventos.provincias'))],
            'capacity'  => ['nullable', 'integer', 'min:1', 'max:200000'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'      => 'nombre',
            'address'   => 'dirección',
            'city'      => 'ciudad',
            'province'  => 'provincia',
            'capacity'  => 'capacidad',
            'latitude'  => 'latitud',
            'longitude' => 'longitud',
        ];
    }
}
