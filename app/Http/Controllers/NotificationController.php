<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\PingUser;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    //shows all the user notifications and reads them
    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()->get();
        foreach($notifications as $notification){
            $notification->markAsRead();
        }
        return $notifications;
    }
    //'tag' a user, sends them a notification
    public function notifyUser(Request $request, User $user, Conversation $conversation) 
    {
        if($request->user()->cannot('notifyUser', [$user, $conversation])) //taken from user policy.
        {
            abort(403);
        }
        $user->notify(new PingUser($request->user(),$conversation));
        return response()->noContent();
    }
}
