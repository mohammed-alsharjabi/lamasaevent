<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string', 'starts_with:/']]);

        $redirect = Redirect::query()
            ->where('from_path', $request->string('path'))
            ->where('is_active', true)
            ->firstOrFail();

        $redirect->increment('hit_count');
        $redirect->forceFill(['last_used_at' => now()])->saveQuietly();

        return response()->json($redirect);
    }
}
