<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use App\Models\VehicleDocument;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    private const MAX_FILES = 10;

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Foto veicolo';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(84),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Dimensione')
                    ->formatStateUsing(fn ($state): string => $this->formatBytes((int) ($state ?? 0))),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Caricata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('upload_photos')
                    ->label('Carica foto')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (): bool => $this->remainingSlots() > 0)
                    ->modalHeading('Carica foto veicolo')
                    ->form([
                        Forms\Components\FileUpload::make('files')
                            ->label('Foto')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory(fn (): string => 'vehicle-documents/' . $this->ownerRecord->getKey())
                            ->visibility('public')
                            ->maxFiles(fn (): int => $this->remainingSlots())
                            ->required()
                            ->helperText(fn (): string => 'Puoi caricare fino a ' . self::MAX_FILES . ' foto per veicolo.'),
                    ])
                    ->action(function (array $data): void {
                        $paths = array_values(array_filter((array) ($data['files'] ?? [])));
                        $remainingSlots = $this->remainingSlots();

                        if (empty($paths)) {
                            return;
                        }

                        if (count($paths) > $remainingSlots) {
                            Storage::disk('public')->delete($paths);

                            Notification::make()
                                ->title('Limite foto raggiunto')
                                ->body('Ogni veicolo puo avere al massimo ' . self::MAX_FILES . ' foto.')
                                ->danger()
                                ->send();

                            return;
                        }

                        foreach ($paths as $path) {
                            $this->ownerRecord->documents()->create([
                                'file_path' => $path,
                            ]);
                        }

                        Notification::make()
                            ->title('Foto caricate')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Apri')
                    ->icon('heroicon-o-eye')
                    ->url(fn (VehicleDocument $record): ?string => $record->file_url)
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->label('Sostituisci')
                    ->form([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Foto')
                            ->image()
                            ->disk('public')
                            ->directory(fn (): string => 'vehicle-documents/' . $this->ownerRecord->getKey())
                            ->visibility('public')
                            ->required(),
                    ]),
                Tables\Actions\DeleteAction::make()
                    ->label('Elimina'),
            ]);
    }

    private function remainingSlots(): int
    {
        return max(self::MAX_FILES - $this->ownerRecord->documents()->count(), 0);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2, ',', '.') . ' ' . $units[$power];
    }
}
