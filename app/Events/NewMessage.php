<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageData;

    public function __construct($messageData)
    {
        $this->messageData = $messageData;
    }

    public function broadcastOn()
    {
        return new Channel('wiseelik_2');
    }

    public function broadcastAs()
    {
        return 'MessageEvent';
    }

    public function broadcastWith()
    {
        return [
            'sender_id' => $this->messageData['sender_id'],
            'message' => $this->messageData['message'],
            'timestamp' => $this->messageData['timestamp']
        ];
    }
}
