<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path')->unique();
            $table->string('webp_path')->nullable()->unique();
            $table->string('source_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('sha256', 64)->index();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->string('status', 30)->default('ready');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('legacy_path')->nullable()->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('legacy_path')->nullable()->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('topic')->nullable()->index();
            $table->text('excerpt')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('legacy_path')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('legacy_path')->nullable()->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->string('path')->unique();
            $table->text('summary')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('legacy_path')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gallery_media', function (Blueprint $table) {
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['gallery_id', 'media_id']);
        });

        Schema::create('mediaables', function (Blueprint $table) {
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->morphs('mediaable');
            $table->string('role', 40)->default('gallery');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(
                ['media_id', 'mediaable_id', 'mediaable_type', 'role'],
                'mediaables_unique',
            );
        });

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('title');
            $table->text('description');
            $table->string('canonical')->unique();
            $table->string('robots')->nullable();
            $table->json('open_graph')->nullable();
            $table->json('twitter')->nullable();
            $table->json('hreflang')->nullable();
            $table->json('json_ld')->nullable();
            $table->string('json_ld_sha256', 64)->nullable();
            $table->timestamps();
            $table->unique(['seoable_id', 'seoable_type']);
        });

        Schema::create('route_registry', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('exact_url')->unique();
            $table->nullableMorphs('routable');
            $table->boolean('is_legacy')->default(false)->index();
            $table->boolean('slug_locked')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->string('legacy_html_sha256', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sitemap_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_registry_id')->nullable()->constrained('route_registry')->nullOnDelete();
            $table->string('loc')->unique();
            $table->string('path')->unique();
            $table->date('lastmod')->nullable();
            $table->string('changefreq', 20)->nullable();
            $table->decimal('priority', 3, 2)->nullable();
            $table->unsignedInteger('position');
            $table->boolean('is_included')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('phone_display')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 40)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('subject');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('contact_settings');
        Schema::dropIfExists('sitemap_entries');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('route_registry');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('mediaables');
        Schema::dropIfExists('gallery_media');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('media');
    }
};
