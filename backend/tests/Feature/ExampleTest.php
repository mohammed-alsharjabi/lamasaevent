<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Menu;
use App\Models\SitemapEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use App\Services\ImageProcessor;
use App\Services\PublicPageRenderer;
use App\Services\SlugRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_is_available_and_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Lamsa-Content-Source', 'database');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_uses_the_self_hosted_cairo_font(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('font-family:"Cairo"', false)
            ->assertSee('/fonts/cairo/cairo-arabic.woff2', false)
            ->assertSee('/fonts/cairo/cairo-latin.woff2', false);

        $this->assertFileExists(public_path('fonts/cairo/cairo-arabic.woff2'));
        $this->assertFileExists(public_path('fonts/cairo/cairo-latin.woff2'));

        $this->get('/fonts/cairo/cairo-arabic.woff2')
            ->assertOk()
            ->assertHeader('Content-Type', 'font/woff2')
            ->assertHeader(
                'Cache-Control',
                'immutable, max-age=31536000, public',
            );
    }

    public function test_non_admin_users_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_published_slug_cannot_be_changed_directly(): void
    {
        $article = Article::create([
            'title' => 'اختبار',
            'slug' => 'original-slug',
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $article->update(['slug' => 'unsafe-change']);
    }

    public function test_slug_service_creates_a_permanent_redirect(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = Article::create([
            'title' => 'اختبار',
            'slug' => 'original-slug',
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
        app(ContentPublishingService::class)->sync($article, $admin->id);

        app(SlugRedirectService::class)->change($article, 'new-slug', $admin);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/blog/original-slug',
            'to_path' => '/blog/new-slug',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertSame('new-slug', $article->fresh()->slug);
    }

    public function test_validated_upload_generates_a_webp_derivative(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->image('event.jpg', 120, 80);

        $media = app(ImageProcessor::class)->upload($upload);

        Storage::disk('public')->assertExists($media->path);
        Storage::disk('public')->assertExists($media->webp_path);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(120, $media->width);
        $this->assertSame(80, $media->height);
    }

    public function test_mime_spoofed_upload_is_rejected(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->createWithContent(
            'malicious.jpg',
            '<?php echo "not an image";',
        );

        $this->expectException(ValidationException::class);
        app(ImageProcessor::class)->upload($upload);
    }

    public function test_uploading_the_same_image_reuses_the_existing_media_record(): void
    {
        Storage::fake('public');
        $firstUpload = UploadedFile::fake()->image('first.jpg', 120, 80);
        $secondUpload = UploadedFile::fake()->createWithContent(
            'second.jpg',
            (string) file_get_contents($firstUpload->getRealPath()),
        );

        $first = app(ImageProcessor::class)->upload($firstUpload);
        $second = app(ImageProcessor::class)->upload($secondUpload);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('media', 1);
    }

    public function test_content_export_keeps_sitemap_lastmod_as_a_calendar_date(): void
    {
        SitemapEntry::create([
            'loc' => 'https://lams-event.com/example',
            'path' => '/example',
            'lastmod' => '2026-05-14',
            'changefreq' => 'monthly',
            'priority' => 0.5,
            'position' => 1,
            'is_included' => true,
        ]);

        $export = app(ContentExportService::class)->build();

        $this->assertSame('2026-05-14', $export['sitemap']->first()['lastmod']);
    }

    public function test_global_cms_data_is_exported_and_invalidates_the_api_cache(): void
    {
        $contact = ContactSetting::create([
            'phone' => '+966500000000',
            'phone_display' => '050 000 0000',
            'whatsapp' => '966500000000',
            'email' => 'cms@example.test',
            'city' => 'الرياض',
            'region' => 'منطقة الرياض',
            'country_code' => 'SA',
        ]);
        $setting = SiteSetting::create([
            'key' => 'brand',
            'value' => ['name' => 'اسم من قاعدة البيانات'],
            'group' => 'general',
            'is_public' => true,
        ]);
        $menu = Menu::create([
            'name' => 'القائمة الرئيسية',
            'location' => 'header',
            'is_active' => true,
        ]);
        $menu->allItems()->create([
            'label' => 'الرئيسية',
            'url' => '/',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $export = app(ContentExportService::class)->build();

        $this->assertSame($contact->email, $export['contact']->email);
        $this->assertSame(
            'اسم من قاعدة البيانات',
            $export['settings']['brand']['name'],
        );
        $this->assertSame(
            'الرئيسية',
            $export['menus']->sole()->allItems->sole()->label,
        );

        Cache::put(ContentExportService::CACHE_KEY, '{"stale":true}', now()->addMinute());
        Cache::put(PublicPageRenderer::CACHE_KEY, '{"stale":true}', now()->addMinute());
        $setting->update(['value' => ['name' => 'اسم محدث']]);

        $this->assertFalse(Cache::has(ContentExportService::CACHE_KEY));
        $this->assertFalse(Cache::has(PublicPageRenderer::CACHE_KEY));
    }
}
