@php
    $brandItem = $items->first();
    $ctaItem = $items->last();
    $navigation = $items->slice(1, max(0, $items->count() - 2));
    $logoWidth = (int) ($brand['logo_width'] ?? 520);
    $logoHeight = (int) ($brand['logo_height'] ?? 480);
    $menuOpenLabel = $ui['menu_open'] ?? 'فتح القائمة';
    $menuCloseLabel = $ui['menu_close'] ?? 'إغلاق القائمة';
@endphp
<div class="site-header-wrap">
    <input type="checkbox" id="site-nav-toggle" class="site-header__checkbox visually-hidden">
    <header class="site-header">
        <div class="site-header__bar">
            <div class="container site-header__bar-inner">
                <div class="site-header__start">
                    <a class="site-header__brand brand site-header__brand--with-logo" href="{{ $brandItem['url'] ?? '/' }}" aria-label="{{ $brandItem['label'] ?? $brand['name'] ?? '' }}">
                        <img class="site-header__logo" src="{{ $brand['logo_src'] ?? '/icons/logo-without-bg.png' }}" alt="" width="{{ $logoWidth }}" height="{{ $logoHeight }}" decoding="async">
                        <span class="site-header__brand-text">
                            <span class="site-header__brand-name">{{ $brand['name'] ?? '' }}</span>
                            <span class="brand__tag">{{ $brand['tagline'] ?? '' }}</span>
                        </span>
                    </a>
                    <nav class="site-header__nav site-header__nav--desktop" aria-label="{{ $ui['primary_navigation'] ?? 'التنقل الرئيسي' }}">
                        @foreach ($navigation as $item)
                            <a class="site-header__link" href="{{ $item['url'] ?? '/' }}" @if ($item['open_in_new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] ?? '' }}</a>
                        @endforeach
                    </nav>
                </div>
                <div class="site-header__end">
                    <a href="{{ $ctaItem['url'] ?? '/contact' }}" class="ui-btn ui-btn--primary site-header__cta site-header__cta--bar">{{ $ctaItem['label'] ?? 'طلب عرض' }}</a>
                    <label for="site-nav-toggle" class="site-header__menu-btn" aria-label="{{ $menuOpenLabel }}" id="site-nav-toggle-label">
                        <span class="site-header__menu-icon" aria-hidden="true">
                            <span class="site-header__menu-line"></span>
                            <span class="site-header__menu-line"></span>
                            <span class="site-header__menu-line"></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </header>
    <label for="site-nav-toggle" class="site-header__scrim" aria-hidden="true"></label>
    <div class="site-header__drawer" role="dialog" aria-modal="true" aria-labelledby="site-nav-drawer-title">
        <div class="site-header__drawer-head">
            <div class="site-header__drawer-brand">
                <img class="site-header__drawer-logo" src="{{ $brand['logo_src'] ?? '/icons/logo-without-bg.png' }}" alt="" width="{{ $logoWidth }}" height="{{ $logoHeight }}" decoding="async">
                <p id="site-nav-drawer-title" class="site-header__drawer-title">{{ $brand['name'] ?? '' }}</p>
            </div>
            <label for="site-nav-toggle" class="site-header__drawer-close" aria-label="{{ $menuCloseLabel }}">
                <span class="site-header__drawer-close-icon" aria-hidden="true"></span>
            </label>
        </div>
        <nav class="site-header__nav site-header__nav--mobile" aria-label="{{ $ui['primary_navigation'] ?? 'التنقل الرئيسي' }}">
            @foreach ($navigation as $item)
                <a class="site-header__drawer-link" href="{{ $item['url'] ?? '/' }}" @if ($item['open_in_new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] ?? '' }}</a>
            @endforeach
        </nav>
        <div class="site-header__drawer-cta">
            <a href="{{ $ctaItem['url'] ?? '/contact' }}" class="ui-btn ui-btn--primary site-header__cta site-header__cta--drawer">{{ $ctaItem['label'] ?? 'طلب عرض' }}</a>
        </div>
    </div>
</div>
<script>
    (function () {
        var toggle = document.getElementById("site-nav-toggle");
        var label = document.getElementById("site-nav-toggle-label");
        var menuOpenLabel = @json($menuOpenLabel);
        var menuCloseLabel = @json($menuCloseLabel);
        if (!toggle || !label) return;
        function sync() {
            var open = toggle.checked;
            label.setAttribute("aria-label", open ? menuCloseLabel : menuOpenLabel);
            label.setAttribute("aria-expanded", open ? "true" : "false");
        }
        toggle.addEventListener("change", sync);
        sync();
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && toggle.checked) {
                toggle.checked = false;
                toggle.dispatchEvent(new Event("change"));
            }
        });
    })();
</script>
