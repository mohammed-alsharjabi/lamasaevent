<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedirectLookupRequest;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;

class RedirectLookupController extends Controller
{
    public function __invoke(RedirectLookupRequest $request): JsonResponse
    {
        $redirect = Redirect::query()
            ->where('from_path', $request->string('path'))
            ->where('is_active', true)
            ->firstOrFail();

        $redirect->increment('hit_count');
        $redirect->forceFill(['last_used_at' => now()])->saveQuietly();

        return response()->json($redirect);
    }
}
