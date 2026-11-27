<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_view_the_audit_log(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $this->actingAs($worker);

        $this->assertFalse(\App\Filament\Resources\AuditLogResource::canViewAny());

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->actingAs($admin);

        $this->assertTrue(\App\Filament\Resources\AuditLogResource::canViewAny());
    }

    public function test_audit_log_page_renders_and_shows_a_logged_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'phone' => '111']);
        $worker->update(['phone' => '222']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListAuditLogs::class)
            ->assertSuccessful()
            ->assertSee('User');
    }
}
