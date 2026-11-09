# Verifica que todos los archivos de GestiónEventos (pasos 1 a 12) estén en su lugar.
# Uso: desde la carpeta del proyecto ->  powershell -ExecutionPolicy Bypass -File .\verificar.ps1

$archivos = @(
    # Configuración y rutas
    'config/geventos.php', 'bootstrap/app.php', 'routes/web.php', 'routes/api.php', 'routes/console.php',

    # Modelos
    'app/Models/User.php', 'app/Models/Organizer.php', 'app/Models/Venue.php', 'app/Models/VenueSection.php',
    'app/Models/Seat.php', 'app/Models/Event.php', 'app/Models/TicketType.php', 'app/Models/TicketLot.php',
    'app/Models/Coupon.php', 'app/Models/Order.php', 'app/Models/EventSeat.php', 'app/Models/OrderItem.php',
    'app/Models/Ticket.php', 'app/Models/TicketTransfer.php', 'app/Models/Checkin.php', 'app/Models/Payment.php',
    'app/Models/WebhookEvent.php',

    # Controladores
    'app/Http/Controllers/Auth/LoginController.php', 'app/Http/Controllers/Auth/RegisterController.php',
    'app/Http/Controllers/Auth/OrganizerRegisterController.php', 'app/Http/Controllers/Auth/PasswordResetController.php',
    'app/Http/Controllers/Organizer/DashboardController.php', 'app/Http/Controllers/Organizer/EventController.php',
    'app/Http/Controllers/Organizer/VenueController.php', 'app/Http/Controllers/Organizer/SeatMapController.php',
    'app/Http/Controllers/Organizer/TicketTypeController.php', 'app/Http/Controllers/Organizer/TicketLotController.php',
    'app/Http/Controllers/Organizer/CouponController.php', 'app/Http/Controllers/Organizer/EventSeatController.php',
    'app/Http/Controllers/Organizer/MercadoPagoController.php', 'app/Http/Controllers/Organizer/CheckinController.php',
    'app/Http/Controllers/Organizer/OrderController.php', 'app/Http/Controllers/Organizer/ReportController.php',
    'app/Http/Controllers/Organizer/TeamController.php', 'app/Http/Controllers/Admin/AdminReportController.php',
    'app/Http/Controllers/Admin/OrganizerController.php', 'app/Http/Controllers/Api/ScannerController.php',
    'app/Http/Controllers/PublicEventController.php', 'app/Http/Controllers/CheckoutController.php',
    'app/Http/Controllers/WebhookController.php', 'app/Http/Controllers/OrderManageController.php',
    'app/Http/Controllers/TicketPublicController.php',

    # Middleware y requests
    'app/Http/Middleware/EnsureOrganizerAccess.php', 'app/Http/Middleware/EnsurePlatformAdmin.php',
    'app/Http/Requests/VenueRequest.php', 'app/Http/Requests/EventRequest.php', 'app/Http/Requests/VenueSectionRequest.php',
    'app/Http/Requests/SeatGenerationRequest.php', 'app/Http/Requests/TicketTypeRequest.php',
    'app/Http/Requests/TicketLotRequest.php', 'app/Http/Requests/CouponRequest.php', 'app/Http/Requests/CheckoutRequest.php',

    # Servicios, comandos y mails
    'app/Services/SeatMapService.php', 'app/Services/EventSeatService.php', 'app/Services/MercadoPagoService.php',
    'app/Services/CheckoutService.php', 'app/Services/TicketQrService.php', 'app/Services/TicketService.php',
    'app/Services/TicketTransferService.php', 'app/Services/CheckinService.php', 'app/Services/ReportService.php',
    'app/Jobs/RefundEventOrders.php', 'app/Mail/TeamInviteMail.php',
    'app/Console/Commands/ReleaseExpiredOrders.php', 'app/Console/Commands/ReconcilePayments.php',
    'app/Mail/TicketsMail.php', 'app/Mail/OrderRefundedMail.php', 'app/Mail/OrderAccessMail.php', 'app/Mail/TicketTransferredMail.php',

    # Vistas
    'resources/views/layouts/app.blade.php', 'resources/views/layouts/panel.blade.php', 'resources/views/home.blade.php',
    'resources/views/escaner.blade.php',
    'resources/views/auth/login.blade.php', 'resources/views/auth/register.blade.php', 'resources/views/auth/register-organizer.blade.php',
    'resources/views/auth/forgot-password.blade.php', 'resources/views/auth/reset-password.blade.php',
    'resources/views/organizer/dashboard.blade.php', 'resources/views/organizer/venues-index.blade.php',
    'resources/views/organizer/venues-form.blade.php', 'resources/views/organizer/events-index.blade.php',
    'resources/views/organizer/events-form.blade.php', 'resources/views/organizer/events-show.blade.php',
    'resources/views/organizer/seatmap.blade.php', 'resources/views/organizer/coupons-index.blade.php',
    'resources/views/organizer/coupons-form.blade.php', 'resources/views/organizer/event-seats.blade.php',
    'resources/views/organizer/mercadopago.blade.php', 'resources/views/organizer/checkins.blade.php',
    'resources/views/organizer/orders-index.blade.php', 'resources/views/organizer/orders-show.blade.php',
    'resources/views/organizer/reports-index.blade.php', 'resources/views/organizer/reports-show.blade.php',
    'resources/views/organizer/team.blade.php',
    'resources/views/admin/organizers-index.blade.php', 'resources/views/admin/reports.blade.php',
    'resources/views/emails/team-invite-email.blade.php', 'deploy.sh',
    'resources/views/public/event.blade.php', 'resources/views/public/order.blade.php', 'resources/views/public/attendees.blade.php',
    'resources/views/public/ticket.blade.php', 'resources/views/public/my-orders.blade.php',
    'resources/views/pdf/tickets-pdf.blade.php',
    'resources/views/emails/tickets-email.blade.php', 'resources/views/emails/order-refunded-email.blade.php',
    'resources/views/emails/order-access-email.blade.php', 'resources/views/emails/ticket-transferred-email.blade.php',

    # Archivos públicos
    'public/js/seatmap.js', 'public/js/escaner.js', 'public/sw-escaner.js', 'public/escaner.webmanifest',
    'public/img/escaner-192.png', 'public/img/escaner-512.png'
)

$faltan = @()
foreach ($a in $archivos) {
    if (-not (Test-Path $a)) { $faltan += $a }
}

Write-Host ""
if ($faltan.Count -eq 0) {
    Write-Host "OK: estan los $($archivos.Count) archivos." -ForegroundColor Green
} else {
    Write-Host "FALTAN $($faltan.Count) archivo(s):" -ForegroundColor Red
    $faltan | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
}

# Copias con nombre cambiado por el navegador: "Archivo (1).php"
$duplicados = Get-ChildItem -Path app, resources, routes, config, public -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -match ' \(\d+\)' }
if ($duplicados) {
    Write-Host ""
    Write-Host "Archivos con nombre duplicado (renombrar o borrar):" -ForegroundColor Red
    $duplicados | ForEach-Object { Write-Host "  - $($_.FullName)" -ForegroundColor Yellow }
}

# Clases de la carpeta Organizer en otra carpeta por error
$sueltos = Get-ChildItem -Path app/Http/Controllers -File -Filter '*.php' |
    Where-Object { $_.Name -in @('DashboardController.php','EventController.php','VenueController.php','SeatMapController.php',
        'TicketTypeController.php','TicketLotController.php','CouponController.php','EventSeatController.php',
        'MercadoPagoController.php','CheckinController.php','OrderController.php','ReportController.php','TeamController.php') }
if ($sueltos) {
    Write-Host ""
    Write-Host "Controladores de la productora fuera de su carpeta (van en app/Http/Controllers/Organizer):" -ForegroundColor Red
    $sueltos | ForEach-Object { Write-Host "  - $($_.FullName)" -ForegroundColor Yellow }
}
Write-Host ""
