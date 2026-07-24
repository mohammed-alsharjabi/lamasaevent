@extends('layouts.admin')

@php($editing = $media->exists)

@section('title', $editing ? 'تحرير صورة' : 'رفع صورة')

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $editing ? 'تحرير صورة' : 'رفع صورة آمنة' }}</h1>
        <p class="muted">JPEG أو PNG أو WebP، بحد أقصى {{ number_format(config('media.max_bytes') / 1024 / 1024) }}MB.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.media.index') }}">رجوع</a>
</div>
<form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.media.update', $media) : route('admin.media.store') }}">
    @csrf
    @if ($editing) @method('put') @endif
    <section class="panel">
        <div class="panel-body">
            @if ($editing)
                <img src="{{ Storage::disk($media->disk)->url($media->webp_path ?: $media->path) }}" alt="{{ $media->alt }}" style="max-width:420px;width:100%;border-radius:10px;margin-bottom:18px">
            @else
                <div class="field">
                    <label for="image">ملف الصورة</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                </div>
            @endif
            <div class="field">
                <label for="alt">النص البديل Alt</label>
                <input id="alt" name="alt" value="{{ old('alt', $media->alt) }}" required>
            </div>
            <div class="field">
                <label for="caption">التعليق</label>
                <textarea id="caption" name="caption">{{ old('caption', $media->caption) }}</textarea>
            </div>
            <fieldset class="field">
                <legend>المعارض</legend>
                @foreach ($galleries as $gallery)
                    <label>
                        <input
                            type="checkbox"
                            name="gallery_ids[]"
                            value="{{ $gallery->id }}"
                            @checked(in_array($gallery->id, old('gallery_ids', $editing ? $media->galleries->pluck('id')->all() : [])))
                        >
                        {{ $gallery->title }}
                    </label>
                @endforeach
            </fieldset>
            <button class="btn" type="submit">{{ $editing ? 'حفظ' : 'رفع ومعالجة' }}</button>
        </div>
    </section>
</form>
@if ($editing)
    <div class="danger-zone">
        <form method="post" action="{{ route('admin.media.destroy', $media) }}" onsubmit="return confirm('أرشفة الصورة؟')">
            @csrf @method('delete')
            <button class="btn btn-danger" type="submit">أرشفة الصورة</button>
        </form>
    </div>
@endif
@endsection
