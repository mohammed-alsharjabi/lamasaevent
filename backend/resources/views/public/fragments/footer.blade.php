@php
    $socialLabels = [
        'instagram' => 'إنستغرام', 'tiktok' => 'تيك توك', 'snapchat' => 'سناب شات',
        'x' => 'إكس', 'twitter' => 'إكس', 'facebook' => 'فيسبوك',
        'youtube' => 'يوتيوب', 'linkedin' => 'لينكدإن',
    ];
    $socialLinks = collect($contact['social_links'] ?? [])->filter();
    $hasCompanyLine = filled($footer['company_line'] ?? null) || filled($contact['email'] ?? null);
    $hasCredit = filled($footer['credit_title'] ?? null) || filled($footer['credit_whatsapp_url'] ?? null) || filled($footer['credit_profile_url'] ?? null);
@endphp
<footer class="site-footer">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <p class="site-footer__brand-name">{{ $brand['name'] ?? '' }}</p>
            <p class="site-footer__brand-desc">{{ $footer['description'] ?? '' }}</p>
            @if ($hasCompanyLine)
                <p class="site-footer__brand-desc" style="margin-top: 0.5rem; font-size: 0.9em; opacity: 0.92;">
                    {{ $footer['company_line'] ?? '' }}
                    @if (filled($contact['email'] ?? null))<a href="mailto:{{ $contact['email'] }}" dir="ltr">{{ $contact['email'] }}</a>@endif
                </p>
            @endif
        </div>
        <div class="site-footer__columns">
            <div class="site-footer__col">
                <h2 class="site-footer__col-h">{{ $footer['quick_links_heading'] ?? 'روابط سريعة' }}</h2>
                <ul class="site-footer__list">
                    @foreach ($items as $item)
                        <li><a href="{{ $item['url'] ?? '/' }}" @if ($item['open_in_new_tab'] ?? false) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] ?? '' }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="site-footer__col">
                <h2 class="site-footer__col-h">{{ $footer['contact_heading'] ?? 'تواصل معنا' }}</h2>
                <ul class="site-footer__list site-footer__list--contact">
                    <li><a class="site-footer__phone" href="tel:{{ $contact['phone'] ?? '' }}" dir="ltr">{{ $contact['phone_display'] ?? '' }}</a></li>
                    <li class="site-footer__cta-row">
                        <a class="site-footer__pill site-footer__pill--wa" href="https://wa.me/{{ $contact['whatsapp'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $ui['whatsapp'] ?? 'واتساب' }}</a>
                        <a class="site-footer__pill site-footer__pill--call" href="tel:{{ $contact['phone'] ?? '' }}">{{ $ui['call'] ?? 'اتصال' }}</a>
                    </li>
                    @if ($socialLinks->isNotEmpty())
                        <li>
                            <ul class="site-footer__social" aria-label="حسابات التواصل الاجتماعي">
                                @foreach ($socialLinks as $network => $url)
                                    <li><a class="site-footer__social-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $socialLabels[$network] ?? $network }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
    <div class="container site-footer__legal">
        <p>{{ $footer['legal'] ?? '' }}</p>
        @if ($hasCredit)
            <div class="site-footer__credit" aria-label="{{ ($footer['credit_aria_label'] ?? '') ?: 'بيانات منفذ الموقع' }}">
                @if (filled($footer['credit_title'] ?? null))<p class="site-footer__credit-title">{{ $footer['credit_title'] }}</p>@endif
                @if (filled($footer['credit_whatsapp_url'] ?? null) || filled($footer['credit_profile_url'] ?? null))
                    <div class="site-footer__credit-actions">
                        @if (filled($footer['credit_whatsapp_url'] ?? null))<a class="site-footer__credit-btn site-footer__credit-btn--wa" href="{{ $footer['credit_whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">{{ ($footer['credit_whatsapp_label'] ?? '') ?: 'واتساب' }}</a>@endif
                        @if (filled($footer['credit_profile_url'] ?? null))<a class="site-footer__credit-btn site-footer__credit-btn--at" href="{{ $footer['credit_profile_url'] }}" target="_blank" rel="noopener noreferrer" dir="ltr">{{ ($footer['credit_profile_label'] ?? '') ?: 'الملف الشخصي' }}</a>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</footer>
