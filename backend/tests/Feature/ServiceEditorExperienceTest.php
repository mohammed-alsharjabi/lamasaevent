<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Models\ContactSetting;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use App\Services\ServiceDuplicationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceEditorExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::where('slug', 'super-admin')->sole());
        $this->actingAs($this->admin);
    }

    public function test_quick_create_generates_all_service_defaults_in_laravel(): void
    {
        $category = ServiceCategory::create([
            'title' => 'الحفلات',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        Service::create([
            'title' => 'خدمة سابقة',
            'service_category_id' => $category->id,
            'status' => 'draft',
            'sort_order' => 7,
        ]);
        ContactSetting::query()->updateOrCreate([], [
            'phone' => '+966500000000',
            'phone_display' => '050 000 0000',
            'whatsapp' => '966500000000',
            'email' => 'hello@example.test',
            'city' => 'الرياض',
            'region' => 'الرياض',
            'country_code' => 'SA',
        ]);
        SiteSetting::query()->updateOrCreate(['key' => 'brand'], [
            'value' => ['name' => 'لمسة'],
            'group' => 'general',
            'is_public' => true,
            'is_sensitive' => false,
        ]);

        Livewire::test(CreateService::class)
            ->assertSee('الإضافة السريعة')
            ->assertSee('وضع المطور')
            ->assertSee('ابدأ بقالب جاهز')
            ->fillForm([
                'title' => 'تنسيق حفلات الشركات',
                'service_category_id' => $category->id,
                'excerpt' => 'تنظيم احترافي ومتكامل لحفلات الشركات.',
                'status' => 'published',
                'content_blocks' => [
                    ['type' => 'intro', 'lead' => '<p>مقدمة <script>alert(1)</script>آمنة</p>'],
                    [
                        'type' => 'features',
                        'heading' => 'المميزات',
                        'items' => [['title' => 'تنظيم كامل', 'description' => 'من البداية للنهاية']],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('title', 'تنسيق حفلات الشركات')->sole();
        app(ContentPublishingService::class)->sync($service);
        $service->refresh();

        $this->assertNotEmpty($service->slug);
        $this->assertSame(8, $service->sort_order);
        $this->assertNotNull($service->published_at);
        $this->assertSame('تواصل عبر واتساب', $service->cta_label);
        $this->assertStringStartsWith('https://wa.me/966500000000?text=', $service->cta_url);
        $this->assertStringNotContainsString('<script>', $service->content_blocks[0]['lead']);
        $this->assertSame('تنسيق حفلات الشركات | لمسة', $service->seoMeta->title);
        $this->assertSame('تنظيم احترافي ومتكامل لحفلات الشركات.', $service->seoMeta->description);
        $this->assertSame('index,follow', $service->seoMeta->robots);
        $this->assertSame($service->seoMeta->canonical, $service->seoMeta->open_graph['url']);
        $this->assertSame('ar-SA', $service->seoMeta->hreflang[0]['lang']);
        $this->assertSame('x-default', $service->seoMeta->hreflang[1]['lang']);
        $this->assertSame('Service', $service->seoMeta->json_ld[0]['@type']);

        $exported = app(ContentExportService::class)
            ->build()['services']
            ->firstWhere('id', $service->id);
        $this->assertSame('features', $exported->content_blocks[1]['type']);
        $this->assertArrayNotHasKey('seo_overrides', $exported->toArray());
    }

    public function test_optional_seo_overrides_win_and_can_return_to_generated_values(): void
    {
        $service = Service::create([
            'title' => 'خدمة أصلية',
            'excerpt' => 'الوصف الأصلي',
            'status' => 'published',
            'seo_overrides' => [
                'title' => 'عنوان مخصص',
                'description' => 'وصف مخصص',
                'canonical' => 'https://lams-event.com/custom-service',
                'robots' => 'noindex,follow',
            ],
        ]);
        app(ContentPublishingService::class)->sync($service);

        $this->assertSame('عنوان مخصص', $service->fresh()->seoMeta->title);
        $this->assertSame('وصف مخصص', $service->fresh()->seoMeta->description);
        $this->assertSame('https://lams-event.com/custom-service', $service->fresh()->seoMeta->canonical);

        $service->update(['seo_overrides' => []]);
        app(ContentPublishingService::class)->sync($service);

        $this->assertStringStartsWith('خدمة أصلية', $service->fresh()->seoMeta->title);
        $this->assertSame('الوصف الأصلي', $service->fresh()->seoMeta->description);
        $this->assertSame(
            'https://lams-event.com'.$service->routePath(),
            $service->fresh()->seoMeta->canonical,
        );
    }

    public function test_service_duplication_creates_an_independent_draft_and_route(): void
    {
        $service = Service::create([
            'title' => 'خدمة قابلة للتكرار',
            'excerpt' => 'محتوى الخدمة',
            'content_blocks' => [['type' => 'text', 'body' => '<p>النص</p>']],
            'status' => 'published',
        ]);
        $service->faqs()->create([
            'question' => 'هل الخدمة متاحة؟',
            'answer' => 'نعم.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        app(ContentPublishingService::class)->sync($service);

        $duplicate = app(ServiceDuplicationService::class)
            ->duplicate($service, $this->admin->id);

        $this->assertSame('draft', $duplicate->status->value);
        $this->assertNotSame($service->slug, $duplicate->slug);
        $this->assertNull($duplicate->published_at);
        $this->assertSame($service->content_blocks, $duplicate->content_blocks);
        $this->assertSame('هل الخدمة متاحة؟', $duplicate->faqs()->sole()->question);
        $this->assertDatabaseHas('route_registry', [
            'routable_type' => $duplicate->getMorphClass(),
            'routable_id' => $duplicate->id,
            'is_published' => false,
        ]);
    }

    public function test_authenticated_manager_can_open_private_draft_preview(): void
    {
        $service = Service::create([
            'title' => 'معاينة مسودة',
            'status' => 'draft',
            'content_blocks' => [['type' => 'text', 'body' => '<p>نص المعاينة</p>']],
        ]);

        $this->get(route('admin.services.preview', $service))
            ->assertOk()
            ->assertSee('هذه معاينة خاصة')
            ->assertSee('نص المعاينة');
    }

    public function test_migration_preserves_existing_service_metadata_as_overrides(): void
    {
        $service = Service::create([
            'title' => 'خدمة موجودة قبل الترقية',
            'status' => 'published',
        ]);
        app(ContentPublishingService::class)->sync($service);
        DB::table('seo_meta')
            ->where('seoable_type', $service->getMorphClass())
            ->where('seoable_id', $service->id)
            ->update([
                'title' => 'عنوان محفوظ كما هو',
                'description' => 'وصف محفوظ كما هو',
                'canonical' => 'https://lams-event.com/preserved-service',
            ]);

        $migration = require database_path(
            'migrations/2026_08_01_000000_add_service_editor_defaults.php',
        );
        $migration->down();
        DB::table('services')->where('id', $service->id)->update([
            'cta_label' => 'زر محفوظ',
            'cta_url' => 'https://wa.me/966500000000',
            'whatsapp_enabled' => true,
        ]);
        $migration->up();

        $row = DB::table('services')->where('id', $service->id)->first();
        $seoOverrides = json_decode((string) $row->seo_overrides, true);
        $ctaOverrides = json_decode((string) $row->cta_overrides, true);

        $this->assertTrue((bool) $row->uses_generated_defaults);
        $this->assertSame('عنوان محفوظ كما هو', $seoOverrides['title']);
        $this->assertSame('وصف محفوظ كما هو', $seoOverrides['description']);
        $this->assertSame('https://lams-event.com/preserved-service', $seoOverrides['canonical']);
        $this->assertSame('زر محفوظ', $ctaOverrides['label']);
        $this->assertSame('show', $ctaOverrides['mode']);
    }
}
