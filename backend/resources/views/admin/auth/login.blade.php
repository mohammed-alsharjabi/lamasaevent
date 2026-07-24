@extends('layouts.admin')

@section('title', 'تسجيل الدخول')

@section('content')
<div class="login-wrap">
    <section class="panel login-card">
        <h1>تسجيل دخول الإدارة</h1>
        <p class="muted">لوحة خاصة بإدارة محتوى موقع لمسه التميز.</p>
        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <div class="field">
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus dir="ltr">
            </div>
            <div class="field">
                <label for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required dir="ltr">
            </div>
            <div class="field">
                <label><input name="remember" type="checkbox" value="1"> تذكرني</label>
            </div>
            <button class="btn" type="submit">دخول آمن</button>
        </form>
    </section>
</div>
@endsection
