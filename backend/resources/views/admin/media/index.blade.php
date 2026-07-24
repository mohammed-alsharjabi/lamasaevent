@extends('layouts.admin')

@section('title', 'مكتبة الصور')

@section('content')
<div class="topbar">
    <div>
        <h1>مكتبة الصور</h1>
        <p class="muted">كل صورة مرفوعة مفحوصة وتملك نسخة WebP مشتقة.</p>
    </div>
    <a class="btn" href="{{ route('admin.media.create') }}">رفع صورة</a>
</div>
<div class="grid">
    @forelse ($mediaItems as $media)
        <article class="card">
            <img
                src="{{ Storage::disk($media->disk)->url($media->webp_path ?: $media->path) }}"
                alt="{{ $media->alt }}"
                style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px"
                loading="lazy"
            >
            <p>{{ $media->alt ?: 'بدون وصف بديل' }}</p>
            <small class="muted">{{ $media->width }}×{{ $media->height }} · {{ number_format($media->size / 1024) }}KB</small>
            <div style="margin-top:12px"><a class="btn btn-secondary" href="{{ route('admin.media.edit', $media) }}">تحرير</a></div>
        </article>
    @empty
        <div class="card">لا توجد صور بعد.</div>
    @endforelse
</div>
<div class="pagination">{{ $mediaItems->links() }}</div>
@endsection
