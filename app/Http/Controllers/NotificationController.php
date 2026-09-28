<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()->get();
        foreach($notifications as $notification){
            $notification->markAsRead();
        }
        return $notifications;
    }
    
}
