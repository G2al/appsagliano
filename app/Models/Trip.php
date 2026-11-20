<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory;
    use SoftDeletes;

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
    ];

    protected $casts = [
        'date' => 'datetime',
        'price' => 'decimal:2',
        'owner_price' => 'decimal:2',
        'scarichi' => 'integer',
        'trip_order' => 'integer',
        'reviewed_at' => 'datetime',
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
}
