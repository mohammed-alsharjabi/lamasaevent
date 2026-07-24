@extends('layouts.admin')

@section('title', 'نظرة عامة')

@section('content')
<div class="topbar">
    <div>
        <h1>نظرة عامة</h1>
        <p class="muted">إدارة المحتوى المستعاد وحالة النشر والمسارات.</p>
    </div>
</div>
<div class="grid">
    @foreach ($counts as $label => $count)
        <article class="card">
            <span>{{ $label }}</span>
            <strong>{{ number_format($count) }}</strong>
        </article>
    @endforeach
</div>
@endsection
