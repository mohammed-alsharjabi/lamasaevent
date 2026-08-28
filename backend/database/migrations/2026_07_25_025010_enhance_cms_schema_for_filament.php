<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->string('group', 80)->index();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable()->index();
        });

        Schema::create('article_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('article_category_id')
                ->nullable()
                ->constrained('article_categories')
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->json('internal_keywords')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->boolean('whatsapp_enabled')->default(true);
        });

        Schema::table('service_categories', function (Blueprint $table): void {
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('areas', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
        });

        Schema::table('galleries', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
        });

        Schema::create('service_area', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_id', 'area_id']);
        });

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('faqable');
            $table->text('question');
            $table->longText('answer');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['gallery_id', 'media_id']);
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('location', 80)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 2048);
            $table->boolean('is_external')->default(false);
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['revisionable_type', 'revisionable_id', 'revision'],
                'content_revisions_unique',
            );
        });

        Schema::create('publish_jobs', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('publishable');
            $table->string('action', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('snapshot_path')->nullable();
            $table->string('build_version')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 40)->default('general')->index();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('site_settings')->orderBy('id')->each(function (object $setting): void {
            DB::table('settings')->insert([
                'key' => $setting->key,
                'value' => $setting->value,
                'group' => $setting->group,
                'is_public' => $setting->is_public,
                'is_sensitive' => false,
                'created_at' => $setting->created_at,
                'updated_at' => $setting->updated_at,
            ]);
        });

        Schema::rename('activity_logs', 'audit_logs');

        Schema::table('redirects', function (Blueprint $table): void {
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable()->index();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->string('avif_path')->nullable()->unique();
            $table->json('variants')->nullable();
            $table->string('title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['avif_path', 'variants', 'title']);
        });

        Schema::table('redirects', function (Blueprint $table): void {
            $table->dropColumn(['hit_count', 'last_used_at']);
        });

        Schema::rename('audit_logs', 'activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('publish_jobs');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('service_area');

        Schema::table('galleries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['is_featured', 'sort_order']);
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hero_media_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['is_featured', 'sort_order']);
        });

        Schema::table('areas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('is_active');
        });

        Schema::table('service_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hero_media_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('is_active');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'is_featured', 'cta_label', 'cta_url', 'whatsapp_enabled',
            ]);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('article_category_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['is_featured', 'internal_keywords', 'sort_order']);
        });

        Schema::dropIfExists('article_categories');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'is_active', 'last_login_at', 'failed_login_count', 'locked_until',
            ]);
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
