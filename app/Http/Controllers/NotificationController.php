<?php

namespace App\Http\Controllers;

use App\Models\ShipmentNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications, always scoped to the signed-in user [S10].
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()
                ->with('shipment')
                ->latest()
                ->paginate(30),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function read(Request $request, ShipmentNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markRead();

        return back();
    }
}
