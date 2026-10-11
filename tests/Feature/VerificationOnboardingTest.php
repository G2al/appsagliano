<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_worker_with_temporary_password_can_log_in(): void
    {
        // Simula un account creato dall'admin con password provvisoria:
        // puo' accedere subito per completare da solo la verifica.
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => false,
            'must_change_password' => true,
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'full_name' => $user->name . ' ' . $user->surname,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('token'));
    }

    public function test_self_registered_unapproved_worker_cannot_log_in(): void
    {
        // Un account nato da /auth/register (must_change_password resta
        // false) deve continuare a richiedere l'approvazione manuale di un
        // admin: niente auto-verifica via documenti per questo percorso.
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => false,
            'must_change_password' => false,
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'full_name' => $user->name . ' ' . $user->surname,
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_unapproved_worker_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->getJson('/api/trips')
            ->assertStatus(403)
            ->assertJson(['onboarding_required' => true]);
    }

    public function test_approved_worker_with_pending_password_change_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/trips')
            ->assertStatus(403)
            ->assertJson(['onboarding_required' => true]);
    }

    public function test_unapproved_worker_can_still_reach_onboarding_routes(): void
    {
        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->getJson('/api/verification/status')->assertOk();
        $this->postJson('/api/auth/change-password', [
            'new_password' => 'newpass1',
            'new_password_confirmation' => 'newpass1',
        ])->assertOk();
        $this->getJson('/api/me')->assertOk();
    }

    public function test_already_approved_users_are_unaffected_even_without_documents(): void
    {
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'must_change_password' => false,
        ]);

        $this->assertFalse($user->needsOnboarding());

        Sanctum::actingAs($user);

        $this->getJson('/api/trips')->assertOk();
    }

    public function test_submitting_all_required_data_auto_approves_the_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', [
            'address' => 'Via Roma 1, Bari',
        ])->assertOk();

        $this->assertFalse($user->fresh()->is_approved);

        $response = $this->post('/api/verification', [
            'identity_front' => UploadedFile::fake()->image('front.jpg'),
            'identity_back' => UploadedFile::fake()->image('back.jpg'),
            'identity_expiry_date' => '2030-01-01',
            'license' => UploadedFile::fake()->image('license.jpg'),
            'license_expiry_date' => '2028-05-01',
            'cqc' => UploadedFile::fake()->image('cqc.jpg'),
            'cqc_expiry_date' => '2027-03-15',
        ]);

        $response->assertOk();
        $response->assertJsonPath('is_approved', true);

        $this->assertTrue($user->fresh()->is_approved);
        $this->assertNotNull($user->fresh()->verification->completed_at);
    }

    public function test_partial_submission_does_not_approve_the_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', [
            'address' => 'Via Roma 1, Bari',
        ])->assertOk();

        $this->post('/api/verification', [
            'identity_front' => UploadedFile::fake()->image('front.jpg'),
        ])->assertOk();

        $this->assertFalse($user->fresh()->is_approved);
    }

    public function test_verification_submission_requires_authentication(): void
    {
        $this->postJson('/api/verification', ['address' => 'Via Roma 1'])
            ->assertUnauthorized();
    }

    public function test_license_expiry_date_is_rejected_without_license_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', [
            'license_expiry_date' => '2028-05-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['license_expiry_date']);
    }

    public function test_cqc_expiry_date_is_rejected_without_cqc_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', [
            'cqc_expiry_date' => '2027-03-15',
        ])->assertStatus(422)->assertJsonValidationErrors(['cqc_expiry_date']);
    }

    public function test_identity_expiry_date_is_rejected_without_both_identity_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        // Solo il fronte, manca il retro.
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/verification', [
                'identity_front' => UploadedFile::fake()->image('front.jpg'),
                'identity_expiry_date' => '2030-01-01',
            ])->assertStatus(422)->assertJsonValidationErrors(['identity_expiry_date']);
    }

    public function test_license_expiry_date_is_accepted_when_photo_is_already_on_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->post('/api/verification', [
            'license' => UploadedFile::fake()->image('license.jpg'),
        ])->assertOk();

        // Scadenza inviata in una chiamata successiva: la foto e' gia' salvata.
        $this->postJson('/api/verification', [
            'license_expiry_date' => '2028-05-01',
        ])->assertOk()
            ->assertJsonPath('license_expiry_date', '2028-05-01');
    }

    public function test_photo_is_saved_even_when_its_expiry_date_is_rejected_in_the_same_request(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        // Fronte + scadenza insieme: la scadenza va rifiutata (manca il
        // retro), ma la foto del fronte deve comunque salvarsi.
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/verification', [
                'identity_front' => UploadedFile::fake()->image('front.jpg'),
                'identity_expiry_date' => '2030-01-01',
            ])->assertStatus(422);

        $user->refresh();
        $this->assertNotNull($user->verification->identity_front_path);
        $this->assertNull($user->verification->identity_expiry_date);

        // Ora basta il retro per sbloccare la scadenza.
        $this->post('/api/verification', [
            'identity_back' => UploadedFile::fake()->image('back.jpg'),
            'identity_expiry_date' => '2030-01-01',
        ])->assertOk()->assertJsonPath('identity_expiry_date', '2030-01-01');
    }

    public function test_license_expiry_date_is_accepted_when_uploaded_in_the_same_request(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->post('/api/verification', [
            'license' => UploadedFile::fake()->image('license.jpg'),
            'license_expiry_date' => '2028-05-01',
        ])->assertOk()
            ->assertJsonPath('license_expiry_date', '2028-05-01');
    }
}
