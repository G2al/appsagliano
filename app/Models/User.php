<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\UserDocumentFolderTemplateSyncService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Movement;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const PANEL_MODULE_MAINTENANCE = 'maintenance';
    public const PANEL_MODULE_REFUELS = 'refuels';
    public const PANEL_MODULE_DOCUMENTS = 'documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'password',
        'role',
        'panel_modules',
        'is_approved',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'full_name',
    ];

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            app(UserDocumentFolderTemplateSyncService::class)->syncForUser($user);

            if ($user->role === 'worker') {
                $user->notifyTelegramSignup();
            }
        });

        static::updated(function (self $user): void {
            if ($user->isDirty('is_approved') && $user->is_approved && $user->role === 'worker') {
                $user->notifyTelegramApproval();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'panel_modules' => 'array',
            'is_approved' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->is_approved) {
            return false;
        }

        return $this->hasAnyPanelModules();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return array<int, string>
     */
    public static function allowedPanelModules(): array
    {
        return [
            self::PANEL_MODULE_MAINTENANCE,
            self::PANEL_MODULE_REFUELS,
            self::PANEL_MODULE_DOCUMENTS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function panelModuleOptions(): array
    {
        return [
            self::PANEL_MODULE_MAINTENANCE => 'Manutenzione',
            self::PANEL_MODULE_REFUELS => 'Rifornimenti',
            self::PANEL_MODULE_DOCUMENTS => 'Documenti',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getSanitizedPanelModules(): array
    {
        $raw = $this->panel_modules;
        if (! is_array($raw)) {
            return [];
        }

        $allowed = self::allowedPanelModules();
        $sanitized = array_values(array_unique(array_map('strval', $raw)));

        return array_values(array_intersect($allowed, $sanitized));
    }

    public function hasPanelModule(string $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($module, $this->getSanitizedPanelModules(), true);
    }

    /**
     * @param  array<int, string>|null  $modules
     */
    public function hasAnyPanelModules(?array $modules = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $ownedModules = $this->getSanitizedPanelModules();
        if (empty($ownedModules)) {
            return false;
        }

        $modulesToCheck = $modules ?: self::allowedPanelModules();

        return ! empty(array_intersect($ownedModules, $modulesToCheck));
    }

    public function canAccessMaintenanceArea(): bool
    {
        return $this->hasPanelModule(self::PANEL_MODULE_MAINTENANCE);
    }

    public function canAccessRefuelsArea(): bool
    {
        return $this->hasPanelModule(self::PANEL_MODULE_REFUELS);
    }

    public function canAccessDocumentsArea(): bool
    {
        return $this->hasPanelModule(self::PANEL_MODULE_DOCUMENTS);
    }

    public function canAccessUsersArea(): bool
    {
        return $this->hasAnyPanelModules([
            self::PANEL_MODULE_MAINTENANCE,
            self::PANEL_MODULE_REFUELS,
            self::PANEL_MODULE_DOCUMENTS,
        ]);
    }

    public function canAccessVehiclesArea(): bool
    {
        return $this->hasAnyPanelModules([
            self::PANEL_MODULE_MAINTENANCE,
            self::PANEL_MODULE_REFUELS,
        ]);
    }

    public function canManageUserDocuments(): bool
    {
        return $this->canAccessDocumentsArea();
    }

    public function setPanelModulesAttribute($value): void
    {
        $values = is_array($value) ? $value : (is_null($value) ? [] : [$value]);
        $values = array_values(array_unique(array_map('strval', $values)));
        $allowed = self::allowedPanelModules();
        $sanitized = array_values(array_intersect($allowed, $values));

        $this->attributes['panel_modules'] = empty($sanitized)
            ? null
            : json_encode($sanitized, JSON_UNESCAPED_UNICODE);
    }

    public function getFullNameAttribute(): string
    {
        $fullName = trim(($this->name ?? '') . ' ' . ($this->surname ?? ''));
        return $fullName !== '' ? $fullName : ($this->name ?? '');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function documentFolders(): HasMany
    {
        return $this->hasMany(UserDocumentFolder::class)->latest('id');
    }

    public function notifyTelegramSignup(): void
    {
        $token = env('TELEGRAM_SIGNUP_BOT_TOKEN');
        $chatId = env('TELEGRAM_SIGNUP_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }

        $roleLabel = $this->role === 'worker' ? 'Operaio' : ($this->role ?? 'N/D');

        $lines = [];
        $lines[] = '🆕 <b>Nuova registrazione</b>';
        $lines[] = '👤 <b>Nome:</b> ' . ($this->full_name ?: 'N/D');
        $lines[] = '📞 <b>Telefono:</b> ' . ($this->phone ?: 'N/D');
        $lines[] = '🛠️ <b>Ruolo:</b> ' . $roleLabel;
        $lines[] = '⏳ <b>Stato:</b> in attesa di approvazione';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare in caso di errore Telegram
        }
    }

    public function notifyTelegramApproval(): void
    {
        $token = env('TELEGRAM_SIGNUP_BOT_TOKEN');
        $chatId = env('TELEGRAM_SIGNUP_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }

        $roleLabel = $this->role === 'worker' ? 'Operaio' : ($this->role ?? 'N/D');

        $lines = [];
        $lines[] = '✅ <b>Account approvato</b>';
        $lines[] = '👤 <b>Nome:</b> ' . ($this->full_name ?: 'N/D');
        $lines[] = '📞 <b>Telefono:</b> ' . ($this->phone ?: 'N/D');
        $lines[] = '🛠️ <b>Ruolo:</b> ' . $roleLabel;
        $lines[] = '📌 <b>Stato:</b> approvato dall\'admin';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare in caso di errore Telegram
        }
    }
}
