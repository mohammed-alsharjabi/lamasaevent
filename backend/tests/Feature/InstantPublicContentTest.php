<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Jobs\GenerateFrontendSnapshot;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\ContentPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstantPublicContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config()->set('public_delivery.static_root', base_path('../frontend/dist'));
    }

    public function test_a_published_service_is_visible_on_its_page_and_both_service_lists_immediately(): void
    {
        $service = Service::create([
            'title' => 'خدمة فورية للاختبار',
            'excerpt' => 'وصف الخدمة الذي يجب أن يظهر دون انتظار بناء الواجهة.',
            'status' => ContentStatus::Published,
            'content_blocks' => [],
        ]);

        app(ContentPublishingService::class)->sync($service);

        Queue::assertPushed(GenerateFrontendSnapshot::class);

        $detail = $this->get($service->routePath());
        $detail
            ->assertOk()
            ->assertHeader('X-Lamsa-Content-Source', 'database')
            ->assertSee('خدمة فورية للاختبار')
            ->assertSee('وصف الخدمة الذي يجب أن يظهر دون انتظار بناء الواجهة.')
            ->assertSee('data-live-content="service"', false)
            ->assertSee('class="content-detail--dark"', false)
            ->assertSee('class="managed-detail-fallback container"', false)
            ->assertSee(
                '<link rel="canonical" href="'.config('app.production_url').$service->routePath().'">',
                false,
            );
        $this->assertStringContainsString(
            'no-store',
            (string) $detail->headers->get('Cache-Control'),
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('خدمة فورية للاختبار')
            ->assertSee('href="'.$service->routePath().'"', false);

        $this->get('/services')
            ->assertOk()
            ->assertSee('خدمة فورية للاختبار')
            ->assertSee('href="'.$service->routePath().'"', false);
    }

    public function test_an_edit_replaces_the_cached_public_value_on_the_next_request(): void
    {
        $service = Service::create([
            'title' => 'خدمة قابلة للتحديث الفوري',
            'excerpt' => 'النص قبل التعديل',
            'status' => ContentStatus::Published,
            'content_blocks' => [],
        ]);
        app(ContentPublishingService::class)->sync($service);

        $this->get($service->routePath())->assertSee('النص قبل التعديل');

        $service->update(['excerpt' => 'النص بعد التعديل الفوري']);
        app(ContentPublishingService::class)->sync($service->fresh());

        $this->get($service->routePath())
            ->assertOk()
            ->assertSee('النص بعد التعديل الفوري')
            ->assertDontSee('النص قبل التعديل');
    }

    public function test_articles_and_areas_are_visible_in_their_pages_and_lists_immediately(): void
    {
        $article = Article::create([
            'title' => 'مقالة فورية للاختبار',
            'excerpt' => 'ملخص المقالة الفوري',
            'status' => ContentStatus::Published,
            'content_blocks' => [],
        ]);
        $area = Area::create([
            'title' => 'منطقة فورية للاختبار',
            'summary' => 'ملخص المنطقة الفوري',
            'status' => ContentStatus::Published,
            'is_active' => true,
            'content_blocks' => [],
        ]);

        app(ContentPublishingService::class)->sync($article);
        app(ContentPublishingService::class)->sync($area);

        $this->get($article->routePath())
            ->assertOk()
            ->assertSee('مقالة فورية للاختبار')
            ->assertSee('ملخص المقالة الفوري')
            ->assertSee('class="content-detail--dark"', false)
            ->assertSee('class="managed-detail-fallback container"', false);
        $this->get('/blog')
            ->assertOk()
            ->assertSee('مقالة فورية للاختبار')
            ->assertSee('href="'.$article->routePath().'"', false);

        $this->get($area->routePath())
            ->assertOk()
            ->assertSee('منطقة فورية للاختبار')
            ->assertSee('ملخص المنطقة الفوري');
        $this->get('/areas')
            ->assertOk()
            ->assertSee('منطقة فورية للاختبار')
            ->assertSee('href="'.$area->routePath().'"', false);
    }

    public function test_drafts_stay_hidden_and_published_content_enters_sitemap_immediately(): void
    {
        $draft = Service::create([
            'title' => 'مسودة لا تظهر للعامة',
            'excerpt' => 'يجب ألا يظهر هذا النص.',
            'status' => ContentStatus::Draft,
            'content_blocks' => [],
        ]);
        app(ContentPublishingService::class)->sync($draft);

        $published = Article::create([
            'title' => 'مقالة Sitemap الفورية',
            'excerpt' => 'اختبار خريطة الموقع.',
            'status' => ContentStatus::Published,
            'content_blocks' => [],
        ]);
        app(ContentPublishingService::class)->sync($published);

        $this->get($draft->routePath())->assertNotFound();
        $this->get('/services')->assertDontSee('مسودة لا تظهر للعامة');

        $sitemap = $this->get('/sitemap.xml');
        $sitemap
            ->assertOk()
            ->assertSee(config('app.production_url').$published->routePath())
            ->assertDontSee($draft->routePath());
        $this->assertStringContainsString(
            'no-store',
            (string) $sitemap->headers->get('Cache-Control'),
        );
    }

    public function test_public_content_apis_forbid_stale_browser_and_proxy_caches(): void
    {
        $export = $this->get('/api/v1/content-export');
        $export
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString(
            'no-store',
            (string) $export->headers->get('Cache-Control'),
        );

        $services = $this->get('/api/v1/content/services');
        $services
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString(
            'no-store',
            (string) $services->headers->get('Cache-Control'),
        );
    }

    public function test_gallery_contact_and_footer_changes_are_visible_on_the_next_request(): void
    {
        $contact = ContactSetting::create([
            'phone' => '+966500000001',
            'whatsapp' => '966500000001',
            'email' => 'before@example.test',
            'country_code' => 'SA',
        ]);
        $footer = SiteSetting::create([
            'key' => 'footer',
            'value' => [
                'description' => 'وصف التذييل قبل التعديل',
                'legal' => 'حقوق الاختبار',
            ],
            'group' => 'footer',
            'is_public' => true,
            'is_sensitive' => false,
        ]);
        $gallery = Gallery::create([
            'title' => 'معرض فوري للاختبار',
            'description' => 'وصف المعرض الفوري',
            'status' => ContentStatus::Published,
            'is_legacy' => false,
        ]);

        $this->get('/')->assertSee('وصف التذييل قبل التعديل');

        $contact->update([
            'phone' => '+966511111111',
            'whatsapp' => '966511111111',
            'email' => 'after@example.test',
        ]);
        $footer->update(['value' => [
            'description' => 'وصف التذييل بعد التعديل فورًا',
            'legal' => 'حقوق الاختبار',
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('وصف التذييل بعد التعديل فورًا')
            ->assertSee('after@example.test')
            ->assertSee('tel:+966511111111', false)
            ->assertSee('https://wa.me/966511111111', false)
            ->assertDontSee('وصف التذييل قبل التعديل');

        $this->get('/gallery')
            ->assertOk()
            ->assertSee($gallery->title)
            ->assertSee($gallery->description);
    }
}
