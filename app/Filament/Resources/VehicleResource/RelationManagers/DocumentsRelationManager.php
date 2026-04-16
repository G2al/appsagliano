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
    private const ACCEPTED_FILE_TYPES = ['image/*', 'application/pdf'];

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti veicolo';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                Tables\Columns\IconColumn::make('mime_type')
                    ->label('Tipo')
                    ->icon(fn (?string $state): string => match (true) {
                        str_starts_with((string) $state, 'image/') => 'heroicon-o-photo',
                        $state === 'application/pdf' => 'heroicon-o-document-text',
                        default => 'heroicon-o-document',
                    })
                    ->color(fn (?string $state): string => match (true) {
                        str_starts_with((string) $state, 'image/') => 'primary',
                        $state === 'application/pdf' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : 'N/D')
                    ->searchable(),
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
                    ->label('Carica file')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => $this->remainingSlots() > 0)
                    ->modalHeading('Carica documenti veicolo')
                    ->form([
                        Forms\Components\FileUpload::make('files')
                            ->label('File')
                            ->multiple()
                            ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES)
                            ->disk('public')
                            ->directory(fn (): string => 'vehicle-documents/' . $this->ownerRecord->getKey())
                            ->visibility('public')
                            ->maxFiles(fn (): int => $this->remainingSlots())
                            ->required()
                            ->helperText(fn (): string => 'Puoi caricare fino a ' . self::MAX_FILES . ' file per veicolo. Formati accettati: immagini e PDF.'),
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
                                ->title('Limite documenti raggiunto')
                                ->body('Ogni veicolo puo avere al massimo ' . self::MAX_FILES . ' file.')
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
                            ->title('File caricati')
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
                            ->label('File')
                            ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES)
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
