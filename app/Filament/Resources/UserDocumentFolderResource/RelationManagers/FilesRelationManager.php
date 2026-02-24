<?php

namespace App\Filament\Resources\UserDocumentFolderResource\RelationManagers;

use App\Models\UserDocumentFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'File cartella';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titolo file')
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File')
                    ->disk('local')
                    ->directory(fn (): string => 'worker-documents/' . $this->ownerRecord->user_id . '/' . $this->ownerRecord->id)
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Dimensione')
                    ->formatStateUsing(fn ($state): string => $this->formatBytes((int) ($state ?? 0))),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Aperto' : 'Non aperto')
                    ->color(fn ($state): string => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Aperto il')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Caricato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (UserDocumentFile $record): string => route('user-documents.files.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = Auth::id();

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
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
