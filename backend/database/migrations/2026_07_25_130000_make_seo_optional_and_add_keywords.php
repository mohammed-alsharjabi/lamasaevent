<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->string('title')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->string('canonical')->nullable()->change();
            $table->json('keywords')->nullable()->after('robots');
        });
    }

    public function down(): void
    {
        DB::table('seo_meta')->whereNull('title')->update(['title' => '']);
        DB::table('seo_meta')->whereNull('description')->update(['description' => '']);

        DB::table('seo_meta')
            ->whereNull('canonical')
            ->orderBy('id')
            ->eachById(function (object $seo): void {
                DB::table('seo_meta')
                    ->where('id', $seo->id)
                    ->update(['canonical' => "urn:seo-meta:{$seo->id}"]);
            });

        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->dropColumn('keywords');
            $table->string('title')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->string('canonical')->nullable(false)->change();
        });
    }
};
