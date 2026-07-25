<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('contact_settings')->count() > 1) {
            throw new RuntimeException(
                'Contact settings must be consolidated to one row before this migration.',
            );
        }

        $hasDuplicateUploads = DB::table('media')
            ->whereNull('source_path')
            ->select('sha256')
            ->groupBy('sha256')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateUploads) {
            throw new RuntimeException(
                'Duplicate uploaded media must be consolidated before this migration.',
            );
        }

        Schema::table('contact_settings', function (Blueprint $table): void {
            $table->string('singleton_key', 32)
                ->default('primary')
                ->unique();
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->string('upload_fingerprint', 64)
                ->nullable()
                ->after('sha256')
                ->unique();
        });

        DB::table('media')
            ->whereNull('source_path')
            ->orderBy('id')
            ->eachById(function (object $media): void {
                DB::table('media')
                    ->where('id', $media->id)
                    ->update(['upload_fingerprint' => $media->sha256]);
            });

        Schema::table('articles', function (Blueprint $table): void {
            $table->index(
                ['status', 'sort_order', 'published_at'],
                'articles_public_listing_index',
            );
        });

        foreach (['services', 'service_categories', 'areas', 'pages', 'galleries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index(
                    ['status', 'sort_order'],
                    "{$tableName}_public_listing_index",
                );
            });
        }

        Schema::table('sitemap_entries', function (Blueprint $table): void {
            $table->index(
                ['is_included', 'position'],
                'sitemap_entries_public_index',
            );
        });

        Schema::table('route_registry', function (Blueprint $table): void {
            $table->index(
                ['is_published', 'id'],
                'route_registry_public_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('route_registry', function (Blueprint $table): void {
            $table->dropIndex('route_registry_public_index');
        });

        Schema::table('sitemap_entries', function (Blueprint $table): void {
            $table->dropIndex('sitemap_entries_public_index');
        });

        foreach (['services', 'service_categories', 'areas', 'pages', 'galleries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex("{$tableName}_public_listing_index");
            });
        }

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('articles_public_listing_index');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique(['upload_fingerprint']);
            $table->dropColumn('upload_fingerprint');
        });

        Schema::table('contact_settings', function (Blueprint $table): void {
            $table->dropUnique(['singleton_key']);
            $table->dropColumn('singleton_key');
        });
    }
};
