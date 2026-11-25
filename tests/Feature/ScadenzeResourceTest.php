<?php

namespace Tests\Feature;

use App\Filament\Resources\ScadenzeResource\Pages\ManageScadenzes;
use App\Models\DocumentExpiry;
use App\Models\User;
use App\Models\UserVerification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScadenzeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_a_document_expiry_date_through_the_modal(): void
    {
        $driver = User::factory()->create(['name' => 'Mario', 'surname' => 'Rossi']);

        $verification = UserVerification::create([
            'user_id' => $driver->id,
            'identity_front_path' => 'x',
            'identity_back_path' => 'x',
            'identity_expiry_date' => now()->subDays(5),
        ]);

        $expiry = DocumentExpiry::query()->where('user_verification_id', $verification->id)->firstOrFail();

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $newDate = now()->addYear()->toDateString();

        Livewire::test(ManageScadenzes::class)
            ->callTableAction('gestisci', $expiry, data: ['expiry_date' => $newDate])
            ->assertSuccessful();

        $this->assertSame($newDate, $verification->fresh()->identity_expiry_date->toDateString());
    }
}
