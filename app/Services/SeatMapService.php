<?php

namespace App\Services;

use App\Models\EventSeat;
use App\Models\Seat;
use App\Models\Venue;
use App\Models\VenueSection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica del mapa de butacas de un lugar: generación de filas,
 * posiciones de los sectores y recálculo de capacidades.
 */
class SeatMapService
{
    public const NUMBERING_ASC      = 'asc';       // 1, 2, 3 ... de izquierda a derecha
    public const NUMBERING_DESC     = 'desc';      // ... 3, 2, 1
    public const NUMBERING_ODD_EVEN = 'odd_even';  // ... 5, 3, 1 | 2, 4, 6 ... (impares a la izquierda, pares a la derecha)

    public const PALETTE = ['#6d28d9', '#0ea5e9', '#16a34a', '#f59e0b', '#e11d48', '#0d9488', '#7c3aed', '#ea580c'];

    /**
     * Genera (reemplazando) todas las butacas de un sector numerado.
     *
     * $o: rows, seats_per_row, row_label_type (letters|numbers), row_start,
     *     numbering, start_number, aisles ("10,20": pasillo después de esas columnas)
     */
    public function generate(VenueSection $section, array $o): int
    {
        return DB::transaction(function () use ($section, $o) {
            $section->seats()->delete();

            $labels = $this->rowLabels((int) $o['rows'], $o['row_label_type'], (string) $o['row_start']);
            $aisles = $this->parseAisles($o['aisles'] ?? null);
            $now = now();
            $rows = [];

            foreach ($labels as $r => $label) {
                $numbers = $this->numbersForRow((int) $o['seats_per_row'], $o['numbering'], (int) ($o['start_number'] ?? 1));

                foreach ($numbers as $col => $number) {
                    // Cantidad de pasillos que quedan a la izquierda de esta columna (1-based)
                    $gaps = count(array_filter($aisles, fn (int $a) => $col + 1 > $a));

                    $rows[] = [
                        'venue_section_id' => $section->id,
                        'row_label'        => $label,
                        'number'           => (string) $number,
                        'label'            => null,
                        'x'                => $col + $gaps,
                        'y'                => $r,
                        'kind'             => Seat::KIND_STANDARD,
                        'is_active'        => true,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Seat::insert($chunk);
            }

            $this->refreshSection($section);

            return count($rows);
        });
    }

    /** Etiquetas de fila: A, B, ... Z, AA, AB ... o 1, 2, 3 ... */
    public function rowLabels(int $count, string $type, string $start): array
    {
        if ($type === 'numbers') {
            $first = max(1, (int) $start);

            return array_map('strval', range($first, $first + $count - 1));
        }

        $index = $this->lettersToIndex(strtoupper(trim($start)) ?: 'A');

        return array_map(fn ($i) => $this->indexToLetters($index + $i), range(0, $count - 1));
    }

    /** Números de butaca de una fila, en orden de izquierda a derecha */
    public function numbersForRow(int $count, string $mode, int $start = 1): array
    {
        if ($mode === self::NUMBERING_ODD_EVEN) {
            $odds = range(1, 2 * (int) ceil($count / 2) - 1, 2);
            $evens = $count > 1 ? range(2, 2 * (int) floor($count / 2), 2) : [];

            return array_merge(array_reverse($odds), $evens);
        }

        $numbers = range($start, $start + $count - 1);

        return $mode === self::NUMBERING_DESC ? array_reverse($numbers) : $numbers;
    }

    /** Recalcula la capacidad del sector y el indicador de mapa del lugar */
    public function refreshSection(VenueSection $section): void
    {
        if ($section->isSeated()) {
            $section->update(['capacity' => $section->seats()->where('is_active', true)->count()]);
        }

        $this->refreshVenue($section->venue);
    }

    public function refreshVenue(Venue $venue): void
    {
        $hasMap = $venue->sections()
            ->where('type', VenueSection::TYPE_SEATED)
            ->whereHas('seats', fn ($q) => $q->where('is_active', true))
            ->exists();

        $venue->update(['has_seat_map' => $hasMap]);
    }

    /** Un sector queda bloqueado si alguna de sus butacas ya se usa en un evento */
    public function isLocked(VenueSection $section): bool
    {
        return EventSeat::whereIn('seat_id', $section->seats()->select('id'))->exists();
    }

    /** Tamaño del sector en unidades del mapa [ancho, alto] */
    public function sectionSize(VenueSection $section): array
    {
        if ($section->isSeated()) {
            $maxX = (int) $section->seats()->max('x');
            $maxY = (int) $section->seats()->max('y');

            // +1 de alto por el título del sector
            return [$maxX + 1, $maxY + 2];
        }

        return [(int) ($section->layout['w'] ?? 10), (int) ($section->layout['h'] ?? 4)];
    }

    /** Posición sugerida para un sector nuevo: debajo del último */
    public function nextPosition(Venue $venue): array
    {
        $bottom = 0;

        foreach ($venue->sections as $s) {
            [, $h] = $this->sectionSize($s);
            $bottom = max($bottom, (int) ($s->layout['y'] ?? 0) + $h);
        }

        return ['x' => 0, 'y' => $venue->sections->isEmpty() ? 0 : $bottom + 2];
    }

    public function nextColor(Venue $venue): string
    {
        return self::PALETTE[$venue->sections()->count() % count(self::PALETTE)];
    }

    /** Datos compactos para dibujar el mapa con public/js/seatmap.js */
    public function mapData(Venue $venue, bool $withLock = false): array
    {
        $venue->loadMissing(['sections.seats']);

        return [
            'sections' => $venue->sections->map(function (VenueSection $s) use ($withLock) {
                return [
                    'id'       => $s->id,
                    'name'     => $s->name,
                    'type'     => $s->type,
                    'color'    => $s->color ?: self::PALETTE[0],
                    'capacity' => $s->capacity,
                    'layout'   => [
                        'x' => (int) ($s->layout['x'] ?? 0),
                        'y' => (int) ($s->layout['y'] ?? 0),
                        'w' => (int) ($s->layout['w'] ?? 10),
                        'h' => (int) ($s->layout['h'] ?? 4),
                    ],
                    'locked'   => $withLock ? $this->isLocked($s) : false,
                    'seats'    => $s->seats->map(fn (Seat $seat) => [
                        'i' => $seat->id,
                        'r' => $seat->row_label,
                        'n' => $seat->number,
                        'x' => (int) $seat->x,
                        'y' => (int) $seat->y,
                        'k' => $seat->kind,
                        'a' => $seat->is_active,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    /* ---------------------------------------------------------------- */

    private function parseAisles(?string $aisles): array
    {
        if (! $aisles) {
            return [];
        }

        $list = array_filter(array_map('intval', explode(',', $aisles)), fn ($n) => $n > 0);
        sort($list);

        return array_values(array_unique($list));
    }

    private function lettersToIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return max(0, $index - 1);
    }

    private function indexToLetters(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
