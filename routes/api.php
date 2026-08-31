<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VillaController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\CleaningLogController;
use App\Http\Controllers\Api\WhatsAppBotController;
use App\Http\Controllers\Api\OwnerPortal\DashboardController as OwnerPortalDashboardController;
use App\Http\Controllers\Api\OwnerPortal\VillaController as OwnerPortalVillaController;
use App\Http\Controllers\Api\OwnerPortal\BookingController as OwnerPortalBookingController;
use App\Http\Controllers\Api\OwnerPortal\GuestController as OwnerPortalGuestController;

// Public auth routes
Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // WhatsApp bot webhook relay (server-to-server; authenticated via X-Relay-Secret
    // header inside the controller, not sanctum — the caller is the Firebase relay function)
    Route::post('whatsapp-bot/handle-event', [WhatsAppBotController::class, 'handleEvent']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateProfile']);
        Route::put('me/password', [AuthController::class, 'changePassword']);

        // Resources
        Route::get('villas/stats', [VillaController::class, 'stats']);
        Route::apiResource('villas', VillaController::class);
        Route::get('villas/{villa}/bookings', [VillaController::class, 'bookings']);
        Route::get('villas/{villa}/calendar', [VillaController::class, 'calendar']);

        Route::post('owners/copy-phones-to-whatsapp', [OwnerController::class, 'copyPhonesToWhatsApp']);
        Route::post('owners/{owner}/enable-login', [OwnerController::class, 'enableLogin']);
        Route::apiResource('owners', OwnerController::class);
        Route::apiResource('guests', GuestController::class);

        Route::get('bookings/progress/{token}', [BookingController::class, 'progress']);
        Route::apiResource('bookings', BookingController::class);
        Route::post('bookings/check-availability', [BookingController::class, 'checkAvailability']);
        Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirmBooking']);
        Route::post('bookings/{booking}/confirm-arrival', [BookingController::class, 'confirmArrival']);
        Route::post('bookings/{booking}/confirm-departure', [BookingController::class, 'confirmDeparture']);
        Route::post('bookings/{booking}/send-checkout-reminder', [BookingController::class, 'sendCheckoutReminder']);
        Route::post('bookings/{booking}/payments', [PaymentController::class, 'store']);
        Route::get('bookings/{booking}/payments', [PaymentController::class, 'index']);
        Route::delete('bookings/{booking}/payments/{payment}', [PaymentController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Reports
        Route::get('reports/occupancy', [ReportController::class, 'occupancy']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/villa-performance', [ReportController::class, 'villaPerformance']);
        Route::get('reports/user-performance', [ReportController::class, 'userPerformance']);
        Route::get('reports/bookings-summary', [ReportController::class, 'bookingsSummary']);
        Route::get('reports/payment-methods', [ReportController::class, 'paymentMethods']);
        Route::get('reports/owner-bookings', [ReportController::class, 'ownerBookings']);
        Route::get('reports/user-bookings', [ReportController::class, 'userBookings']);
        Route::get('reports/user-bookings/export', [ReportController::class, 'userBookingsExport']);

        // Users (admin only)
        Route::apiResource('users', UserController::class);

        // Notifications
        Route::get('notifications/summary', [NotificationController::class, 'summary']);
        Route::post('notifications/whatsapp', [NotificationController::class, 'sendWhatsApp']);
        Route::get('notifications/whatsapp-phone', [NotificationController::class, 'whatsappPhoneInfo']);
        Route::post('notifications/whatsapp-test', [NotificationController::class, 'whatsappTest']);

        // Activity logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // App settings (admin only)
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::post('settings/stamp', [SettingController::class, 'uploadStamp']);
        Route::post('settings/management-logo', [SettingController::class, 'uploadManagementLogo']);

        // Maintenance
        Route::get('maintenance/turnover', [MaintenanceController::class, 'turnover']);

        // Cleaning logs
        Route::get('maintenance/cleaning-logs/recent', [CleaningLogController::class, 'recent']);
        Route::post('maintenance/cleaning-logs', [CleaningLogController::class, 'store']);
        Route::delete('maintenance/cleaning-logs/{cleaningLog}', [CleaningLogController::class, 'destroy']);

        // Import
        Route::post('import/owners', [ImportController::class, 'importOwners']);
    });

    // Owner portal — fully separate route group, scoped to the logged-in
    // owner's own villas/bookings only. See OWNER_PORTAL_PLAN.md.
    Route::prefix('owner')->middleware(['auth:sanctum', 'ensure.owner'])->group(function () {
        Route::get('dashboard', [OwnerPortalDashboardController::class, 'index']);

        Route::get('villas', [OwnerPortalVillaController::class, 'index']);
        Route::get('villas/{villa}', [OwnerPortalVillaController::class, 'show']);

        Route::get('guests', [OwnerPortalGuestController::class, 'index']);
        Route::post('guests', [OwnerPortalGuestController::class, 'store']);

        Route::get('bookings', [OwnerPortalBookingController::class, 'index']);
        Route::post('bookings/check-availability', [OwnerPortalBookingController::class, 'checkAvailability']);
        Route::get('bookings/{booking}', [OwnerPortalBookingController::class, 'show']);
        Route::post('bookings', [OwnerPortalBookingController::class, 'store']);
    });
});
