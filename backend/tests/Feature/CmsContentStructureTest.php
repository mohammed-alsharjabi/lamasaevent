<?php

namespace Tests\Feature;

use App\Jobs\GenerateFrontendSnapshot;
use App\Models\Article;
use App\Models\PublishJob;
use App\Models\Service;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use App\Services\ContentSnapshotWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsContentStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_slugs_are_generated_internally_once_and_are_unique(): void
    {
        $first = Article::create([
            'title' => 'تنظيم حفلات الرياض',
            'status' => 'draft',
        ]);
        $second = Article::create([
            'title' => 'تنظيم حفلات الرياض',
            'status' => 'draft',
        ]);
        $originalSlug = $first->slug;

        $this->assertNotEmpty($originalSlug);
        $this->assertNotSame($first->slug, $second->slug);

        $first->update(['title' => 'عنوان جديد لا يغيّر الرابط']);

        $this->assertSame($originalSlug, $first->fresh()->slug);
    }

    public function test_services_support_one_safe_main_and_sub_service_level(): void
    {
        $main = Service::create([
            'title' => 'الخدمة الرئيسية',
            'status' => 'draft',
        ]);
        $child = Service::create([
            'title' => 'الخدمة الفرعية',
            'parent_id' => $main->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($child->parent->is($main));
        $this->assertTrue($main->children->contains($child));

        $this->expectException(ValidationException::class);
        Service::create([
            'title' => 'مستوى ثالث غير مسموح',
            'parent_id' => $child->id,
            'status' => 'draft',
        ]);
    }

    public function test_service_hierarchy_is_included_in_the_public_export(): void
    {
        $main = Service::create([
            'title' => 'الخدمة الرئيسية',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $child = Service::create([
            'title' => 'الخدمة الفرعية',
            'parent_id' => $main->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $services = app(ContentExportService::class)->build()['services'];
        $exportedMain = $services->firstWhere('id', $main->id);
        $exportedChild = $services->firstWhere('id', $child->id);

        $this->assertSame($main->id, $exportedChild->parent->id);
        $this->assertSame($child->id, $exportedMain->children->sole()->id);
    }

    public function test_new_published_content_is_synchronized_to_the_sitemap(): void
    {
        $service = Service::create([
            'title' => 'خدمة جديدة',
            'status' => 'published',
            'published_at' => now(),
        ]);

        app(ContentPublishingService::class)->sync($service);

        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'loc' => 'https://lams-event.com'.$service->routePath(),
            'is_included' => true,
        ]);

        $service->update(['status' => 'draft']);
        app(ContentPublishingService::class)->sync($service);

        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => false,
        ]);
    }

    public function test_content_snapshots_are_written_atomically(): void
    {
        $output = storage_path(
            'framework/testing/content-'.Str::uuid().'.json',
        );

        try {
            app(ContentSnapshotWriter::class)->write(
                $output,
                app(ContentSnapshotWriter::class)->encode(['version' => 1]),
            );

            $this->assertSame(
                ['version' => 1],
                json_decode((string) file_get_contents($output), true),
            );
            $this->assertFileDoesNotExist($output.'.tmp');
        } finally {
            @unlink($output);
        }
    }

    public function test_publish_job_refreshes_the_active_astro_snapshot(): void
    {
        Storage::fake('local');
        $output = storage_path(
            'framework/testing/active-'.Str::uuid().'.json',
        );
        config(['publishing.frontend_snapshot_path' => $output]);
        $publishJob = PublishJob::create([
            'action' => 'snapshot',
            'status' => 'pending',
            'scheduled_for' => now(),
        ]);

        try {
            (new GenerateFrontendSnapshot($publishJob->id))->handle(
                app(ContentExportService::class),
                app(ContentSnapshotWriter::class),
            );

            $snapshot = json_decode(
                (string) file_get_contents($output),
                true,
            );
            $this->assertSame(2, $snapshot['schema_version']);
            $this->assertSame('completed', $publishJob->fresh()->status);
            Storage::disk('local')->assertExists(
                $publishJob->fresh()->snapshot_path,
            );
        } finally {
            @unlink($output);
        }
    }
}
