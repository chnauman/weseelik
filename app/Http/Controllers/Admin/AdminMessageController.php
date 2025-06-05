<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Message;
use App\Events\NewMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMessageController extends Controller
{
    public function index()
    {
        $users = User::all();
        $messages = Message::with(['sender', 'receiver'])->get();
        return view('admin.messages.index', compact('users', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string'
        ]);

        // Save message to database
        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->user_id,
            'message' => $request->message,
            'status' => 'unread'
        ]);

        // Prepare data for broadcasting
        $messageData = [
            'sender_id' => Auth::id(),
            'message' => $request->message,
            'timestamp' => now()->toDateTimeString()
        ];

        // Broadcast the message
        event(new NewMessage($messageData));

        return redirect()->back()->with('success', 'Message sent successfully');
    }
}