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
            $table->json('seo_overrides')->nullable()->after('whatsapp_enabled');
            $table->json('cta_overrides')->nullable()->after('seo_overrides');
            $table->boolean('uses_generated_defaults')
                ->default(false)
                ->after('cta_overrides')
                ->index();
            $table->index(
                ['service_category_id', 'sort_order'],
                'services_category_sort_index',
            );
        });

        // Preserve every value already visible on CMS-created services as an
        // explicit override before enabling generated defaults. This makes the
        // migration content-safe: nothing changes until the editor deliberately
        // presses "return to automatic" on a field.
        DB::table('services')
            ->whereNull('legacy_path')
            ->orderBy('id')
            ->eachById(function (object $service): void {
                $seo = DB::table('seo_meta')
                    ->where('seoable_type', 'App\\Models\\Service')
                    ->where('seoable_id', $service->id)
                    ->first();
                $seoOverrides = [];

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
                            $seoOverrides[$field] = $value;
                        }
                    }
                }

                $ctaOverrides = [];

                if (filled($service->cta_label ?? null)) {
                    $ctaOverrides['label'] = $service->cta_label;
                }

                if (filled($service->cta_url ?? null)) {
                    $ctaOverrides['url'] = $service->cta_url;
                }

                $ctaOverrides['mode'] = ($service->whatsapp_enabled ?? true)
                    ? 'show'
                    : 'hide';

                DB::table('services')->where('id', $service->id)->update([
                    'seo_overrides' => $seoOverrides === []
                        ? null
                        : json_encode(
                            $seoOverrides,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ),
                    'cta_overrides' => json_encode(
                        $ctaOverrides,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                    'uses_generated_defaults' => true,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_category_sort_index');
            $table->dropIndex('services_uses_generated_defaults_index');
            $table->dropColumn([
                'seo_overrides',
                'cta_overrides',
                'uses_generated_defaults',
            ]);
        });
    }
};
