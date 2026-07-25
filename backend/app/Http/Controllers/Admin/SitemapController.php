<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SitemapEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SitemapController extends Controller
{
    public function index(): View
    {
        return view('admin.sitemap', [
            'entries' => SitemapEntry::query()
                ->with('routeRecord')
                ->orderBy('position')
                ->paginate(40),
        ]);
    }

    public function update(Request $request, SitemapEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'lastmod' => ['nullable', 'date'],
            'changefreq' => [
                'nullable',
                Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never']),
            ],
            'priority' => ['nullable', 'numeric', 'between:0,1'],
            'is_included' => ['nullable', 'boolean'],
        ]);

        $routeRecord = $entry->routeRecord()->first();
        if (
            $routeRecord?->getAttribute('is_legacy')
            && ! $request->boolean('is_included')
        ) {
            throw ValidationException::withMessages([
                'is_included' => 'لا يمكن حذف رابط موروث من sitemap.',
            ]);
        }

        $entry->update([
            ...$data,
            'is_included' => $request->boolean('is_included'),
        ]);

        return back()->with('success', 'تم تحديث إعدادات رابط sitemap.');
    }
}
