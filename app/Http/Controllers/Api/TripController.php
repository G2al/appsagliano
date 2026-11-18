<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filiale;
use App\Models\PlatformExtra;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripPriceCalculator;
use App\Support\BuildsMonthlyTripSchedule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    use BuildsMonthlyTripSchedule;

    /**
     * Scheda bordero mensile dell'utente autenticato (o, solo per admin, di
     * un altro utente via ?user_id=): giorni del mese con record, totale
     * giorno, totale mese e totali per filiale. Stessa logica usata lato
     * Filament.
     */
    public function schedule(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
        ], [
            'month.regex' => 'Il formato del mese deve essere YYYY-MM.',
        ]);

        $targetUserId = $request->query('user_id');

        if ($targetUserId && (int) $targetUserId !== $user->id) {
            if ($user->role !== 'admin') {
                abort(403, 'Non puoi vedere la scheda bordero di un altro utente.');
            }

            $owner = User::query()->findOrFail($targetUserId);
        } else {
            $owner = $user;
        }

        $month = $request->query('month') ?: now()->format('Y-m');

        $schedule = $this->buildMonthlyScheduleFor($owner, $month);

        $allTrips = EloquentCollection::make(
            collect($schedule['days'])->pluck('trips')->collapse()->all()
        );
        $allTrips->loadMissing('attachments');

        $isAdmin = $user->role === 'admin';

        $allTrips->makeHidden('owner_price');

        if (! $isAdmin) {
            $allTrips->makeHidden('price');
        }

        $days = collect($schedule['days'])->map(fn (array $day) => [
            'day' => $day['day'],
            'date' => $day['date']->toDateString(),
            'total' => $isAdmin ? round($day['total'], 2) : null,
            'trips' => $day['trips']->values(),
        ])->values();

        return response()->json([
            'month' => $month,
            'total_trips' => $schedule['totalTrips'],
            'month_total' => $isAdmin ? round($days->sum('total'), 2) : null,
            'filiale_totals' => $isAdmin ? $schedule['filialeTotals'] : null,
            'days' => $days,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Trip::with(['filiale.platform', 'filiale.goodsType', 'vehicle', 'user', 'attachments'])
            ->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))
            ->latest('date')
            ->latest('id');

        $perPage = $request->query('per_page');

        if ($perPage === 'all') {
            $trips = $query->get();

            $trips->makeHidden('owner_price');

            if ($user->role !== 'admin') {
                $trips->makeHidden('price');
            }

            return response()->json($trips);
        }

        $perPageValue = (int) $perPage;

        $paginated = $query->paginate($perPageValue > 0 ? $perPageValue : 20);

        $paginated->getCollection()->makeHidden('owner_price');

        if ($user->role !== 'admin') {
            $paginated->getCollection()->makeHidden('price');
        }

        return response()->json($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['worker', 'admin'], true)) {
            throw ValidationException::withMessages([
                'role' => ['Ruolo non autorizzato a creare record bordero.'],
            ]);
        }

        $validated = $this->validatePayload($request);
        $extraIds = $validated['extra_ids'] ?? [];
        unset($validated['extra_ids']);

        $date = Carbon::parse($validated['date']);
        $filiale = Filiale::query()->with(['prices', 'goodsType.dischargeBonuses'])->findOrFail($validated['filiale_id']);
        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        $this->assertExtrasBelongToPlatform($extraIds, $filiale->platform_id);

        $calculator = app(TripPriceCalculator::class);
        $tripOrder = $calculator->resolveTripOrder($user->id, $date);
        $scarichi = (int) ($validated['scarichi'] ?? 0);

        $extras = PlatformExtra::query()->whereIn('id', $extraIds)->get();

        $vehicleDayPosition = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $priorSequence = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $ruleOverride = $calculator->resolveRuleOverride($vehicle, $filiale, $vehicleDayPosition, $priorSequence);

        $basePrice = $ruleOverride['price'] ?? $calculator->resolvePrice($filiale, $tripOrder);
        $ownerPrice = $ruleOverride['owner_price'] ?? $calculator->resolveOwnerPrice($filiale, $tripOrder, $scarichi);

        $trip = Trip::create([
            'user_id' => $user->id,
            ...$validated,
            'trip_order' => $tripOrder,
            'price' => $basePrice !== null ? round($basePrice + $calculator->resolveExtrasAmount($extras), 2) : null,
            'owner_price' => $ownerPrice,
        ]);

        if ($extras->isNotEmpty()) {
            $trip->extras()->sync($extras->pluck('id'));
        }

        foreach ($this->collectAttachmentFiles($request) as $file) {
            $trip->attachments()->create([
                'path' => $file->store('trips', 'public'),
            ]);
        }

        $trip->load(['filiale.platform', 'filiale.goodsType', 'vehicle', 'user', 'attachments', 'extras']);
        $trip->makeHidden('owner_price');

        if ($user->role !== 'admin') {
            $trip->makeHidden('price');
        }

        return response()->json($trip, 201);
    }

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        if (! $isAdmin && (int) $trip->user_id !== (int) $user->id) {
            abort(403, 'Non puoi modificare un record di un altro utente.');
        }

        if (! $isAdmin && $trip->is_certified) {
            abort(403, 'Il record e gia stato pagato e non puo piu essere modificato.');
        }

        $validated = $this->validatePayload($request);
        $extraIds = $validated['extra_ids'] ?? [];
        unset($validated['extra_ids']);

        $date = Carbon::parse($validated['date']);
        $filiale = Filiale::query()->with(['prices', 'goodsType.dischargeBonuses'])->findOrFail($validated['filiale_id']);
        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        $this->assertExtrasBelongToPlatform($extraIds, $filiale->platform_id);

        $calculator = app(TripPriceCalculator::class);
        $tripOrder = $calculator->resolveTripOrder($trip->user_id, $date, excludeTripId: $trip->id);
        $scarichi = (int) ($validated['scarichi'] ?? 0);

        $extras = PlatformExtra::query()->whereIn('id', $extraIds)->get();

        $vehicleDayPosition = $calculator->resolveVehicleDayPosition($vehicle->id, $date, excludeTripId: $trip->id);
        $priorSequence = $calculator->resolveVehicleDaySequence($vehicle->id, $date, excludeTripId: $trip->id);
        $ruleOverride = $calculator->resolveRuleOverride($vehicle, $filiale, $vehicleDayPosition, $priorSequence);

        $basePrice = $ruleOverride['price'] ?? $calculator->resolvePrice($filiale, $tripOrder);
        $ownerPrice = $ruleOverride['owner_price'] ?? $calculator->resolveOwnerPrice($filiale, $tripOrder, $scarichi);

        $trip->update([
            ...$validated,
            'trip_order' => $tripOrder,
            'price' => $basePrice !== null ? round($basePrice + $calculator->resolveExtrasAmount($extras), 2) : null,
            'owner_price' => $ownerPrice,
        ]);

        $trip->extras()->sync($extras->pluck('id'));

        $removeIds = collect($request->input('remove_attachment_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        if ($removeIds->isNotEmpty()) {
            $trip->attachments()
                ->whereIn('id', $removeIds)
                ->get()
                ->each(function ($attachment) {
                    Storage::disk('public')->delete($attachment->path);
                    $attachment->delete();
                });
        }

        foreach ($this->collectAttachmentFiles($request) as $file) {
            $trip->attachments()->create([
                'path' => $file->store('trips', 'public'),
            ]);
        }

        $trip->refresh()->load(['filiale.platform', 'filiale.goodsType', 'vehicle', 'user', 'attachments', 'extras']);
        $trip->makeHidden('owner_price');

        if (! $isAdmin) {
            $trip->makeHidden('price');
        }

        return response()->json($trip);
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function collectAttachmentFiles(Request $request): array
    {
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];

        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }

        return array_values(array_filter($files));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'filiale_id' => ['required', Rule::exists('filiali', 'id')],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'scarichi' => ['nullable', 'integer', 'min:0'],
            'delivery_note_number' => ['required', 'regex:/^[0-9-]{1,30}$/'],
            'attachment' => ['nullable', 'file', 'max:16384'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:16384'],
            'extra_ids' => ['nullable', 'array'],
            'extra_ids.*' => ['integer', Rule::exists('platform_extras', 'id')],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'exists' => 'Il campo :attribute non esiste.',
            'delivery_note_number.regex' => 'Il campo numero bordero puo contenere solo numeri e trattini.',
            'attachment.file' => "L'allegato deve essere un file valido.",
            'attachment.max' => "L'allegato non puo superare 16MB.",
            'attachments.*.file' => 'Ogni allegato deve essere un file valido.',
            'attachments.*.max' => 'Ogni allegato non puo superare 16MB.',
        ], [
            'date' => 'data',
            'filiale_id' => 'filiale',
            'vehicle_id' => 'veicolo',
            'scarichi' => 'scarichi',
            'delivery_note_number' => 'numero bordero',
            'attachment' => 'allegato',
            'attachments' => 'allegati',
            'extra_ids' => 'diciture extra',
        ]);

        $validated['scarichi'] = $validated['scarichi'] ?? 0;

        unset($validated['attachment'], $validated['attachments']);

        return $validated;
    }

    /**
     * @param  array<int, int>  $extraIds
     */
    private function assertExtrasBelongToPlatform(array $extraIds, int $platformId): void
    {
        if (empty($extraIds)) {
            return;
        }

        $validCount = PlatformExtra::query()
            ->whereIn('id', $extraIds)
            ->where('platform_id', $platformId)
            ->count();

        if ($validCount !== count(array_unique($extraIds))) {
            throw ValidationException::withMessages([
                'extra_ids' => ['Una o piu diciture extra non appartengono alla piattaforma della filiale scelta.'],
            ]);
        }
    }
}
