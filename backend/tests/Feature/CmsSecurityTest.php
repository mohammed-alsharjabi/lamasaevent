<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentRevision;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_have_distinct_publish_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $manager = User::factory()->create(['is_active' => true]);
        $editor->roles()->attach(Role::where('slug', 'editor')->sole());
        $manager->roles()->attach(Role::where('slug', 'content-manager')->sole());

        $this->assertTrue($editor->hasPermission('articles.update'));
        $this->assertFalse($editor->hasPermission('articles.publish'));
        $this->assertTrue($manager->hasPermission('articles.publish'));
        $this->assertFalse($manager->hasPermission('users.update'));
    }

    public function test_testing_environment_never_seeds_local_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@lams-event.local',
        ]);
    }

    public function test_redirect_cycles_are_rejected(): void
    {
        Redirect::create([
            'from_path' => '/old-a',
            'to_path' => '/old-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        Redirect::create([
            'from_path' => '/old-b',
            'to_path' => '/old-a',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }

    public function test_content_updates_create_a_revision_snapshot(): void
    {
        $article = Article::create([
            'title' => 'نسخة أولى',
            'slug' => 'first-version',
            'content_blocks' => [],
            'status' => 'draft',
        ]);

        $article->update(['title' => 'نسخة ثانية']);

        $revision = ContentRevision::whereMorphedTo('revisionable', $article)->sole();
        $this->assertSame('نسخة أولى', $revision->snapshot['title']);
        $this->assertSame(1, $revision->revision);
    }

    public function test_public_content_api_excludes_drafts(): void
    {
        Article::create([
            'title' => 'مسودة',
            'slug' => 'draft-only',
            'content_blocks' => [],
            'status' => 'draft',
        ]);
        Article::create([
            'title' => 'منشور',
            'slug' => 'published-only',
            'content_blocks' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/content/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-only');
    }

    public function test_content_export_honors_conditional_etag_requests(): void
    {
        $response = $this->getJson('/api/v1/content-export')->assertOk();
        $etag = $response->headers->get('ETag');

        $this->withHeader('If-None-Match', $etag)
            ->get('/api/v1/content-export')
            ->assertStatus(304);
    }
}
