<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use App\Models\VehicleDocument;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipologia')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->label('File')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : 'N/D')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (VehicleDocument $record): ?string => match (true) {
                        $record->expiry_date === null => null,
                        $record->expiry_date->isPast() => 'danger',
                        $record->expiry_date->diffInDays(now()) <= 15 => 'warning',
                        $record->expiry_date->diffInDays(now()) <= 30 => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Dimensione')
                    ->formatStateUsing(fn ($state): string => $this->formatBytes((int) ($state ?? 0))),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Caricata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('upload_document')
                    ->label('Carica documento')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => $this->remainingSlots() > 0)
                    ->modalHeading('Carica documento veicolo')
                    ->form($this->documentForm())
                    ->action(function (array $data): void {
                        $this->ownerRecord->documents()->create([
                            'document_type' => $data['document_type'] ?? null,
                            'expiry_date' => $data['expiry_date'] ?? null,
                            'file_path' => $data['file_path'],
                        ]);

                        Notification::make()
                            ->title('Documento caricato')
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
                    ->label('Modifica')
                    ->form($this->documentForm(requireFile: false)),
                Tables\Actions\DeleteAction::make()
                    ->label('Elimina'),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function documentForm(bool $requireFile = true): array
    {
        return [
            Forms\Components\TextInput::make('document_type')
                ->label('Tipologia documento')
                ->maxLength(255)
                ->placeholder('Es. Assicurazione, Revisione, Bollo, Libretto')
                ->nullable(),
            Forms\Components\DatePicker::make('expiry_date')
                ->label('Scadenza')
                ->nullable(),
            Forms\Components\FileUpload::make('file_path')
                ->label('File')
                ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES)
                ->disk('public')
                ->directory(fn (): string => 'vehicle-documents/' . $this->ownerRecord->getKey())
                ->visibility('public')
                ->required($requireFile),
        ];
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
