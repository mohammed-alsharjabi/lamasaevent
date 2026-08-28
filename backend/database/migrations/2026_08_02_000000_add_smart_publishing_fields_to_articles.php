<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->json('seo_overrides')->nullable()->after('sort_order');
            $table->boolean('uses_generated_defaults')->default(false)->after('seo_overrides')->index();
            $table->string('seo_title_override', 70)->nullable()->after('uses_generated_defaults');
            $table->text('meta_description_override')->nullable()->after('seo_title_override');
            $table->string('slug_override', 180)->nullable()->after('meta_description_override')->index();
            $table->string('canonical_override', 2048)->nullable()->after('slug_override');
            $table->string('robots_override', 80)->nullable()->after('canonical_override');
            $table->string('og_title_override', 100)->nullable()->after('robots_override');
            $table->text('og_description_override')->nullable()->after('og_title_override');
            $table->string('og_image_override', 2048)->nullable()->after('og_description_override');
            $table->json('schema_override')->nullable()->after('og_image_override');
            $table->string('target_search_phrase', 160)->nullable()->after('schema_override');
            $table->string('hero_alt_override', 255)->nullable()->after('target_search_phrase');
            $table->index(
                ['article_category_id', 'sort_order'],
                'articles_category_sort_index',
            );
        });

        // Existing CMS articles keep every visible SEO value as an explicit
        // override. Restored legacy pages remain untouched and immutable.
        DB::table('articles')
            ->whereNull('legacy_path')
            ->orderBy('id')
            ->eachById(function (object $article): void {
                $seo = DB::table('seo_meta')
                    ->where('seoable_type', 'App\\Models\\Article')
                    ->where('seoable_id', $article->id)
                    ->first();
                $overrides = [];

                if ($seo) {
                    foreach ([
                        'title', 'description', 'canonical', 'robots',
                        'keywords', 'open_graph', 'twitter', 'hreflang', 'json_ld',
                    ] as $field) {
                        $value = $seo->{$field} ?? null;

                        if (in_array($field, [
                            'keywords', 'open_graph', 'twitter', 'hreflang', 'json_ld',
                        ], true) && is_string($value)) {
                            $decoded = json_decode($value, true);
                            $value = is_array($decoded) ? $decoded : null;
                        }

                        if ($value !== null && $value !== '' && $value !== []) {
                            $overrides[$field] = $value;
                        }
                    }
                }

                $openGraph = is_array($overrides['open_graph'] ?? null)
                    ? $overrides['open_graph']
                    : [];
                $schema = $overrides['json_ld'] ?? null;

                DB::table('articles')->where('id', $article->id)->update([
                    'seo_overrides' => $overrides === []
                        ? null
                        : json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'uses_generated_defaults' => true,
                    'seo_title_override' => self::stringValue($overrides['title'] ?? null),
                    'meta_description_override' => self::stringValue($overrides['description'] ?? null),
                    'canonical_override' => self::stringValue($overrides['canonical'] ?? null),
                    'robots_override' => self::stringValue($overrides['robots'] ?? null),
                    'og_title_override' => self::stringValue($openGraph['title'] ?? null),
                    'og_description_override' => self::stringValue($openGraph['description'] ?? null),
                    'og_image_override' => self::stringValue($openGraph['image'] ?? null),
                    'schema_override' => is_array($schema) && $schema !== []
                        ? json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : null,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('articles_category_sort_index');
            $table->dropIndex('articles_uses_generated_defaults_index');
            $table->dropIndex('articles_slug_override_index');
            $table->dropColumn([
                'seo_overrides',
                'uses_generated_defaults',
                'seo_title_override',
                'meta_description_override',
                'slug_override',
                'canonical_override',
                'robots_override',
                'og_title_override',
                'og_description_override',
                'og_image_override',
                'schema_override',
                'target_search_phrase',
                'hero_alt_override',
            ]);
        });
    }

    private static function stringValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
};
