<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Gallery;
use App\Models\Media;
use App\Services\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
        return view('admin.media.index', [
            'mediaItems' => Media::query()->latest()->paginate(36),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', [
            'media' => new Media,
            'galleries' => Gallery::query()->orderBy('title')->get(),
        ]);
    }

    public function store(
        Request $request,
        ImageProcessor $processor,
    ): RedirectResponse {
        $request->validate([
            'image' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max((int) ceil(config('media.max_bytes') / 1024)),
            ],
            'alt' => ['required', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'gallery_ids' => ['nullable', 'array'],
            'gallery_ids.*' => ['integer', 'exists:galleries,id'],
        ]);

        $media = $processor->upload($request->file('image'), $request->user()->id);
        $media->update($request->only('alt', 'caption'));
        $media->galleries()->sync($request->input('gallery_ids', []));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'media.uploaded',
            'subject_type' => $media->getMorphClass(),
            'subject_id' => $media->id,
            'after' => $media->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('success', 'تم رفع الصورة وفحصها وتوليد WebP.');
    }

    public function edit(Media $media): View
    {
        return view('admin.media.form', [
            'media' => $media->load('galleries'),
            'galleries' => Gallery::query()->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $data = $request->validate([
            'alt' => ['required', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'gallery_ids' => ['nullable', 'array'],
            'gallery_ids.*' => ['integer', 'exists:galleries,id'],
        ]);

        $before = $media->toArray();
        $media->update($data);
        $media->galleries()->sync($request->input('gallery_ids', []));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'media.updated',
            'subject_type' => $media->getMorphClass(),
            'subject_id' => $media->id,
            'before' => $before,
            'after' => $media->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'تم تحديث بيانات الصورة.');
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $isUsed = DB::table('mediaables')->where('media_id', $media->id)->exists()
            || DB::table('gallery_media')->where('media_id', $media->id)->exists()
            || DB::table('articles')->where('hero_media_id', $media->id)->exists()
            || DB::table('services')->where('hero_media_id', $media->id)->exists()
            || DB::table('areas')->where('hero_media_id', $media->id)->exists();

        if ($isUsed) {
            return back()->withErrors([
                'image' => 'لا يمكن حذف صورة مستخدمة. أزل ارتباطها بالمحتوى أولًا.',
            ]);
        }

        $before = $media->toArray();
        $media->delete();
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'media.archived',
            'subject_type' => $media->getMorphClass(),
            'subject_id' => $media->id,
            'before' => $before,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.media.index')->with(
            'success',
            'تمت أرشفة سجل الصورة، وبقي الملف قابلًا للاستعادة.',
        );
    }
}
