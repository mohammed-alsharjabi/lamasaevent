<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>معاينة: {{ $service->title }}</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #172033; background: #f8fafc; }
        * { box-sizing: border-box; }
        body { margin: 0; line-height: 1.8; }
        .notice { position: sticky; top: 0; z-index: 5; padding: .65rem 1rem; text-align: center; color: #854d0e; background: #fef9c3; border-bottom: 1px solid #fde047; font-weight: 700; }
        .container { width: min(1100px, calc(100% - 2rem)); margin-inline: auto; }
        .hero { padding: clamp(2.5rem, 7vw, 6rem) 0 3rem; }
        .hero img { width: 100%; max-height: 34rem; object-fit: cover; border-radius: 1.25rem; box-shadow: 0 20px 60px #0f172a1a; }
        h1 { margin: 1.25rem 0 .5rem; font-size: clamp(2rem, 5vw, 4rem); line-height: 1.2; }
        h2 { margin-top: 0; font-size: clamp(1.45rem, 3vw, 2.2rem); }
        .lead { max-width: 52rem; color: #475569; font-size: 1.1rem; }
        .section { margin-block: 1rem; padding: clamp(1.3rem, 4vw, 2.5rem); border: 1px solid #e2e8f0; border-radius: 1.25rem; background: white; box-shadow: 0 10px 35px #0f172a0a; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .card { padding: 1rem; border-radius: .9rem; background: #f8fafc; border: 1px solid #e2e8f0; }
        .gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .9rem; }
        .cta { text-align: center; color: white; background: #173f38; }
        .button { display: inline-flex; padding: .75rem 1.15rem; border-radius: 999px; color: #173f38; background: white; text-decoration: none; font-weight: 800; }
        details { border-bottom: 1px solid #e2e8f0; padding-block: .75rem; }
        summary { cursor: pointer; font-weight: 800; }
    </style>
</head>
<body>
    <div class="notice">هذه معاينة خاصة وغير مفهرسة — احفظ التعديلات ثم حدّث هذه الصفحة لرؤيتها.</div>
    <main>
        <header class="hero container">
            @if($service->heroMedia)
                <img src="{{ $service->heroMedia->url() }}" alt="{{ $service->heroMedia->alt ?: $service->title }}">
            @endif
            <h1>{{ $service->title }}</h1>
            @if($service->excerpt)<p class="lead">{{ $service->excerpt }}</p>@endif
        </header>

        <div class="container">
            @foreach(($service->content_blocks ?? []) as $block)
                @switch($block['type'] ?? '')
                    @case('intro')
                    @case('text')
                        <section class="section">
                            @if($block['heading'] ?? null)<h2>{{ $block['heading'] }}</h2>@endif
                            {!! $block['lead'] ?? $block['body'] ?? '' !!}
                        </section>
                        @break
                    @case('features')
                    @case('steps')
                        <section class="section">
                            @if($block['heading'] ?? null)<h2>{{ $block['heading'] }}</h2>@endif
                            <div class="grid">
                                @foreach(($block['items'] ?? []) as $index => $item)
                                    <article class="card">
                                        @if(($block['type'] ?? '') === 'steps')<b>{{ $index + 1 }}</b>@endif
                                        @if($item['title'] ?? null)<h3>{{ $item['title'] }}</h3>@endif
                                        @if($item['description'] ?? null)<p>{{ $item['description'] }}</p>@endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                        @break
                    @case('gallery')
                        <section class="section gallery">
                            @if($block['heading'] ?? null)<h2>{{ $block['heading'] }}</h2>@endif
                            <div class="grid">
                                @foreach(($block['media_ids'] ?? []) as $mediaId)
                                    @if($media = $galleryMedia->get($mediaId))
                                        <img src="{{ $media->url() }}" alt="{{ $media->alt ?: $media->original_name }}">
                                    @endif
                                @endforeach
                            </div>
                        </section>
                        @break
                    @case('cta')
                        <section class="section cta">
                            <h2>{{ $block['heading'] ?? 'هل ترغب في هذه الخدمة؟' }}</h2>
                            @if($block['text'] ?? null)<p>{{ $block['text'] }}</p>@endif
                            @if(($block['url'] ?? $cta['url']) && $cta['enabled'])
                                <a class="button" href="{{ $block['url'] ?? $cta['url'] }}">{{ $block['label'] ?? $cta['label'] }}</a>
                            @endif
                        </section>
                        @break
                    @case('faq')
                        <section class="section">
                            <h2>{{ $block['heading'] ?? 'الأسئلة الشائعة' }}</h2>
                            @foreach(($block['items'] ?? []) as $item)
                                <details><summary>{{ $item['question'] ?? '' }}</summary><p>{{ $item['answer'] ?? '' }}</p></details>
                            @endforeach
                        </section>
                        @break
                    @case('related_services')
                        <section class="section">
                            <h2>{{ $block['heading'] ?? 'خدمات مرتبطة' }}</h2>
                            <div class="grid">
                                @foreach(($block['service_ids'] ?? []) as $serviceId)
                                    @if($related = $relatedServices->get($serviceId))
                                        <article class="card">
                                            <h3>{{ $related->title }}</h3>
                                            @if($related->excerpt)<p>{{ $related->excerpt }}</p>@endif
                                        </article>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                        @break
                    @default
                        {!! $block['html'] ?? '' !!}
                @endswitch
            @endforeach
        </div>
    </main>
</body>
</html>
