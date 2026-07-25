<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Jobs\GenerateFrontendSnapshot;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(
            Role::where('slug', 'super-admin')->sole(),
        );
        $this->actingAs($this->admin);
    }

    public function test_service_create_update_archive_and_restore_work_through_filament(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'title' => 'خدمة من لوحة التحكم',
                'excerpt' => 'وصف الخدمة',
                'status' => 'published',
                'sort_order' => 5,
                'is_featured' => true,
                'whatsapp_enabled' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()
            ->where('title', 'خدمة من لوحة التحكم')
            ->sole();
        $this->assertSame($this->admin->id, $service->created_by);
        $this->assertSame($this->admin->id, $service->updated_by);
        $this->assertNotNull($service->published_at);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => true,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->fillForm(['title' => 'خدمة محدثة من لوحة التحكم'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'title' => 'خدمة محدثة من لوحة التحكم',
            'updated_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('content_revisions', [
            'revisionable_type' => $service->getMorphClass(),
            'revisionable_id' => $service->id,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => false,
        ]);

        Livewire::test(EditService::class, ['record' => $service->id])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseHas('sitemap_entries', [
            'path' => $service->routePath(),
            'is_included' => true,
        ]);
        Queue::assertPushed(GenerateFrontendSnapshot::class);
    }
}
