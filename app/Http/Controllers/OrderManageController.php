<?php

namespace App\Http\Controllers;

use App\Mail\OrderAccessMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Services\TicketTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Gestión de las entradas de una compra: datos de los asistentes y transferencias.
 *
 * Tener el enlace de la compra alcanza para ver las entradas, pero para modificarlas
 * hace falta acceso de gestión:
 *  - el navegador donde se hizo la compra (queda habilitado al comprar),
 *  - el usuario logueado que hizo la compra, o
 *  - un enlace temporal enviado al email del comprador.
 */
class OrderManageController extends Controller
{
    private const SESSION_KEY = 'orders_access';

    public function __construct(
        private TicketTransferService $transfers,
        private TicketService $tickets,
    ) {
    }

    /** Habilita la gestión de la orden en la sesión actual */
    public static function grant(Order $order): void
    {
        $ids = session(self::SESSION_KEY, []);

        if (! in_array($order->id, $ids, true)) {
            $ids[] = $order->id;
            session([self::SESSION_KEY => array_slice($ids, -30)]);
        }
    }

    public static function canManage(Order $order): bool
    {
        if (auth()->check() && $order->user_id && $order->user_id === auth()->id()) {
            return true;
        }

        return in_array($order->id, session(self::SESSION_KEY, []), true);
    }

    /* ---------------------------------------------------------------- */

    /** Pantalla de asistentes y transferencias */
    public function edit(Order $order): View|RedirectResponse
    {
        if (! $order->isPaid()) {
            return redirect()->route('checkout.show', $order);
        }

        $order->load(['event.venue']);
        $tickets = $this->tickets->printable($order, includeTransferred: true);

        return view('public.attendees', [
            'order'       => $order,
            'tickets'     => $tickets,
            'hasAccess'   => self::canManage($order),
            'transfers'   => $this->transfers,
            'maskedEmail' => $this->maskEmail($order->buyer_email),
        ]);
    }

    /** Envía al email del comprador un enlace temporal de gestión */
    public function requestAccess(Order $order): RedirectResponse
    {
        abort_unless($order->isPaid(), 404);

        $url = URL::temporarySignedRoute('checkout.access', now()->addHour(), ['order' => $order->uuid]);

        Mail::to($order->buyer_email, $order->buyer_name)->queue(new OrderAccessMail($order, $url));

        return back()->with('success', 'Te enviamos un enlace a ' . $this->maskEmail($order->buyer_email) . '. Vence en 1 hora.');
    }

    /** Llegada desde el enlace del mail (ruta firmada) */
    public function access(Order $order): RedirectResponse
    {
        self::grant($order);

        return redirect()->route('checkout.attendees', $order)->with('success', 'Listo, ya podés gestionar tus entradas.');
    }

    /** Carga o corrige los datos del asistente */
    public function assign(Request $request, Order $order, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicket($order, $ticket);

        $data = $request->validateWithBag('ticket' . $ticket->id, [
            'attendee_name'  => ['required', 'string', 'max:120'],
            'attendee_dni'   => [$order->event->is_nominative ? 'required' : 'nullable', 'digits_between:7,9'],
            'attendee_email' => ['nullable', 'email', 'max:150'],
        ], [], [
            'attendee_name'  => 'nombre y apellido',
            'attendee_dni'   => 'DNI',
            'attendee_email' => 'email',
        ]);

        $result = $this->transfers->assign($ticket, $data['attendee_name'], $data['attendee_dni'] ?? null, $data['attendee_email'] ?? null);

        $msg = match ($result) {
            'assigned'    => "Entrada {$ticket->code} asignada a {$data['attendee_name']}.",
            'transferred' => "Cambiaste el titular de la entrada {$ticket->code}: se generó un QR nuevo y el anterior ya no sirve."
                . (filled($data['attendee_email'] ?? null) && strcasecmp($data['attendee_email'], $order->buyer_email) !== 0
                    ? " Le enviamos la entrada a {$data['attendee_email']}."
                    : ''),
            default       => "Datos de la entrada {$ticket->code} actualizados.",
        };

        return back()->with('success', $msg);
    }

    /** Transfiere la entrada a otra persona y se la envía por mail */
    public function transfer(Request $request, Order $order, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicket($order, $ticket);

        $data = $request->validateWithBag('transfer' . $ticket->id, [
            'to_name'  => ['required', 'string', 'max:120'],
            'to_dni'   => [$order->event->is_nominative ? 'required' : 'nullable', 'digits_between:7,9'],
            'to_email' => ['required', 'email', 'max:150', 'confirmed'],
        ], [
            'to_email.confirmed' => 'Los emails no coinciden.',
        ], [
            'to_name'  => 'nombre y apellido',
            'to_dni'   => 'DNI',
            'to_email' => 'email',
        ]);

        $this->transfers->transfer($ticket, $data['to_name'], $data['to_dni'] ?? null, $data['to_email']);

        return back()->with('success', "Transferiste la entrada {$ticket->code} a {$data['to_name']}. Le enviamos la entrada a {$data['to_email']} y tu QR anterior quedó anulado.");
    }

    /** Compras del usuario logueado */
    public function mine(Request $request): View
    {
        $orders = Order::with(['event.venue'])
            ->withCount('tickets')
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PENDING, Order::STATUS_REFUNDED])
            ->latest()
            ->paginate(15);

        return view('public.my-orders', compact('orders'));
    }

    /* ---------------------------------------------------------------- */

    private function authorizeTicket(Order $order, Ticket $ticket): void
    {
        abort_unless($ticket->order_id === $order->id, 404);
        abort_unless($order->isPaid(), 403);
        abort_unless(self::canManage($order), 403, 'Pedí el enlace de gestión desde la página de tu compra.');

        $ticket->loadMissing(['event', 'order']);
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($user, 0, 2) . str_repeat('*', max(1, mb_strlen($user) - 2)) . '@' . $domain;
    }
}
