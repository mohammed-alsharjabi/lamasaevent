@php
    $blocks = is_array($entity['content_blocks'] ?? null) ? $entity['content_blocks'] : [];
    $hero = is_array($entity['hero_media_summary'] ?? null) ? $entity['hero_media_summary'] : null;
    $summary = $entity['summary'] ?? $entity['excerpt'] ?? null;
    $faqs = collect($entity['faqs'] ?? [])->filter(fn ($faq) => is_array($faq) && ($faq['is_active'] ?? true));
    $hasFaqBlock = collect($blocks)->contains(fn ($block) => is_array($block) && ($block['type'] ?? null) === 'faq');
    $hasManagedBlocks = $blocks === [] || collect($blocks)->contains(fn ($block) => is_array($block) && ! filled($block['html'] ?? null));
    $usesDarkDetail = in_array($kind, ['article', 'service', 'service-category'], true);
@endphp
<main id="main" @class(['content-detail--dark' => $usesDarkDetail]) data-live-content="{{ $kind }}" data-live-content-id="{{ (int) ($entity['id'] ?? 0) }}">
    @if ($hasManagedBlocks)
        <article class="managed-detail-fallback container">
            @if ($hero && filled($hero['public_url'] ?? null))
                <figure class="managed-detail-fallback__media">
                    <img
                        src="{{ $hero['public_url'] }}"
                        alt="{{ $hero['alt'] ?? $entity['title'] ?? '' }}"
                        width="1200"
                        height="760"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                    >
                </figure>
            @endif
            <div class="managed-detail-fallback__body">
                <h1>{{ $entity['title'] ?? '' }}</h1>
                @if (filled($summary))
                    <p>{{ $summary }}</p>
                @endif
            </div>
        </article>
    @endif

    @foreach ($blocks as $block)
        @continue(! is_array($block))
        @if (filled($block['html'] ?? null))
            {!! $block['html'] !!}
        @elseif (($block['type'] ?? null) === 'intro')
            <section class="container svc-detail__section">
                @if (filled($block['heading'] ?? null))<h2 class="svc-detail__h2">{{ $block['heading'] }}</h2>@endif
                <div class="svc-detail__prose">{!! $block['lead'] ?? '' !!}</div>
            </section>
        @elseif (($block['type'] ?? null) === 'text')
            <section class="container svc-detail__section">
                @if (filled($block['heading'] ?? null))<h2 class="svc-detail__h2">{{ $block['heading'] }}</h2>@endif
                <div class="svc-detail__prose">{!! $block['body'] ?? '' !!}</div>
            </section>
        @elseif (in_array($block['type'] ?? null, ['features', 'steps'], true))
            <section class="container svc-detail__section">
                <h2 class="svc-detail__h2">{{ $block['heading'] ?? (($block['type'] ?? '') === 'steps' ? 'خطوات التنفيذ' : 'مميزات الخدمة') }}</h2>
                <ul class="svc-detail__highlights">
                    @foreach (($block['items'] ?? []) as $item)
                        @continue(! is_array($item))
                        <li>
                            @if (filled($item['title'] ?? null))<strong>{{ $item['title'] }}</strong>@endif
                            @if (filled($item['description'] ?? null))<span>{{ $item['description'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif (($block['type'] ?? null) === 'gallery')
            <section class="container svc-detail__section">
                <h2 class="svc-detail__h2">{{ $block['heading'] ?? 'معرض الصور' }}</h2>
                <div class="svc-detail__gallery">
                    @foreach (($block['media'] ?? []) as $media)
                        @continue(! is_array($media) || blank($media['public_url'] ?? null))
                        <figure>
                            <img src="{{ $media['public_url'] }}" alt="{{ $media['alt'] ?? $media['original_name'] ?? $entity['title'] ?? '' }}" loading="lazy" decoding="async">
                            @if (filled($media['caption'] ?? null))<figcaption>{{ $media['caption'] }}</figcaption>@endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @elseif (($block['type'] ?? null) === 'faq')
            <section class="container svc-detail__section">
                <h2 class="svc-detail__h2">{{ $block['heading'] ?? 'الأسئلة الشائعة' }}</h2>
                <div class="svc-detail__prose">
                    @foreach (($block['items'] ?? []) as $item)
                        @continue(! is_array($item))
                        <details><summary>{{ $item['question'] ?? '' }}</summary><p>{{ $item['answer'] ?? '' }}</p></details>
                    @endforeach
                </div>
            </section>
        @elseif (($block['type'] ?? null) === 'related_services' && filled($block['services'] ?? null))
            <section class="container svc-detail__section">
                <h2 class="svc-detail__h2">{{ $block['heading'] ?? 'خدمات مرتبطة' }}</h2>
                <div class="svc-detail__sibling-grid">
                    @foreach (($block['services'] ?? []) as $service)
                        @continue(! is_array($service))
                        <a class="svc-detail__sibling-card" href="{{ $service['public_path'] ?? '/' }}">
                            <span class="svc-detail__sibling-title">{{ $service['title'] ?? '' }}</span>
                            @if (filled($service['excerpt'] ?? null))<p class="svc-detail__sibling-intro">{{ $service['excerpt'] }}</p>@endif
                            <span class="svc-detail__sibling-hint">التفاصيل</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @elseif (($block['type'] ?? null) === 'cta')
            <section class="container service-cta">
                @if (filled($block['heading'] ?? null))<h2 class="service-cta__title">{{ $block['heading'] }}</h2>@endif
                @if (filled($block['text'] ?? null))<p class="service-cta__text">{{ $block['text'] }}</p>@endif
                @if (filled($block['url'] ?? null))<a class="cta-wa" href="{{ $block['url'] }}">{{ $block['label'] ?? 'تواصل معنا' }}</a>@endif
            </section>
        @endif
    @endforeach

    @if (! $hasFaqBlock && $faqs->isNotEmpty())
        <section class="container svc-detail__section">
            <h2 class="svc-detail__h2">الأسئلة الشائعة</h2>
            <div class="svc-detail__prose">
                @foreach ($faqs as $faq)
                    <details><summary>{{ $faq['question'] ?? '' }}</summary><p>{{ $faq['answer'] ?? '' }}</p></details>
                @endforeach
            </div>
        </section>
    @endif

    @if ($related !== [])
        <section class="container svc-detail__section">
            <h2 class="svc-detail__h2">{{ $kind === 'service-category' ? 'خدمات التصنيف' : 'الخدمات الفرعية' }}</h2>
            <div class="svc-detail__sibling-grid">
                @foreach ($related as $service)
                    <a class="svc-detail__sibling-card" href="{{ $service['public_path'] ?? '/' }}" data-cms-id="{{ (int) ($service['id'] ?? 0) }}">
                        <span class="svc-detail__sibling-title">{{ $service['title'] ?? '' }}</span>
                        @if (filled($service['excerpt'] ?? null))<p class="svc-detail__sibling-intro">{{ $service['excerpt'] }}</p>@endif
                        <span class="svc-detail__sibling-hint">التفاصيل</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (($entity['whatsapp_enabled'] ?? false) && filled($entity['cta_url'] ?? null))
        <aside class="container service-cta">
            <h2 class="service-cta__title">تواصل معنا</h2>
            <a class="cta-wa" href="{{ $entity['cta_url'] }}" target="_blank" rel="noopener noreferrer">{{ $entity['cta_label'] ?? 'تواصل عبر واتساب' }}</a>
        </aside>
    @endif
</main>
