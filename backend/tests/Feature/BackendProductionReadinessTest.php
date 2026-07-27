<?php

namespace Tests\Feature;

use App\Jobs\GenerateFrontendSnapshot;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Media;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SitemapEntry;
use App\Models\User;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use App\Services\PublishPipeline;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BackendProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_and_restoring_new_content_updates_routes_sitemap_and_export(): void
    {
        Queue::fake();
        $article = Article::create([
            'title' => 'مقال جديد قابل للأرشفة',
            'status' => 'published',
            'published_at' => now(),
        ]);
        app(ContentPublishingService::class)->sync($article);
        $path = $article->routePath();

        $article->delete();

        $this->assertDatabaseHas('route_registry', [
            'path' => $path,
            'is_published' => false,
        ]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $path,
            'is_included' => false,
        ]);
        $this->assertFalse(
            app(ContentExportService::class)->build()['articles']
                ->contains('id', $article->id),
        );

        $article->restore();

        $this->assertDatabaseHas('route_registry', [
            'path' => $path,
            'is_published' => true,
        ]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $path,
            'is_included' => true,
        ]);
        $this->assertTrue(
            app(ContentExportService::class)->build()['articles']
                ->contains('id', $article->id),
        );
    }

    public function test_legacy_content_route_canonical_and_sitemap_are_immutable(): void
    {
        Queue::fake();
        $article = Article::create([
            'title' => 'مقال موروث',
            'status' => 'published',
            'published_at' => now(),
            'legacy_path' => 'blog/legacy/index.html',
        ]);
        app(ContentPublishingService::class)->sync($article);
        $route = $article->routeRecord()->sole();
        $sitemap = SitemapEntry::create([
            'route_registry_id' => $route->id,
            'loc' => $route->exact_url,
            'path' => $route->path,
            'position' => 1,
            'is_included' => true,
        ]);

        try {
            $article->update(['status' => 'draft']);
            $this->fail('Legacy content unpublishing was not rejected.');
        } catch (ValidationException) {
            $this->assertSame('published', $article->fresh()->status->value);
        }

        try {
            $article->delete();
            $this->fail('Legacy content deletion was not rejected.');
        } catch (ValidationException) {
            $this->assertFalse($article->trashed());
        }

        try {
            $route->update(['is_published' => false]);
            $this->fail('Legacy route unpublishing was not rejected.');
        } catch (ValidationException) {
            $this->assertTrue($route->fresh()->is_published);
        }

        try {
            $sitemap->update(['is_included' => false]);
            $this->fail('Legacy sitemap removal was not rejected.');
        } catch (ValidationException) {
            $this->assertTrue($sitemap->fresh()->is_included);
        }

        try {
            $article->seoMeta->update([
                'canonical' => 'https://lams-event.com/changed',
            ]);
            $this->fail('Legacy canonical mutation was not rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('seo_meta', [
                'id' => $article->seoMeta->id,
                'canonical' => $route->exact_url,
            ]);
        }
    }

    public function test_policies_prevent_deleting_records_that_would_break_relations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->sole());
        $category = ServiceCategory::create([
            'title' => 'تصنيف مرتبط',
            'status' => 'draft',
        ]);
        $parent = Service::create([
            'title' => 'خدمة رئيسية',
            'service_category_id' => $category->id,
            'status' => 'draft',
        ]);
        Service::create([
            'title' => 'خدمة فرعية',
            'parent_id' => $parent->id,
            'status' => 'draft',
        ]);
        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/policy.jpg',
            'webp_path' => 'media/policy.webp',
            'original_name' => 'policy.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'width' => 10,
            'height' => 10,
            'sha256' => str_repeat('a', 64),
            'alt' => 'صورة',
        ]);
        Article::create([
            'title' => 'مقال بصورة',
            'hero_media_id' => $media->id,
            'status' => 'draft',
        ]);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $category));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $parent));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $media));
        $this->assertFalse(Gate::forUser($admin)->allows('forceDelete', $parent));
    }

    public function test_contact_settings_are_a_database_enforced_singleton(): void
    {
        ContactSetting::create(['email' => 'first@example.test']);

        $this->expectException(QueryException::class);
        ContactSetting::create(['email' => 'second@example.test']);
    }

    public function test_contact_phone_display_follows_the_primary_number_safely(): void
    {
        $contact = ContactSetting::create([
            'phone' => '+966501234567',
            'phone_display' => '050 123 4567',
            'email' => 'contact@example.test',
        ]);

        $contact->update(['phone' => '+966568767724']);

        $this->assertSame('056 876 7724', $contact->fresh()->phone_display);

        $contact->update([
            'phone' => '+966501111111',
            'phone_display' => 'اتصل بنا الآن',
        ]);

        $this->assertSame('اتصل بنا الآن', $contact->fresh()->phone_display);
    }

    public function test_public_api_validates_pagination_and_filters_inactive_content(): void
    {
        Area::create([
            'title' => 'منطقة مخفية',
            'status' => 'published',
            'published_at' => now(),
            'is_active' => false,
        ]);
        $article = Article::create([
            'title' => 'مقال منشور',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->faqs()->createMany([
            ['question' => 'ظاهر؟', 'answer' => 'نعم', 'is_active' => true],
            ['question' => 'مخفي؟', 'answer' => 'نعم', 'is_active' => false],
        ]);

        $this->getJson('/api/v1/content/articles?per_page=0')
            ->assertUnprocessable();
        $this->getJson('/api/v1/content/areas')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/content/articles/{$article->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs');
    }

    public function test_pending_snapshot_jobs_are_coalesced(): void
    {
        Queue::fake();
        $first = Service::create(['title' => 'الأولى', 'status' => 'draft']);
        $second = Service::create(['title' => 'الثانية', 'status' => 'draft']);

        $firstJob = app(PublishPipeline::class)->queue($first, null);
        $secondJob = app(PublishPipeline::class)->queue($second, null);

        $this->assertTrue($firstJob->is($secondJob));
        Queue::assertPushed(GenerateFrontendSnapshot::class, 1);
    }

    public function test_sitemap_cache_is_invalidated_after_an_entry_update(): void
    {
        $entry = SitemapEntry::create([
            'loc' => 'https://lams-event.com/cache-test',
            'path' => '/cache-test',
            'position' => 1,
            'is_included' => true,
        ]);
        $this->get('/api/v1/sitemap.xml')->assertOk();
        $this->assertTrue(Cache::has('sitemap-xml:v1'));

        $entry->update(['lastmod' => now()->toDateString()]);

        $this->assertFalse(Cache::has('sitemap-xml:v1'));
    }

    public function test_api_server_errors_do_not_expose_technical_details(): void
    {
        $this->withExceptionHandling();
        config(['app.debug' => false]);
        Cache::forget(ContentExportService::CACHE_KEY);
        $this->app->bind(
            ContentExportService::class,
            fn () => new class extends ContentExportService
            {
                public function build(): array
                {
                    throw new RuntimeException('DATABASE_PASSWORD=top-secret');
                }
            },
        );

        $this->getJson('/api/v1/content-export')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Server Error')
            ->assertDontSee('top-secret');
    }
}
