<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(25);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function read(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 404);
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request, CurrentCompany $current)
    {
        $request->user()->unreadNotifications->each(fn ($n) => $n->markAsRead());

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
