<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RedirectController extends Controller
{
    public function index(): View
    {
        return view('admin.redirects', [
            'redirects' => Redirect::query()->latest()->paginate(40),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'starts_with:/', 'max:2048', 'unique:redirects'],
            'to_path' => ['required', 'string', 'starts_with:/', 'max:2048', 'different:from_path'],
            'status_code' => ['required', Rule::in([301])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        Redirect::create([
            ...$data,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم إنشاء Redirect 301.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->update(['is_active' => false]);

        return back()->with('success', 'تم تعطيل التحويل دون حذفه من السجل.');
    }
}
