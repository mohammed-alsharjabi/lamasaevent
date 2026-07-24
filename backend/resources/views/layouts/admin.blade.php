<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'لوحة التحكم') — لمسه التميز</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body>
@auth
<div class="shell">
    <aside class="sidebar">
        <div class="brand">لمسه التميز CMS</div>
        <nav class="nav" aria-label="لوحة التحكم">
            <a href="{{ route('admin.dashboard') }}">نظرة عامة</a>
            @foreach (config('content.resources') as $key => $item)
                <a href="{{ route('admin.resources.index', $key) }}">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('admin.settings.edit') }}">التواصل وSEO</a>
            <a href="{{ route('admin.sitemap.index') }}">Sitemap</a>
            <a href="{{ route('admin.redirects.index') }}">Redirects 301</a>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">تسجيل الخروج</button>
            </form>
        </nav>
    </aside>
    <main class="main">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <strong>تعذر حفظ التغييرات:</strong>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
@else
    @yield('content')
@endauth
</body>
</html>
