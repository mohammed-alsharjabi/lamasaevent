<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->boolean('is_legacy')->default(false)->after('slug')->index();
        });

        DB::table('galleries')
            ->where('slug', 'main')
            ->update(['is_legacy' => true]);
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->dropIndex(['is_legacy']);
            $table->dropColumn('is_legacy');
        });
    }
};
