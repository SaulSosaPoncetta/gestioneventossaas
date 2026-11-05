<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\VenueSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_visible' => $this->boolean('is_visible')]);
    }

    private function event(): Event
    {
        return $this->route('event');
    }

    private function creating(): bool
    {
        return $this->isMethod('post');
    }

    public function rules(): array
    {
        $event = $this->event();

        $rules = [
            'name'             => ['required', 'string', 'max:80'],
            'description'      => ['nullable', 'string', 'max:255'],
            'venue_section_id' => [
                'nullable',
                Rule::exists('venue_sections', 'id')->where('venue_id', $event->venue_id ?? 0),
            ],
            'max_per_order'    => ['nullable', 'integer', 'min:1', 'max:' . config('geventos.max_entradas_por_orden')],
            'is_visible'       => ['boolean'],
            'sort'             => ['nullable', 'integer', 'min:0', 'max:999'],
        ];

        // Al crear el tipo se carga también su primer lote
        if ($this->creating()) {
            $rules += [
                'lot_name'     => ['required', 'string', 'max:80'],
                'lot_price'    => ['required', 'numeric', 'min:0', 'max:99999999'],
                'lot_quantity' => ['nullable', 'integer', 'min:1', 'max:500000'],
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $event = $this->event();
            $section = $this->filled('venue_section_id') ? VenueSection::find($this->input('venue_section_id')) : null;
            $seated = $section?->isSeated() ?? false;

            if ($seated && ! $event->is_seated) {
                $v->errors()->add('venue_section_id', 'Para usar un sector numerado activá "Butacas numeradas" en el evento.');
            }

            if ($seated) {
                $current = $this->route('type');
                $taken = TicketType::where('event_id', $event->id)
                    ->where('venue_section_id', $section->id)
                    ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                    ->exists();

                if ($taken) {
                    $v->errors()->add('venue_section_id', "El sector «{$section->name}» ya está asignado a otro tipo de entrada.");
                }
            }

            if ($this->creating() && ! $seated && ! $this->filled('lot_quantity')) {
                $v->errors()->add('lot_quantity', 'Indicá el cupo del lote.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name'             => 'nombre',
            'description'      => 'descripción',
            'venue_section_id' => 'sector',
            'max_per_order'    => 'máximo por compra',
            'lot_name'         => 'nombre del lote',
            'lot_price'        => 'precio',
            'lot_quantity'     => 'cupo',
        ];
    }
}
