<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use App\Models\RouteRecord;
use App\Services\PublicPageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicPageController extends Controller
{
    public function __invoke(
        Request $request,
        PublicPageRenderer $renderer,
    ): Response|RedirectResponse {
        $path = '/'.ltrim($request->path(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $candidates = $path === '/' ? ['/'] : [$path, $path.'/'];

        $redirect = Redirect::query()
            ->where('is_active', true)
            ->whereIn('from_path', $candidates)
            ->first();

        if ($redirect instanceof Redirect) {
            return redirect()->to(
                (string) $redirect->to_path,
                (int) $redirect->status_code,
            );
        }

        $route = RouteRecord::query()
            ->with('routable')
            ->where('is_published', true)
            ->whereIn('path', $candidates)
            ->first();

        if (! $route instanceof RouteRecord && ! $renderer->isListingPath($path)) {
            abort(404);
        }

        $html = $renderer->render($request, $path, $route?->routable);

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Lamsa-Content-Source', 'database');
    }
}
