<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hazard;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalHazards = Hazard::count();
        $pendingHazards = Hazard::where('status', 'pending')->count();
        $confirmedHazards = Hazard::where('status', 'confirmed')->count();
        $resolvedHazards = Hazard::where('status', 'resolved')->count();
        $totalUsers = User::count();

        $hazards = Hazard::all();

        $hazardsByType = $hazards->groupBy('type')->map(fn ($items, $type) => [
            'type' => $type,
            'count' => $items->count(),
        ])->values();

        $hazardsByStatus = $hazards->groupBy('status')->map(fn ($items, $status) => [
            'status' => $status,
            'count' => $items->count(),
        ])->values();

        $hazardsOverTime = $hazards->groupBy(fn ($h) => $h->created_at->format('Y-m-d'))
            ->map(fn ($items, $date) => [
                'date' => $date,
                'count' => $items->count(),
            ])
            ->sortBy('date')
            ->take(30)
            ->values();

        return response()->json([
            'totals' => [
                'total_hazards' => $totalHazards,
                'pending' => $pendingHazards,
                'confirmed' => $confirmedHazards,
                'resolved' => $resolvedHazards,
                'total_users' => $totalUsers,
            ],
            'by_type' => $hazardsByType,
            'by_status' => $hazardsByStatus,
            'over_time' => $hazardsOverTime,
        ]);
    }

    public function users()
    {
        return User::withCount('hazards')->latest()->paginate(20);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:user,admin',
        ]);

        // Privileged fields (role) are not mass assignable; assign explicitly
        // so only this authorization-checked path can ever change them.
        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('role', $validated)) {
            $user->role = $validated['role'];
        }
        $user->save();

        return response()->json($user);
    }

    public function suspendUser(User $user)
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot suspend an admin account.'], 422);
        }

        $user->suspended = true;
        $user->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'User suspended successfully.']);
    }
}
