<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactSetting;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'contact' => ContactSetting::firstOrNew(),
            'settings' => SiteSetting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_display' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'site_name' => ['required', 'string', 'max:255'],
            'site_description' => ['required', 'string', 'max:5000'],
            'default_robots' => ['nullable', 'string', 'max:255'],
        ]);

        $contact = ContactSetting::firstOrCreate([]);
        $before = $contact->toArray();
        $contact->update(collect($data)->only([
            'phone', 'phone_display', 'whatsapp', 'email', 'city', 'region',
            'country_code',
        ])->all());

        foreach (['site_name', 'site_description', 'default_robots'] as $key) {
            SiteSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => ['text' => $data[$key] ?? null],
                    'group' => 'seo',
                    'is_public' => true,
                ],
            );
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'settings.updated',
            'before' => $before,
            'after' => $contact->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'تم تحديث بيانات التواصل وإعدادات SEO.');
    }
}
