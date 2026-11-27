<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class PriceRule extends Model
{
    use LogsActivity;
    use LogsAuditTrail;

    protected $fillable = [
        'name',
        'bancali',
    ];

    protected $casts = [
        'bancali' => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(PriceRuleStep::class)->orderBy('position');
    }

    /**
     * Vale per questo veicolo? bancali nullo = vale per tutti.
     */
    public function appliesToVehicle(Vehicle $vehicle): bool
    {
        return $this->bancali === null || $this->bancali === $vehicle->bancali;
    }

    /**
     * Confronta la sequenza di diciture (goods_type_id) fatte dal veicolo
     * nella giornata con la sequenza della regola: se i primi N elementi
     * coincidono (N = lunghezza della regola fino a $position), la
     * regola e' valida per quella posizione.
     *
     * @param  array<int, int>  $actualSequence  goods_type_id in ordine cronologico
     */
    public function matchesAtPosition(array $actualSequence, int $position): bool
    {
        $ruleSequence = $this->steps->pluck('goods_type_id')->values()->all();

        if ($position > count($ruleSequence) || $position > count($actualSequence)) {
            return false;
        }

        for ($i = 0; $i < $position; $i++) {
            if ($actualSequence[$i] !== $ruleSequence[$i]) {
                return false;
            }
        }

        return true;
    }
}
