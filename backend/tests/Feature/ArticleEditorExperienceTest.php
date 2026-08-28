<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Filament\Resources\ArticleCategories\ArticleCategoryResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Media;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ArticleDuplicationService;
use App\Services\ArticleFormDataMapper;
use App\Services\ArticleSeoAuditService;
use App\Services\ContentExportService;
use App\Services\ContentPublishingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleEditorExperienceTest extends TestCase
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

    public function test_quick_article_publishing_generates_content_and_seo_defaults(): void
    {
        $category = ArticleCategory::create([
            'name' => 'نصائح المناسبات',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Article::create([
            'title' => 'مقال سابق',
            'article_category_id' => $category->id,
            'status' => 'draft',
            'sort_order' => 7,
        ]);
        SiteSetting::query()->updateOrCreate(['key' => 'brand'], [
            'value' => ['name' => 'لمسة'],
            'group' => 'general',
            'is_public' => true,
            'is_sensitive' => false,
        ]);

        Livewire::test(CreateArticle::class)
            ->assertSee('معلومات المقال')
            ->assertSee('تحسين الظهور في محركات البحث')
            ->assertSee('وضع المطور')
            ->assertSee('حفظ كمسودة')
            ->assertSee('نشر وإضافة مقال جديد')
            ->assertDontSee('كتل المحتوى المنظمة')
            ->assertDontSee('Meta Keywords (اختياري)')
            ->assertDontSee('داخل Laravel')
            ->fillForm([
                'title' => 'كيف تختار منسق حفلات مناسب',
                'article_category_id' => $category->id,
                'excerpt' => 'دليل عملي لاختيار منسق الحفلات المناسب.',
                'quick_body' => '<p>ابدأ بتحديد احتياجات المناسبة والميزانية.</p>',
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $article = Article::query()
            ->where('title', 'كيف تختار منسق حفلات مناسب')
            ->sole();
        $article->refresh();

        $this->assertSame('published', $article->status->value);
        $this->assertSame(8, $article->sort_order);
        $this->assertNotNull($article->published_at);
        $this->assertSame('text', $article->content_blocks[0]['type']);
        $this->assertStringContainsString('تحديد احتياجات المناسبة', $article->content_blocks[0]['body']);
        $this->assertSame('كيف تختار منسق حفلات مناسب | لمسة', $article->seoMeta->title);
        $this->assertSame('index,follow', $article->seoMeta->robots);
        $this->assertSame('article', $article->seoMeta->open_graph['type']);
        $this->assertSame('BlogPosting', $article->seoMeta->json_ld[0]['@type']);
        $this->assertSame('BreadcrumbList', $article->seoMeta->json_ld[1]['@type']);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $article->routePath(),
            'is_included' => true,
        ]);
    }

    public function test_article_title_fills_blank_summary_and_body_without_technical_workflow(): void
    {
        $component = Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'أفكار حفلات منزلية بسيطة']);

        $this->assertStringContainsString(
            'أفكار حفلات منزلية بسيطة',
            (string) $component->get('data.excerpt'),
        );
        $this->assertStringContainsString(
            'أفكار حفلات منزلية بسيطة',
            json_encode($component->get('data.quick_body'), JSON_UNESCAPED_UNICODE) ?: '',
        );
        $component
            ->assertDontSee('الذكاء الاصطناعي');
    }

    public function test_article_gallery_round_trips_and_is_exported_for_the_public_page(): void
    {
        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/article.jpg',
            'webp_path' => 'media/article.webp',
            'original_name' => 'article.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'width' => 1200,
            'height' => 800,
            'sha256' => str_repeat('a', 64),
            'alt' => 'صورة المقال',
            'status' => 'ready',
        ]);
        $stored = app(ArticleFormDataMapper::class)->forStorage([
            'quick_body' => '<p>محتوى المقال</p>',
            'quick_gallery_media_ids' => [$media->id, $media->id],
            'content_blocks' => [],
        ]);

        $article = Article::create([
            'title' => 'مقال بمعرض صور',
            'excerpt' => 'وصف المقال',
            'content_blocks' => $stored['content_blocks'],
            'status' => 'published',
        ]);
        app(ContentPublishingService::class)->sync($article);

        $exported = app(ContentExportService::class)
            ->build()['articles']
            ->firstWhere('id', $article->id);

        $this->assertSame(['text', 'gallery'], array_column($article->content_blocks, 'type'));
        $this->assertSame([$media->id], $article->content_blocks[1]['media_ids']);
        $this->assertSame($media->id, $exported->content_blocks[1]['media'][0]['id']);
    }

    public function test_article_audit_preview_duplication_and_private_preview_are_safe(): void
    {
        $article = Article::create([
            'title' => 'مقال قابل للتكرار',
            'excerpt' => 'مقتطف المقال',
            'content_blocks' => [['type' => 'text', 'body' => '<p>النص الأصلي</p>']],
            'status' => 'published',
        ]);
        app(ContentPublishingService::class)->sync($article);

        $audit = app(ArticleSeoAuditService::class)->audit([
            'title' => 'مقال جديد',
            'excerpt' => 'وصف المقال الجديد.',
            'status' => 'draft',
        ]);
        $duplicate = app(ArticleDuplicationService::class)
            ->duplicate($article, $this->admin->id);

        $this->assertSame('noindex,nofollow', $audit['values']['robots']);
        $this->assertSame('draft', $duplicate->status->value);
        $this->assertNotSame($article->slug, $duplicate->slug);
        $this->assertSame($article->content_blocks, $duplicate->content_blocks);
        $this->get(route('admin.articles.preview', $duplicate))
            ->assertOk()
            ->assertSee('هذه معاينة خاصة')
            ->assertSee('النص الأصلي');
    }

    public function test_categories_share_one_group_and_area_creation_is_prominent(): void
    {
        $this->assertSame('التصنيفات', ArticleCategoryResource::getNavigationGroup());
        $this->assertSame('التصنيفات', ServiceCategoryResource::getNavigationGroup());
        $this->assertSame('المناطق والأماكن', AreaResource::getNavigationGroup());

        Livewire::test(ListAreas::class)
            ->assertSee('إضافة منطقة أو مكان');
    }
}
