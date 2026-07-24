<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\User;
use App\Services\ContentPublishingService;
use App\Services\SlugRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
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
}
