<?php

namespace App\Http\Requests;

use App\Models\TicketLot;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TicketLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:80'],
            'price'     => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity'  => ['nullable', 'integer', 'min:1', 'max:500000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after:starts_at'],
            'status'    => ['required', Rule::in([TicketLot::STATUS_ACTIVE, TicketLot::STATUS_PAUSED])],
            'sort'      => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /** Tipo de entrada al que pertenece el lote (en alta viene en la ruta, en edición sale del lote) */
    public function ticketType(): TicketType
    {
        return $this->route('type') ?? $this->route('lot')->ticketType;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->ticketType()->loadMissing('section');
            $seated = $type->isSeated();

            if (! $seated && ! $this->filled('quantity')) {
                $v->errors()->add('quantity', 'Indicá el cupo del lote.');
            }

            /** @var TicketLot|null $lot */
            $lot = $this->route('lot');
            if ($lot && $this->filled('quantity') && (int) $this->input('quantity') < $lot->sold + $lot->reserved) {
                $v->errors()->add('quantity', 'El cupo no puede ser menor a lo ya vendido o reservado (' . ($lot->sold + $lot->reserved) . ').');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name'      => 'nombre del lote',
            'price'     => 'precio',
            'quantity'  => 'cupo',
            'starts_at' => 'inicio',
            'ends_at'   => 'fin',
            'status'    => 'estado',
        ];
    }

    /** Datos a guardar: en tipos numerados el cupo lo dan las butacas */
    public function lotData(): array
    {
        $data = $this->validated();

        if ($this->ticketType()->isSeated()) {
            $data['quantity'] = null;
        }

        $data['sort'] = $data['sort'] ?? 0;

        return $data;
    }
}
