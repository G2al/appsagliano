<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Trip extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use LogsAuditTrail;

    protected $fillable = [
        'user_id',
        'filiale_id',
        'vehicle_id',
        'date',
        'trip_order',
        'scarichi',
        'delivery_note_number',
        'price',
        'owner_price',
        'reviewed_at',
        'reviewed_by',
        'export_locked_by',
        'export_locked_at',
    ];

    protected $casts = [
        'date' => 'datetime',
        'price' => 'decimal:2',
        'owner_price' => 'decimal:2',
        'scarichi' => 'integer',
        'trip_order' => 'integer',
        'reviewed_at' => 'datetime',
        'export_locked_at' => 'datetime',
    ];

    protected $appends = [
        'is_certified',
        'is_reviewed',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filiale(): BelongsTo
    {
        return $this->belongsTo(Filiale::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TripAttachment::class)->orderBy('id');
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(PlatformExtra::class, 'trip_platform_extra');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function exportLocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'export_locked_by');
    }

    /**
     * True se QUESTO utente ha esportato e quindi "bloccato" il record per
     * se stesso: non puo' piu' toccarlo, ma per l'admin resta modificabile.
     */
    public function isExportLockedFor(User $user): bool
    {
        return $this->export_locked_by !== null && (int) $this->export_locked_by === (int) $user->id;
    }

    public function getIsCertifiedAttribute(): bool
    {
        return $this->price !== null;
    }

    public function getIsReviewedAttribute(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * Nome della dicitura (secco/freschi/...), derivata dalla filiale
     * selezionata (non e' piu' un campo diretto su Trip).
     */
    public function getGoodsTypeLabelAttribute(): string
    {
        return $this->filiale?->goodsType?->name ?? 'N/D';
    }

    /**
     * Elenco di TUTTE le voci che compongono il guadagno camion oltre al
     * prezzo base del listino: supplemento scarichi + extra automatici
     * (serale/festivo). Serve per mostrare il dettaglio trasparente di
     * ogni componente, non solo il totale.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, amount: float}>
     */
    public function extrasBreakdown(): \Illuminate\Support\Collection
    {
        $items = collect();

        $dischargeBonus = $this->filiale?->goodsType?->dischargeBonusForScarichi((int) $this->scarichi);

        if ($dischargeBonus !== null && $dischargeBonus > 0) {
            $items->push(['label' => 'Supplemento scarichi', 'amount' => $dischargeBonus]);
        }

        foreach ($this->extras as $extra) {
            $items->push(['label' => $extra->name, 'amount' => (float) $extra->amount]);
        }

        return $items;
    }

    /**
     * Scomposizione COMPLETA del guadagno camion: prezzo base (listino
     * filiale o regola prezzo, qualunque dei due abbia determinato il
     * valore) + ogni componente extra, cosi' si capisce sempre il perche'
     * di un numero (es. "85 base + 15 scarichi + 20 extra serale = 120").
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, amount: float}>
     */
    public function ownerPriceBreakdown(): \Illuminate\Support\Collection
    {
        if ($this->owner_price === null) {
            return collect();
        }

        $extras = $this->extrasBreakdown();
        $base = round((float) $this->owner_price - (float) $extras->sum('amount'), 2);

        return collect([['label' => 'Prezzo base', 'amount' => $base]])->merge($extras);
    }

    /**
     * Stessa scomposizione in formato testo pronto da mostrare, es.
     * "Base €85,00 + Supplemento scarichi €15,00 + Extra serale €20,00 = €120,00".
     */
    public function ownerPriceExplanation(): ?string
    {
        if ($this->owner_price === null) {
            return null;
        }

        $parts = $this->ownerPriceBreakdown()->map(
            fn (array $item) => $item['label'] . ' €' . number_format($item['amount'], 2, ',', '.')
        )->implode(' + ');

        return $parts . ' = €' . number_format((float) $this->owner_price, 2, ',', '.');
    }
}
