<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Hazard;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Hazard chat is private: only the hazard's owner or an admin may read
     * or post messages. This mirrors the broadcast channel authorization in
     * routes/channels.php so REST and realtime surfaces agree.
     */
    private function canAccessHazard(Request $request, Hazard $hazard): bool
    {
        $user = $request->user();

        return $user->isAdmin() || $user->id === $hazard->user_id;
    }

    public function index(Request $request, Hazard $hazard)
    {
        if (!$this->canAccessHazard($request, $hazard)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $hazard->messages()->with('user', 'admin')->latest()->get();
    }

    public function store(Request $request, Hazard $hazard)
    {
        if (!$this->canAccessHazard($request, $hazard)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        $message = Message::create([
            'hazard_id' => $hazard->id,
            // Attribute the message to its actual sender. (Previously every
            // non-admin message was wrongly attributed to the hazard owner.)
            'user_id' => $user->id,
            'admin_id' => $user->isAdmin() ? $user->id : null,
            'message' => $validated['message'],
            'is_from_admin' => $user->isAdmin(),
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message->load('user', 'admin'), 201);
    }
}
