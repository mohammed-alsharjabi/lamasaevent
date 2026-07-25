@extends('layouts.admin')

@section('title', 'Redirects 301')

@section('content')
<div class="topbar">
    <div>
        <h1>Redirects 301</h1>
        <p class="muted">تُنشأ التحويلات الدائمة تلقائيًا عند الحاجة، ويمكن إضافة تحويل موثق يدويًا.</p>
    </div>
</div>
<section class="panel">
    <form class="panel-body form-grid" method="post" action="{{ route('admin.redirects.store') }}">
        @csrf
        <div class="field">
            <label for="from_path">من المسار</label>
            <input id="from_path" name="from_path" placeholder="/old-path" dir="ltr" required>
        </div>
        <div class="field">
            <label for="to_path">إلى المسار</label>
            <input id="to_path" name="to_path" placeholder="/new-path" dir="ltr" required>
        </div>
        <input type="hidden" name="status_code" value="301">
        <div class="field span-2">
            <label for="reason">السبب</label>
            <input id="reason" name="reason">
        </div>
        <button class="btn" type="submit">إنشاء Redirect 301</button>
    </form>
</section>

<h2>السجل</h2>
<section class="panel table-wrap">
    <table>
        <thead><tr><th>من</th><th>إلى</th><th>الحالة</th><th>السبب</th><th></th></tr></thead>
        <tbody>
        @forelse ($redirects as $redirect)
            <tr>
                <td dir="ltr">{{ $redirect->from_path }}</td>
                <td dir="ltr">{{ $redirect->to_path }}</td>
                <td>{{ $redirect->is_active ? 'مفعل' : 'معطل' }}</td>
                <td>{{ $redirect->reason }}</td>
                <td>
                    @if ($redirect->is_active)
                        <form method="post" action="{{ route('admin.redirects.destroy', $redirect) }}">
                            @csrf @method('delete')
                            <button class="btn btn-danger" type="submit">تعطيل</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">لا توجد تحويلات.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $redirects->links() }}</div>
</section>
@endsection
