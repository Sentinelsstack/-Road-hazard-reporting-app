<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('user', 'admin');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hazard.' . $this->message->hazard_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'is_from_admin' => $this->message->is_from_admin,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
            'admin' => $this->message->admin ? [
                'id' => $this->message->admin->id,
                'name' => $this->message->admin->name,
            ] : null,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
