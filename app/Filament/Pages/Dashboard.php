<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StationResource;
use App\Models\Station;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

class Dashboard extends BaseDashboard
{
    private const LOW_CREDIT_THRESHOLD = 5000;

    public function mount(): void
    {
        $this->notifyLowCreditStations();
    }

    private function notifyLowCreditStations(): void
    {
        $this->getStationsBelowThreshold()->each(function (Station $station) {
            $this->sendCreditNotification($station);
        });
    }

    private function getStationsBelowThreshold()
    {
        return Station::query()
            ->whereNotNull('credit_balance')
            ->where('credit_balance', '<=', self::LOW_CREDIT_THRESHOLD)
            ->orderBy('credit_balance')
            ->get();
    }

    private function sendCreditNotification(Station $station): void
    {
        $credit = (float) $station->credit_balance;

        Notification::make()
            ->title($this->getNotificationTitle($credit))
            ->body($this->formatNotificationBody($station, $credit))
            ->color($credit <= 0 ? 'danger' : 'warning')
            ->persistent()
            ->actions($this->getNotificationActions($station))
            ->send();
    }

    private function getNotificationTitle(float $credit): string
    {
        return $credit <= 0
            ? 'Credito carta insufficiente'
            : 'Credito carta basso';
    }

    private function formatNotificationBody(Station $station, float $credit): string
    {
        $formattedCredit = number_format($credit, 2, ',', '.');
        $formattedThreshold = number_format(self::LOW_CREDIT_THRESHOLD, 0, ',', '.');

        return sprintf(
            'Stazione <strong>%s</strong>: credito residuo <strong>€ %s</strong> (soglia € %s).',
            e($station->name),
            $formattedCredit,
            $formattedThreshold
        );
    }

    private function getNotificationActions(Station $station): array
    {
        return [
            Action::make('top_up')
                ->label('Rifornisci')
                ->url(StationResource::getUrl('edit', ['record' => $station]))
                ->button(),

            Action::make('ignore')
                ->label('Ignora')
                ->color('gray')
                ->close(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ];
    }
}
