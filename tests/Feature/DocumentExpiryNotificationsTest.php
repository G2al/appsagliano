<?php

namespace Tests\Feature;

use App\Models\DocumentExpiry;
use App\Models\ExpiryNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentExpiryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_or_maintenance_or_documents_workers_can_receive_expiry_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maintenanceWorker = User::factory()->create(['role' => 'worker', 'panel_modules' => ['maintenance']]);
        $documentsWorker = User::factory()->create(['role' => 'worker', 'panel_modules' => ['documents']]);
        $tripsWorker = User::factory()->create(['role' => 'worker', 'panel_modules' => ['trips']]);
        $noModulesWorker = User::factory()->create(['role' => 'worker', 'panel_modules' => []]);

        $this->assertTrue($admin->canReceiveExpiryNotifications());
        $this->assertTrue($maintenanceWorker->canReceiveExpiryNotifications());
        $this->assertTrue($documentsWorker->canReceiveExpiryNotifications());
        $this->assertFalse($tripsWorker->canReceiveExpiryNotifications());
        $this->assertFalse($noModulesWorker->canReceiveExpiryNotifications());
    }

    public function test_document_expiry_view_exposes_one_row_per_document_with_a_compiled_expiry(): void
    {
        $driver = User::factory()->create();

        UserVerification::create([
            'user_id' => $driver->id,
            'identity_front_path' => 'x', 'identity_back_path' => 'x', 'identity_expiry_date' => now()->addDays(5),
            'license_path' => 'x', 'license_back_path' => 'x', 'license_expiry_date' => null,
            'cqc_path' => 'x', 'cqc_back_path' => 'x', 'cqc_expiry_date' => now()->addDays(60),
            'tachograph_front_path' => 'x', 'tachograph_back_path' => 'x', 'tachograph_expiry_date' => now()->subDay(),
        ]);

        // Solo 3 righe: license_expiry_date e' null quindi non compare nella vista.
        $this->assertSame(3, DocumentExpiry::count());

        $tachograph = DocumentExpiry::where('document_type', 'tachograph')->first();
        $this->assertTrue($tachograph->isExpired());
        $this->assertSame('danger', $tachograph->statusColor());

        $identity = DocumentExpiry::where('document_type', 'identity')->first();
        $this->assertFalse($identity->isExpired());
        $this->assertSame('warning', $identity->statusColor());
    }

    public function test_check_expiry_command_notifies_only_once_per_threshold_and_picks_the_narrowest_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create();

        $verification = UserVerification::create([
            'user_id' => $driver->id,
            'identity_front_path' => 'x', 'identity_back_path' => 'x', 'identity_expiry_date' => now()->addDays(5),
            'license_path' => 'x', 'license_back_path' => 'x', 'license_expiry_date' => now()->addDays(60),
            'cqc_path' => 'x', 'cqc_back_path' => 'x', 'cqc_expiry_date' => now()->addDays(100),
            'tachograph_front_path' => 'x', 'tachograph_back_path' => 'x', 'tachograph_expiry_date' => now()->addDays(25),
        ]);

        $this->artisan('verifications:check-expiry')->assertExitCode(0);

        $this->assertDatabaseHas('expiry_notifications', [
            'user_verification_id' => $verification->id,
            'document_type' => 'identity',
            'threshold_days' => 7,
        ]);

        $this->assertDatabaseHas('expiry_notifications', [
            'user_verification_id' => $verification->id,
            'document_type' => 'tachograph',
            'threshold_days' => 30,
        ]);

        // La CQC (100gg) e la patente (60gg) non devono generare alert.
        $this->assertSame(2, ExpiryNotification::count());

        // Rilanciando il comando non deve duplicare le notifiche gia inviate.
        $this->artisan('verifications:check-expiry')->assertExitCode(0);
        $this->assertSame(2, ExpiryNotification::count());
    }

    public function test_push_subscribe_and_unsubscribe_endpoints_persist_and_remove_the_subscription(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'public-key-value',
            'auth_token' => 'auth-token-value',
            'content_encoding' => 'aes128gcm',
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);

        $this->actingAs($user)->deleteJson('/admin/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/abc',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);
    }

    public function test_subscribing_twice_with_the_same_endpoint_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'old-key',
            'auth_token' => 'old-token',
            'content_encoding' => 'aes128gcm',
        ]);

        $this->actingAs($user)->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'new-key',
            'auth_token' => 'new-token',
        ])->assertOk();

        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'new-key',
        ]);
    }
}
