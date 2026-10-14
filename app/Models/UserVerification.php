<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class UserVerification extends Model
{
    protected $fillable = [
        'user_id',
        'identity_front_path',
        'identity_back_path',
        'identity_expiry_date',
        'license_path',
        'license_back_path',
        'license_expiry_date',
        'cqc_path',
        'cqc_back_path',
        'cqc_expiry_date',
        'tachograph_front_path',
        'tachograph_back_path',
        'tachograph_expiry_date',
        'completed_at',
    ];

    protected $casts = [
        'identity_expiry_date' => 'date',
        'license_expiry_date' => 'date',
        'cqc_expiry_date' => 'date',
        'tachograph_expiry_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'identity_front_url',
        'identity_back_url',
        'license_url',
        'license_back_url',
        'cqc_url',
        'cqc_back_url',
        'tachograph_front_url',
        'tachograph_back_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'utente ha caricato/compilato tutto cio' che serve per essere
     * considerato verificato (indirizzo controllato lato User, qui solo i documenti).
     */
    public function hasAllDocuments(): bool
    {
        return filled($this->identity_front_path)
            && filled($this->identity_back_path)
            && filled($this->identity_expiry_date)
            && filled($this->license_path)
            && filled($this->license_back_path)
            && filled($this->license_expiry_date)
            && filled($this->cqc_path)
            && filled($this->cqc_back_path)
            && filled($this->cqc_expiry_date)
            && filled($this->tachograph_front_path)
            && filled($this->tachograph_back_path)
            && filled($this->tachograph_expiry_date);
    }

    /**
     * Elenco leggibile di cosa manca ancora, per restituire un errore chiaro
     * quando l'utente preme "Conferma invio" senza avere tutto pronto.
     *
     * @return array<int, string>
     */
    public function missingDocumentLabels(): array
    {
        $missing = [];

        if (! filled($this->identity_front_path) || ! filled($this->identity_back_path)) {
            $missing[] = 'documento di identita (fronte e retro)';
        }

        if (! filled($this->identity_expiry_date)) {
            $missing[] = 'scadenza documento di identita';
        }

        if (! filled($this->license_path) || ! filled($this->license_back_path)) {
            $missing[] = 'patente (fronte e retro)';
        }

        if (! filled($this->license_expiry_date)) {
            $missing[] = 'scadenza patente';
        }

        if (! filled($this->cqc_path) || ! filled($this->cqc_back_path)) {
            $missing[] = 'CQC (fronte e retro)';
        }

        if (! filled($this->cqc_expiry_date)) {
            $missing[] = 'scadenza CQC';
        }

        if (! filled($this->tachograph_front_path) || ! filled($this->tachograph_back_path)) {
            $missing[] = 'carta tachigrafica (fronte e retro)';
        }

        if (! filled($this->tachograph_expiry_date)) {
            $missing[] = 'scadenza carta tachigrafica';
        }

        return $missing;
    }

    public function getIdentityFrontUrlAttribute(): ?string
    {
        return $this->urlFor($this->identity_front_path);
    }

    public function getIdentityBackUrlAttribute(): ?string
    {
        return $this->urlFor($this->identity_back_path);
    }

    public function getLicenseUrlAttribute(): ?string
    {
        return $this->urlFor($this->license_path);
    }

    public function getLicenseBackUrlAttribute(): ?string
    {
        return $this->urlFor($this->license_back_path);
    }

    public function getCqcUrlAttribute(): ?string
    {
        return $this->urlFor($this->cqc_path);
    }

    public function getCqcBackUrlAttribute(): ?string
    {
        return $this->urlFor($this->cqc_back_path);
    }

    public function getTachographFrontUrlAttribute(): ?string
    {
        return $this->urlFor($this->tachograph_front_path);
    }

    public function getTachographBackUrlAttribute(): ?string
    {
        return $this->urlFor($this->tachograph_back_path);
    }

    private function urlFor(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }
}
