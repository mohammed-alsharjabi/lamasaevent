@extends('layouts.admin')

@php
    $editing = $record->exists;
    $seo = $record->seoMeta ?? null;
    $json = fn ($value) => $value ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $labels = [
        'slug' => 'Slug',
        'path' => 'المسار',
        'type' => 'نوع الصفحة',
        'topic' => 'التصنيف النصي',
        'excerpt' => 'الملخص',
        'summary' => 'الوصف المختصر',
        'description' => 'الوصف',
        'content_blocks' => 'كتل المحتوى المنظمة (JSON)',
        'sort_order' => 'الترتيب',
        'service_category_id' => 'تصنيف الخدمة',
    ];
@endphp

@section('title', ($editing ? 'تحرير ' : 'إضافة ').$config['singular'])

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $editing ? 'تحرير' : 'إضافة' }} {{ $config['singular'] }}</h1>
        @if ($record->legacy_path)<p class="muted" dir="ltr">Legacy: {{ $record->legacy_path }}</p>@endif
    </div>
    <a class="btn btn-secondary" href="{{ route('admin.resources.index', $resource) }}">رجوع</a>
</div>

<form method="post" action="{{ $editing ? route('admin.resources.update', [$resource, $record]) : route('admin.resources.store', $resource) }}">
    @csrf
    @if ($editing) @method('put') @endif
    <section class="panel">
        <div class="panel-body form-grid">
            <div class="field span-2">
                <label for="title">العنوان</label>
                <input id="title" name="title" value="{{ old('title', $record->title) }}" required>
            </div>

            @foreach ($config['fields'] as $field)
                @continue($field === 'title')
                @if ($field === 'service_category_id')
                    <div class="field">
                        <label for="{{ $field }}">{{ $labels[$field] }}</label>
                        <select id="{{ $field }}" name="{{ $field }}">
                            <option value="">بدون تصنيف</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old($field, $record->$field) === (string) $category->id)>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif (in_array($field, ['excerpt', 'summary', 'description'], true))
                    <div class="field span-2">
                        <label for="{{ $field }}">{{ $labels[$field] }}</label>
                        <textarea id="{{ $field }}" name="{{ $field }}">{{ old($field, $record->$field) }}</textarea>
                    </div>
                @elseif ($field === 'content_blocks')
                    <div class="field span-2">
                        <label for="{{ $field }}">{{ $labels[$field] }}</label>
                        <textarea class="code" id="{{ $field }}" name="{{ $field }}">{{ old($field, $json($record->$field)) }}</textarea>
                    </div>
                @else
                    <div class="field">
                        <label for="{{ $field }}">{{ $labels[$field] ?? $field }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $record->$field) }}" @if(in_array($field, ['slug', 'path'], true)) dir="ltr" @endif>
                    </div>
                @endif
            @endforeach

            <div class="field">
                <label for="status">الحالة</label>
                <select id="status" name="status" required>
                    <option value="draft" @selected(old('status', $record->status?->value ?? 'draft') === 'draft')>مسودة</option>
                    <option value="published" @selected(old('status', $record->status?->value) === 'published')>منشور</option>
                </select>
            </div>
            <div class="field">
                <label for="published_at">تاريخ النشر</label>
                <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $record->published_at?->format('Y-m-d\TH:i')) }}">
            </div>

            @if ($editing && isset($record->slug) && $record->status?->value === 'published')
                <div class="field span-2">
                    <label>
                        <input type="checkbox" name="create_redirect" value="1" @checked(old('create_redirect'))>
                        إنشاء Redirect 301 تلقائيًا إذا تغير slug
                    </label>
                    <small class="muted">لن يسمح النظام بتغيير slug المنشور من دون هذا التأكيد.</small>
                </div>
            @endif
        </div>
    </section>

    @if ($config['seo'])
        <h2>إعدادات SEO</h2>
        <section class="panel">
            <div class="panel-body form-grid">
                <div class="field span-2">
                    <label for="seo-title">SEO Title</label>
                    <input id="seo-title" name="seo[title]" value="{{ old('seo.title', $seo?->title ?? $record->title) }}" required>
                </div>
                <div class="field span-2">
                    <label for="seo-description">Meta Description</label>
                    <textarea id="seo-description" name="seo[description]" required>{{ old('seo.description', $seo?->description) }}</textarea>
                </div>
                <div class="field span-2">
                    <label for="seo-canonical">Canonical URL</label>
                    <input id="seo-canonical" name="seo[canonical]" value="{{ old('seo.canonical', $seo?->canonical) }}" required dir="ltr">
                </div>
                <div class="field span-2">
                    <label for="seo-robots">Robots</label>
                    <input id="seo-robots" name="seo[robots]" value="{{ old('seo.robots', $seo?->robots) }}" dir="ltr">
                </div>
                @foreach (['open_graph' => 'Open Graph JSON', 'twitter' => 'Twitter JSON', 'json_ld' => 'Schema JSON-LD'] as $field => $label)
                    <div class="field span-2">
                        <label for="seo-{{ $field }}">{{ $label }}</label>
                        <textarea class="code" id="seo-{{ $field }}" name="seo[{{ $field }}]">{{ old("seo.$field", $json($seo?->$field)) }}</textarea>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="actions" style="margin-top: 20px">
        <button class="btn" type="submit">حفظ</button>
    </div>
</form>

@if ($editing)
    <div class="danger-zone">
        <form method="post" action="{{ route('admin.resources.destroy', [$resource, $record]) }}" onsubmit="return confirm('تأكيد الحذف؟ يجب تحويل المنشور إلى مسودة أولًا.')">
            @csrf
            @method('delete')
            <button class="btn btn-danger" type="submit">حذف {{ $config['singular'] }}</button>
        </form>
    </div>
@endif
@endsection
