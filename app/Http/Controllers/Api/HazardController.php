<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hazard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HazardController extends Controller
{
    public function index()
    {
        return Hazard::with('user')->latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255', Rule::in(Hazard::TYPES)],
            'description' => ['required', 'string', 'max:2000'],
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->extension();
            $file->storeAs('hazards', $filename, 'private');

            $validated['image_path'] = $filename;
        }

        $hazard = Hazard::create($validated);

        return response()->json($hazard->load('user'), 201);
    }

    public function show(Hazard $hazard)
    {
        return $hazard->load('user', 'messages.user', 'messages.admin');
    }

    public function update(Request $request, Hazard $hazard)
    {
        if (!$request->user()->isAdmin() && $request->user()->id !== $hazard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:255', Rule::in(Hazard::TYPES)],
            'description' => ['sometimes', 'string', 'max:2000'],
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'status' => ['sometimes', Rule::in(Hazard::STATUSES)],
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if (isset($validated['status']) && !$request->user()->isAdmin()) {
            unset($validated['status']);
        }

        if ($request->hasFile('image')) {
            if ($hazard->image_path) {
                Storage::disk('private')->delete('hazards/' . $hazard->image_path);
            }

            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->extension();
            $file->storeAs('hazards', $filename, 'private');

            $validated['image_path'] = $filename;
        }

        $hazard->update($validated);

        return response()->json($hazard->load('user'));
    }

    public function destroy(Request $request, Hazard $hazard)
    {
        if (!$request->user()->isAdmin() && $request->user()->id !== $hazard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($hazard->image_path) {
            Storage::disk('private')->delete('hazards/' . $hazard->image_path);
        }

        $hazard->delete();

        return response()->noContent();
    }
}
