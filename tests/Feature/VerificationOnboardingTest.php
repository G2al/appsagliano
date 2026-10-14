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
        // Il marcatore self_registered (non piu' must_change_password) e'
        // cio' che decide se il login resta bloccato in attesa di un admin.
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => false,
            'self_registered' => true,
            'must_change_password' => true,
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'full_name' => $user->name . ' ' . $user->surname,
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_non_self_registered_unapproved_worker_can_log_in_even_without_pending_password_change(): void
    {
        // Caso che prima era un bug: un operaio gestito da Filament con
        // is_approved tolto a mano, ma senza must_change_password mai
        // impostato (es. non e' mai passato dal seeder). Non deve essere
        // bloccato come un self-registrato, perche' non lo e'.
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => false,
            'self_registered' => false,
            'must_change_password' => false,
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'full_name' => $user->name . ' ' . $user->surname,
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_register_creates_a_self_registered_user_with_automatic_password_and_pending_onboarding(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Paolo',
            'surname' => 'Marino',
            'phone' => '+393339998877',
        ]);

        $response->assertCreated();
        $generatedPassword = $response->json('password');
        $this->assertSame('paolomega2026', $generatedPassword);

        $user = User::query()->where('name', 'Paolo')->where('surname', 'Marino')->firstOrFail();

        $this->assertTrue($user->self_registered);
        $this->assertTrue($user->must_change_password);
        $this->assertFalse($user->documents_verified);
        $this->assertFalse($user->is_approved);

        // Bloccato finche' non approvato.
        $this->postJson('/api/auth/login', [
            'full_name' => 'Paolo Marino',
            'password' => $generatedPassword,
        ])->assertStatus(422);

        // Una volta approvato dall'admin, si comporta come un seedato:
        // login riuscito, ma comunque in attesa di onboarding (password + verifica).
        $user->update(['is_approved' => true]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'full_name' => 'Paolo Marino',
            'password' => $generatedPassword,
        ]);

        $loginResponse->assertOk();
        $this->assertTrue($user->fresh()->needsOnboarding());
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

    public function test_fully_onboarded_users_have_full_access(): void
    {
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'must_change_password' => false,
            'documents_verified' => true,
        ]);

        $this->assertFalse($user->needsOnboarding());

        Sanctum::actingAs($user);

        $this->getJson('/api/trips')->assertOk();
    }

    public function test_approved_user_without_documents_verified_is_still_gated(): void
    {
        // Nessuna eccezione per nessuno: anche un utente gia' approvato (es.
        // seedato da Excel) resta bloccato finche' non completa la verifica
        // documenti, non solo il cambio password.
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'must_change_password' => false,
            'documents_verified' => false,
        ]);

        $this->assertTrue($user->needsOnboarding());

        Sanctum::actingAs($user);

        $this->getJson('/api/trips')
            ->assertStatus(403)
            ->assertJson(['onboarding_required' => true]);
    }

    /**
     * @return array<string, mixed> payload multipart con tutti i documenti completi
     */
    private function fullVerificationPayload(): array
    {
        return [
            'identity_front' => UploadedFile::fake()->image('identity_front.jpg'),
            'identity_back' => UploadedFile::fake()->image('identity_back.jpg'),
            'identity_expiry_date' => '2030-01-01',
            'license' => UploadedFile::fake()->image('license_front.jpg'),
            'license_back' => UploadedFile::fake()->image('license_back.jpg'),
            'license_expiry_date' => '2028-05-01',
            'cqc' => UploadedFile::fake()->image('cqc_front.jpg'),
            'cqc_back' => UploadedFile::fake()->image('cqc_back.jpg'),
            'cqc_expiry_date' => '2027-03-15',
            'tachograph_front' => UploadedFile::fake()->image('tacho_front.jpg'),
            'tachograph_back' => UploadedFile::fake()->image('tacho_back.jpg'),
            'tachograph_expiry_date' => '2029-07-20',
        ];
    }

    public function test_submit_never_auto_approves_even_when_everything_is_complete(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', ['address' => 'Via Roma 1, Bari'])->assertOk();

        $response = $this->post('/api/verification', $this->fullVerificationPayload());

        $response->assertOk();
        $response->assertJsonPath('is_approved', false);

        $this->assertFalse($user->fresh()->is_approved);
        $this->assertNull($user->fresh()->verification->completed_at);
    }

    public function test_confirm_approves_the_user_when_everything_is_complete(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', ['address' => 'Via Roma 1, Bari'])->assertOk();
        $this->post('/api/verification', $this->fullVerificationPayload())->assertOk();

        $response = $this->postJson('/api/verification/confirm');

        $response->assertOk();
        $response->assertJsonPath('is_approved', true);
        $response->assertJsonPath('documents_verified', true);

        $this->assertTrue($user->fresh()->is_approved);
        $this->assertTrue($user->fresh()->documents_verified);
        $this->assertNotNull($user->fresh()->verification->completed_at);
    }

    public function test_confirm_rejects_with_missing_fields_when_incomplete(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->postJson('/api/verification', ['address' => 'Via Roma 1, Bari'])->assertOk();
        $this->post('/api/verification', [
            'identity_front' => UploadedFile::fake()->image('front.jpg'),
        ])->assertOk();

        $this->postJson('/api/verification/confirm')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);

        $this->assertFalse($user->fresh()->is_approved);
    }

    public function test_confirm_requires_authentication(): void
    {
        $this->postJson('/api/verification/confirm')->assertUnauthorized();
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

    public function test_license_expiry_date_is_rejected_with_only_the_front_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->post('/api/verification', [
            'license' => UploadedFile::fake()->image('license.jpg'),
        ])->assertOk();

        // Manca ancora il retro: la scadenza va rifiutata.
        $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/verification', [
                'license_expiry_date' => '2028-05-01',
            ])->assertStatus(422)->assertJsonValidationErrors(['license_expiry_date']);
    }

    public function test_license_expiry_date_is_accepted_when_both_photos_are_already_on_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->post('/api/verification', [
            'license' => UploadedFile::fake()->image('license_front.jpg'),
            'license_back' => UploadedFile::fake()->image('license_back.jpg'),
        ])->assertOk();

        // Scadenza inviata in una chiamata successiva: le foto sono gia' salvate.
        $this->postJson('/api/verification', [
            'license_expiry_date' => '2028-05-01',
        ])->assertOk()
            ->assertJsonPath('license_expiry_date', '2028-05-01');
    }

    public function test_tachograph_expiry_date_is_rejected_without_both_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/verification', [
                'tachograph_front' => UploadedFile::fake()->image('tacho_front.jpg'),
                'tachograph_expiry_date' => '2029-07-20',
            ])->assertStatus(422)->assertJsonValidationErrors(['tachograph_expiry_date']);
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

    public function test_license_expiry_date_is_accepted_when_both_photos_are_uploaded_in_the_same_request(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => false]);

        Sanctum::actingAs($user);

        $this->post('/api/verification', [
            'license' => UploadedFile::fake()->image('license_front.jpg'),
            'license_back' => UploadedFile::fake()->image('license_back.jpg'),
            'license_expiry_date' => '2028-05-01',
        ])->assertOk()
            ->assertJsonPath('license_expiry_date', '2028-05-01');
    }
}
