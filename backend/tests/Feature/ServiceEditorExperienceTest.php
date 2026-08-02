<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\ContactSetting;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use App\Services\ServiceContentSuggestionService;
use App\Services\ServiceDuplicationService;
use App\Services\ServiceFormDataMapper;
use App\Services\ServiceSeoAuditService;
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
            ->assertSee('معلومات الخدمة')
            ->assertSee('وضع المطور')
            ->assertDontSee('قالب جاهز')
            ->assertDontSee('محتوى صفحة الخدمة')
            ->assertDontSee('زر الإجراء وواتساب')
            ->assertDontSee('داخل Laravel')
            ->assertSee('حفظ كمسودة')
            ->assertSee('نشر وإضافة خدمة جديدة')
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
        $this->assertTrue(collect($exported->content_blocks)->contains(
            fn (array $block): bool => ($block['type'] ?? null) === 'features',
        ));
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

    public function test_first_class_overrides_and_automatic_schema_are_exposed_as_final_api_values(): void
    {
        $service = Service::create([
            'title' => 'تنسيق مناسبة خاصة',
            'excerpt' => 'وصف أساسي للخدمة.',
            'status' => 'published',
            'slug_override' => 'private-event-riyadh',
            'seo_title_override' => 'عنوان مخصص للخدمة',
            'meta_description_override' => 'وصف مخصص وواضح لنتيجة البحث.',
            'og_title_override' => 'عنوان مخصص للمشاركة',
            'target_search_phrase' => 'تنسيق مناسبة خاصة',
            'uses_generated_defaults' => true,
        ]);
        app(ContentPublishingService::class)->sync($service);
        $service->refresh();

        $this->assertSame('private-event-riyadh', $service->slug);
        $this->assertSame('عنوان مخصص للخدمة', $service->seoMeta->title);
        $this->assertSame('وصف مخصص وواضح لنتيجة البحث.', $service->seoMeta->description);
        $this->assertSame('عنوان مخصص للمشاركة', $service->seoMeta->open_graph['title']);
        $this->assertSame('index,follow', $service->seoMeta->robots);
        $this->assertSame('Service', $service->seoMeta->json_ld[0]['@type']);
        $this->assertSame('BreadcrumbList', $service->seoMeta->json_ld[1]['@type']);

        $exported = app(ContentExportService::class)
            ->build()['services']
            ->firstWhere('id', $service->id);
        $this->assertSame('عنوان مخصص للخدمة', $exported->seoMeta->title);
        $this->assertArrayNotHasKey('target_search_phrase', $exported->toArray());

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->assertFormSet([
                'seo_title_override' => 'عنوان مخصص للخدمة',
                'meta_description_override' => 'وصف مخصص وواضح لنتيجة البحث.',
                'slug_override' => 'private-event-riyadh',
                'target_search_phrase' => 'تنسيق مناسبة خاصة',
            ]);
    }

    public function test_simple_fields_round_trip_to_visual_blocks_without_duplicates(): void
    {
        $stored = app(ServiceFormDataMapper::class)->forStorage([
            'quick_details' => '<p>تفاصيل الخدمة</p>',
            'quick_gallery_media_ids' => [8, 8, 9],
            'cta_overrides' => ['mode' => 'hide'],
            'content_blocks' => [
                ['type' => 'related_services', 'heading' => 'قد يناسبك', 'service_ids' => [2, 2, 3]],
            ],
        ]);

        $this->assertSame(['text', 'gallery', 'related_services'], array_column($stored['content_blocks'], 'type'));
        $this->assertSame([8, 9], $stored['content_blocks'][1]['media_ids']);
        $this->assertSame([2, 3], $stored['content_blocks'][2]['service_ids']);
        $this->assertSame('hide', $stored['cta_overrides']['mode']);

        $service = new Service([
            'title' => 'خدمة تجريبية',
            'content_blocks' => $stored['content_blocks'],
            'cta_overrides' => $stored['cta_overrides'],
            'uses_generated_defaults' => true,
        ]);
        $form = app(ServiceFormDataMapper::class)->forForm([
            'cta_overrides' => $stored['cta_overrides'],
        ], $service);

        $this->assertSame('<p>تفاصيل الخدمة</p>', $form['quick_details']);
        $this->assertSame([8, 9], $form['quick_gallery_media_ids']);
        $this->assertSame('hide', $form['cta_overrides']['mode']);
        $this->assertSame('related_services', $form['content_blocks'][0]['type']);
    }

    public function test_suggestions_are_editable_and_never_publish_content(): void
    {
        $suggestions = app(ServiceContentSuggestionService::class)->suggest([
            'title' => 'Neymar Events FCB',
            'excerpt' => '',
        ]);

        $this->assertTrue($suggestions['apply_excerpt']);
        $this->assertTrue($suggestions['apply_faqs']);
        $this->assertNotEmpty($suggestions['suggested_features']);
        $this->assertArrayNotHasKey('status', $suggestions);
        $this->assertArrayNotHasKey('published_at', $suggestions);
    }

    public function test_title_can_fill_blank_basic_content_without_exposing_a_suggestion_workflow(): void
    {
        $component = Livewire::test(CreateService::class)
            ->fillForm(['title' => 'تنسيق حفلات التخرج']);

        $this->assertStringContainsString(
            'تنسيق حفلات التخرج',
            (string) $component->get('data.excerpt'),
        );
        $this->assertStringContainsString(
            'تنسيق حفلات التخرج',
            json_encode($component->get('data.quick_details'), JSON_UNESCAPED_UNICODE) ?: '',
        );
        $component
            ->assertDontSee('اقتراح محتوى')
            ->assertDontSee('الذكاء الاصطناعي');
    }

    public function test_seo_audit_uses_practical_checks_and_protects_drafts_from_indexing(): void
    {
        $audit = app(ServiceSeoAuditService::class)->audit([
            'title' => 'تنسيق مناسبة خاصة',
            'excerpt' => 'خدمة لتنظيم المناسبة باحتراف.',
            'quick_details' => '<p>تفاصيل واضحة عن الخدمة.</p>',
            'status' => 'draft',
            'target_search_phrase' => 'تنسيق مناسبة خاصة',
        ]);

        $this->assertFalse($audit['ready']);
        $this->assertSame('يحتاج مراجعة', $audit['status']);
        $this->assertSame('noindex,nofollow', $audit['values']['robots']);
        $this->assertTrue(collect($audit['checks'])->contains(
            fn (array $check): bool => $check['label'] === 'فهرسة المسودة' && $check['ok'],
        ));
        $this->assertTrue(collect($audit['checks'])->contains(
            fn (array $check): bool => $check['label'] === 'الصورة البارزة' && ! $check['ok'],
        ));

        $emptyAudit = app(ServiceSeoAuditService::class)->audit(['status' => 'draft']);
        $this->assertStringContainsString('اسم الخدمة', $emptyAudit['values']['title']);
        $this->assertTrue(collect($emptyAudit['checks'])->contains(
            fn (array $check): bool => $check['label'] === 'وصف نتيجة البحث' && ! $check['ok'],
        ));
    }

    public function test_related_services_are_exported_only_from_published_records(): void
    {
        $published = Service::create([
            'title' => 'خدمة مرتبطة منشورة',
            'excerpt' => 'الخدمة المنشورة.',
            'status' => 'published',
        ]);
        $draft = Service::create([
            'title' => 'خدمة مرتبطة مسودة',
            'status' => 'draft',
        ]);
        $service = Service::create([
            'title' => 'الخدمة الأساسية',
            'status' => 'published',
            'content_blocks' => [[
                'type' => 'related_services',
                'service_ids' => [$published->id, $draft->id],
            ]],
        ]);
        app(ContentPublishingService::class)->sync($published);
        app(ContentPublishingService::class)->sync($draft);
        app(ContentPublishingService::class)->sync($service);

        $exported = app(ContentExportService::class)
            ->build()['services']
            ->firstWhere('id', $service->id);

        $this->assertCount(1, $exported->content_blocks[0]['services']);
        $this->assertSame('خدمة مرتبطة منشورة', $exported->content_blocks[0]['services'][0]['title']);
    }

    public function test_export_normalizes_early_visual_editor_and_seo_shapes(): void
    {
        $service = Service::create([
            'title' => 'خدمة بتنسيق قديم للمحرر',
            'excerpt' => 'وصف الخدمة',
            'status' => 'published',
            'published_at' => now(),
            'content_blocks' => [
                'version' => 1,
                'sections' => [
                    [
                        'type' => 'rich_text',
                        'title' => 'التفاصيل',
                        'content' => 'نص الخدمة',
                    ],
                    [
                        'type' => 'process',
                        'title' => 'الخطوات',
                        'items' => ['الخطوة الأولى', 'الخطوة الثانية'],
                    ],
                ],
            ],
        ]);
        app(ContentPublishingService::class)->sync($service);
        $service->seoMeta()->update([
            'hreflang' => [
                ['lang' => 'ar-SA', 'url' => 'https://lams-event.com/legacy-shape'],
            ],
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => 'خدمة بتنسيق قديم للمحرر',
            ],
        ]);

        $exported = app(ContentExportService::class)
            ->build()['services']
            ->firstWhere('id', $service->id);

        $this->assertSame('text', $exported->content_blocks[0]['type']);
        $this->assertSame('نص الخدمة', $exported->content_blocks[0]['body']);
        $this->assertSame('steps', $exported->content_blocks[1]['type']);
        $this->assertSame('الخطوة الأولى', $exported->content_blocks[1]['items'][0]['title']);
        $this->assertSame(
            'https://lams-event.com/legacy-shape',
            $exported->seoMeta->hreflang[0]['href'],
        );
        $this->assertSame('Service', $exported->seoMeta->json_ld[0]['@type']);
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
