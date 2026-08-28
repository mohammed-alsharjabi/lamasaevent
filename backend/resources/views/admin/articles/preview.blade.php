<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>معاينة: {{ $article->title }}</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #172033; background: #f8fafc; }
        * { box-sizing: border-box; }
        body { margin: 0; line-height: 1.9; }
        .notice { position: sticky; top: 0; z-index: 5; padding: .65rem 1rem; text-align: center; color: #854d0e; background: #fef9c3; border-bottom: 1px solid #fde047; font-weight: 700; }
        .container { width: min(920px, calc(100% - 2rem)); margin-inline: auto; }
        .hero { padding: clamp(2.5rem, 7vw, 6rem) 0 2rem; }
        .hero img { width: 100%; max-height: 34rem; object-fit: cover; border-radius: 1.25rem; box-shadow: 0 20px 60px #0f172a1a; }
        h1 { margin: 1.25rem 0 .5rem; font-size: clamp(2rem, 5vw, 3.7rem); line-height: 1.25; }
        h2 { margin-top: 0; font-size: clamp(1.4rem, 3vw, 2rem); }
        .lead { max-width: 52rem; color: #475569; font-size: 1.1rem; }
        .meta { color: #0f766e; font-weight: 700; }
        .section { margin-block: 1rem; padding: clamp(1.3rem, 4vw, 2.5rem); border: 1px solid #e2e8f0; border-radius: 1.25rem; background: white; box-shadow: 0 10px 35px #0f172a0a; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .9rem; }
        details { border-bottom: 1px solid #e2e8f0; padding-block: .75rem; }
        summary { cursor: pointer; font-weight: 800; }
    </style>
</head>
<body>
    <div class="notice">هذه معاينة خاصة وغير مفهرسة — احفظ التعديلات ثم حدّث الصفحة لرؤيتها.</div>
    <main>
        <header class="hero container">
            @if($article->heroMedia)
                <img src="{{ $article->heroMedia->url() }}" alt="{{ $article->effectiveHeroAlt() }}">
            @endif
            @if($article->category)<p class="meta">{{ $article->category->name }}</p>@endif
            <h1>{{ $article->title }}</h1>
            @if($article->excerpt)<p class="lead">{{ $article->excerpt }}</p>@endif
        </header>

        <div class="container">
            @foreach(($article->content_blocks ?? []) as $block)
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
                                @foreach(($block['items'] ?? []) as $item)
                                    <article>
                                        @if($item['title'] ?? null)<h3>{{ $item['title'] }}</h3>@endif
                                        @if($item['description'] ?? null)<p>{{ $item['description'] }}</p>@endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                        @break
                    @case('gallery')
                        <section class="section gallery">
                            <h2>{{ $block['heading'] ?? 'صور المقال' }}</h2>
                            <div class="grid">
                                @foreach(($block['media_ids'] ?? []) as $mediaId)
                                    @if($media = $galleryMedia->get($mediaId))
                                        <img src="{{ $media->url() }}" alt="{{ $media->alt ?: $media->original_name }}">
                                    @endif
                                @endforeach
                            </div>
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
                    @default
                        {!! $block['html'] ?? '' !!}
                @endswitch
            @endforeach

            @if($article->faqs->isNotEmpty())
                <section class="section">
                    <h2>الأسئلة الشائعة</h2>
                    @foreach($article->faqs->where('is_active', true) as $faq)
                        <details><summary>{{ $faq->question }}</summary><p>{{ $faq->answer }}</p></details>
                    @endforeach
                </section>
            @endif
        </div>
    </main>
</body>
</html>
