@extends('layouts.admin')

@section('title', 'Sitemap')

@section('content')
<div class="topbar">
    <div>
        <h1>إدارة Sitemap</h1>
        <p class="muted">الروابط الموروثة محمية من الاستبعاد، ويمكن تحديث lastmod والتكرار والأولوية.</p>
    </div>
</div>
<section class="panel table-wrap">
    <table>
        <thead><tr><th>#</th><th>URL</th><th>الخصائص</th><th></th></tr></thead>
        <tbody>
        @foreach ($entries as $entry)
            <tr>
                <td>{{ $entry->position }}</td>
                <td dir="ltr">{{ $entry->loc }} @if($entry->routeRecord?->is_legacy)<span class="badge">موروث</span>@endif</td>
                <td>
                    <form class="actions" method="post" action="{{ route('admin.sitemap.update', $entry) }}">
                        @csrf @method('put')
                        <input type="date" name="lastmod" value="{{ $entry->lastmod?->toDateString() }}">
                        <select name="changefreq">
                            @foreach (['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'] as $freq)
                                <option value="{{ $freq }}" @selected($entry->changefreq === $freq)>{{ $freq }}</option>
                            @endforeach
                        </select>
                        <input name="priority" type="number" min="0" max="1" step=".05" value="{{ $entry->priority }}" style="width:75px">
                        <label><input type="checkbox" name="is_included" value="1" @checked($entry->is_included)> مفعل</label>
                        <button class="btn btn-secondary" type="submit">حفظ</button>
                    </form>
                </td>
                <td></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination">{{ $entries->links() }}</div>
</section>
@endsection
