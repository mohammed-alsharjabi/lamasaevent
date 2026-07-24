@extends('layouts.admin')

@section('title', 'التواصل وSEO')

@section('content')
<div class="topbar">
    <div>
        <h1>بيانات التواصل وإعدادات SEO العامة</h1>
        <p class="muted">تستخدمها الواجهة العامة وSchema المؤسسة.</p>
    </div>
</div>
<form method="post" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('put')
    <section class="panel">
        <div class="panel-body form-grid">
            @foreach (['phone' => 'الهاتف', 'phone_display' => 'الهاتف المعروض', 'whatsapp' => 'واتساب', 'email' => 'البريد', 'city' => 'المدينة', 'region' => 'المنطقة', 'country_code' => 'رمز الدولة'] as $field => $label)
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $contact->$field) }}">
                </div>
            @endforeach
            <div class="field span-2">
                <label for="site_name">اسم الموقع</label>
                <input id="site_name" name="site_name" value="{{ old('site_name', data_get($settings, 'site_name.text', 'لمسه التميز للحفلات')) }}" required>
            </div>
            <div class="field span-2">
                <label for="site_description">الوصف العام</label>
                <textarea id="site_description" name="site_description" required>{{ old('site_description', data_get($settings, 'site_description.text')) }}</textarea>
            </div>
            <div class="field span-2">
                <label for="default_robots">Robots الافتراضي</label>
                <input id="default_robots" name="default_robots" dir="ltr" value="{{ old('default_robots', data_get($settings, 'default_robots.text', 'index, follow')) }}">
            </div>
        </div>
    </section>
    <button class="btn" style="margin-top: 18px" type="submit">حفظ الإعدادات</button>
</form>
@endsection
