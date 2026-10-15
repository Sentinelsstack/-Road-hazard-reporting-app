<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hazard;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function show(string $path)
    {
        $fullPath = 'hazards/' . $path;

        if (!Storage::disk('private')->exists($fullPath)) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $hazard = Hazard::where('image_path', $path)->first();
        if (!$hazard) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $user = request()->user();
        if (!$user->isAdmin() && $user->id !== $hazard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = Storage::disk('private')->get($fullPath);
        $mimeType = Storage::disk('private')->mimeType($fullPath);

        return response($file, 200)->header('Content-Type', $mimeType);
    }
}
