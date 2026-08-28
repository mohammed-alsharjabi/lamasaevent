<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
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
        });

        // Keep every existing customization. The old JSON column remains as a
        // backwards-compatible developer payload while common overrides become
        // nullable first-class columns that are easier to validate and query.
        DB::table('services')
            ->whereNotNull('seo_overrides')
            ->orderBy('id')
            ->eachById(function (object $service): void {
                $overrides = json_decode((string) $service->seo_overrides, true);

                if (! is_array($overrides)) {
                    return;
                }

                $openGraph = is_array($overrides['open_graph'] ?? null)
                    ? $overrides['open_graph']
                    : [];
                $schema = $overrides['json_ld'] ?? null;

                DB::table('services')->where('id', $service->id)->update([
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
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_slug_override_index');
            $table->dropColumn([
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
