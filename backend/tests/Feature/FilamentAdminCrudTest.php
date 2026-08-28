<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\Pages\CreateArea;
use App\Filament\Resources\Areas\Pages\EditArea;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\ContactSettings\Pages\EditContactSetting;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\ServiceCategories\Pages\CreateServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Jobs\GenerateFrontendSnapshot;
use App\Models\Area;
use App\Models\Article;
use App\Models\ContactSetting;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ContentExportService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentAdminCrudTest extends TestCase
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
        $this->admin->roles()->attach(
            Role::where('slug', 'super-admin')->sole(),
        );
        $this->actingAs($this->admin);
    }

    public function test_service_create_update_archive_and_restore_work_through_filament(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'title' => 'خدمة من لوحة التحكم',
                'excerpt' => 'وصف الخدمة',
                'status' => 'published',
                'sort_order' => 5,
                'is_featured' => true,
                'whatsapp_enabled' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()
            ->where('title', 'خدمة من لوحة التحكم')
            ->sole();
        $this->assertSame($this->admin->id, $service->created_by);
        $this->assertSame($this->admin->id, $service->updated_by);
        $this->assertNotNull($service->published_at);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => true,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->fillForm(['title' => 'خدمة محدثة من لوحة التحكم'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'title' => 'خدمة محدثة من لوحة التحكم',
            'updated_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('content_revisions', [
            'revisionable_type' => $service->getMorphClass(),
            'revisionable_id' => $service->id,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => false,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => true,
        ]);
        Queue::assertPushed(GenerateFrontendSnapshot::class);
    }

    public function test_service_list_shows_delete_for_new_content_but_not_legacy_routes(): void
    {
        $newService = Service::create([
            'title' => 'خدمة جديدة قابلة للحذف',
            'status' => 'draft',
        ]);
        $legacyService = Service::create([
            'title' => 'خدمة قديمة محمية',
            'legacy_path' => 'services/protected/index.html',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Livewire::test(ListServices::class)
            ->assertTableActionVisible('delete', $newService)
            ->assertTableActionHidden('delete', $legacyService);
    }

    public function test_contact_settings_form_is_clear_and_updates_the_visible_number(): void
    {
        $contact = ContactSetting::create([
            'phone' => '+966501234567',
            'phone_display' => '050 123 4567',
            'whatsapp' => '966501234567',
            'email' => 'contact@example.test',
            'city' => 'الرياض',
            'region' => 'منطقة الرياض',
            'country_code' => 'SA',
        ]);

        Livewire::test(EditContactSetting::class, ['record' => $contact->id])
            ->assertSee('تعديل بيانات التواصل')
            ->assertSee('أين تظهر هذه البيانات؟')
            ->assertSee('إنستغرام')
            ->fillForm([
                'phone' => '+966568767724',
                'social_links' => [
                    'instagram' => 'https://instagram.com/lamasaevent',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $contact->refresh();

        $this->assertSame('056 876 7724', $contact->phone_display);
        $this->assertSame(
            'https://instagram.com/lamasaevent',
            $contact->social_links['instagram'],
        );
        Queue::assertPushed(GenerateFrontendSnapshot::class);
    }

    public function test_footer_settings_are_edited_as_named_fields_and_can_hide_credit(): void
    {
        $footer = SiteSetting::create([
            'key' => 'footer',
            'value' => [
                'description' => 'الوصف القديم',
                'company_line' => 'سطر المنشأة',
                'quick_links_heading' => 'روابط سريعة',
                'contact_heading' => 'تواصل معنا',
                'legal' => 'جميع الحقوق محفوظة',
                'credit_aria_label' => 'بيانات المنفذ',
                'credit_title' => 'اسم المنفذ',
                'credit_whatsapp_url' => 'https://wa.me/966500000000',
                'credit_whatsapp_label' => 'واتساب',
                'credit_profile_url' => 'https://example.test/profile',
                'credit_profile_label' => 'الملف الشخصي',
            ],
            'group' => 'general',
            'is_public' => true,
            'is_sensitive' => false,
        ]);
        $value = $footer->value;
        $value['description'] = 'وصف واضح من لوحة التحكم';
        $value['credit_title'] = '';
        $value['credit_whatsapp_url'] = '';
        $value['credit_whatsapp_label'] = '';
        $value['credit_profile_url'] = '';
        $value['credit_profile_label'] = '';

        Livewire::test(EditSiteSetting::class, ['record' => $footer->id])
            ->assertSee('محتوى الفوتر')
            ->assertSee('اسم منفذ الموقع أو النسبة')
            ->fillForm(['value' => $value])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer->refresh();

        $this->assertSame('وصف واضح من لوحة التحكم', $footer->value['description']);
        $this->assertSame('', $footer->value['credit_title']);
        $this->assertArrayHasKey('quick_links_heading', $footer->value);
        Queue::assertPushed(GenerateFrontendSnapshot::class);
    }

    /**
     * @param  class-string  $createPage
     * @param  class-string  $editPage
     * @param  class-string<Area|Article|Page|ServiceCategory>  $model
     * @param  array<string, mixed>  $formData
     */
    #[DataProvider('managedContentResources')]
    public function test_all_managed_content_resources_complete_filament_crud(
        string $createPage,
        string $editPage,
        string $model,
        array $formData,
    ): void {
        Livewire::test($createPage)
            ->fillForm($formData)
            ->call('create')
            ->assertHasNoFormErrors();

        $record = $model::query()->where('title', $formData['title'])->sole();
        $path = $record->routePath();

        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $path,
            'is_included' => true,
        ]);

        Livewire::test($editPage, ['record' => $record->id])
            ->fillForm(['title' => $formData['title'].' — محدث'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas($record->getTable(), [
            'id' => $record->id,
            'title' => $formData['title'].' — محدث',
            'updated_by' => $this->admin->id,
        ]);

        Livewire::test($editPage, ['record' => $record->id])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($record->getTable(), ['id' => $record->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $path,
            'is_included' => false,
        ]);

        Livewire::test($editPage, ['record' => $record->id])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($record->getTable(), ['id' => $record->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $path,
            'is_included' => true,
        ]);
    }

    /**
     * @return array<string, array{class-string, class-string, class-string, array<string, mixed>}>
     */
    public static function managedContentResources(): array
    {
        return [
            'article' => [
                CreateArticle::class,
                EditArticle::class,
                Article::class,
                [
                    'title' => 'مقال اختبار الإنتاج',
                    'excerpt' => 'مقتطف المقال',
                    'status' => 'published',
                    'sort_order' => 10,
                    'is_featured' => false,
                ],
            ],
            'area' => [
                CreateArea::class,
                EditArea::class,
                Area::class,
                [
                    'title' => 'منطقة اختبار الإنتاج',
                    'summary' => 'ملخص المنطقة',
                    'status' => 'published',
                    'sort_order' => 10,
                    'is_active' => true,
                ],
            ],
            'page' => [
                CreatePage::class,
                EditPage::class,
                Page::class,
                [
                    'title' => 'صفحة اختبار الإنتاج',
                    'type' => 'page',
                    'path' => '/production-crud-test',
                    'summary' => 'ملخص الصفحة',
                    'status' => 'published',
                    'sort_order' => 10,
                    'is_featured' => false,
                ],
            ],
            'service category' => [
                CreateServiceCategory::class,
                EditServiceCategory::class,
                ServiceCategory::class,
                [
                    'title' => 'تصنيف خدمات اختبار الإنتاج',
                    'summary' => 'ملخص التصنيف',
                    'status' => 'published',
                    'sort_order' => 10,
                    'is_active' => true,
                ],
            ],
        ];
    }

    public function test_embedded_gallery_completes_filament_crud_without_creating_a_new_route(): void
    {
        Livewire::test(CreateGallery::class)
            ->fillForm([
                'title' => 'معرض اختبار الإنتاج',
                'description' => 'وصف المعرض',
                'status' => 'published',
                'sort_order' => 10,
                'is_featured' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $gallery = Gallery::query()
            ->where('title', 'معرض اختبار الإنتاج')
            ->sole();

        $this->assertNotNull($gallery->published_at);
        $this->assertDatabaseMissing('route_registry', [
            'routable_type' => $gallery->getMorphClass(),
            'routable_id' => $gallery->id,
        ]);
        $this->assertNotNull(
            app(ContentExportService::class)
                ->build()['galleries']
                ->firstWhere('id', $gallery->id),
        );

        Cache::put(ContentExportService::CACHE_KEY, 'stale');

        Livewire::test(EditGallery::class, ['record' => $gallery->id])
            ->fillForm(['title' => 'معرض اختبار الإنتاج — محدث'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(Cache::get(ContentExportService::CACHE_KEY));
        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'title' => 'معرض اختبار الإنتاج — محدث',
            'updated_by' => $this->admin->id,
        ]);

        Livewire::test(EditGallery::class, ['record' => $gallery->id])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted('galleries', ['id' => $gallery->id]);

        Livewire::test(EditGallery::class, ['record' => $gallery->id])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted('galleries', ['id' => $gallery->id]);
    }
}
