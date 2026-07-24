@extends('layouts.admin')

@section('title', $config['label'])

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $config['label'] }}</h1>
        <p class="muted">المسودة والنشر وتاريخ النشر محفوظة لكل سجل.</p>
    </div>
    <a class="btn" href="{{ route('admin.resources.create', $resource) }}">إضافة {{ $config['singular'] }}</a>
</div>
<section class="panel table-wrap">
    <table>
        <thead>
        <tr>
            <th>العنوان</th>
            <th>الرابط</th>
            <th>الحالة</th>
            <th>تاريخ النشر</th>
            <th>آخر تحديث</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($records as $record)
            <tr>
                <td>{{ $record->title }}</td>
                <td dir="ltr">{{ $record->slug ?? $record->path ?? '—' }}</td>
                <td>
                    <span class="badge {{ $record->status?->value === 'published' ? 'badge-published' : '' }}">
                        {{ $record->status?->value === 'published' ? 'منشور' : 'مسودة' }}
                    </span>
                </td>
                <td>{{ $record->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td>{{ $record->updated_at?->format('Y-m-d H:i') }}</td>
                <td><a class="btn btn-secondary" href="{{ route('admin.resources.edit', [$resource, $record]) }}">تحرير</a></td>
            </tr>
        @empty
            <tr><td colspan="6">لا توجد سجلات بعد.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $records->links() }}</div>
</section>
@endsection
