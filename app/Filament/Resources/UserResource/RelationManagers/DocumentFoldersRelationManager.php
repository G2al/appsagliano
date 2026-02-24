<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\UserDocumentFolderResource;
use App\Models\UserDocumentFolder;
use App\Services\UserDocumentFolderTemplateSyncService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentFoldersRelationManager extends RelationManager
{
    protected static string $relationship = 'documentFolders';

    protected static ?string $title = 'Cartelle documenti';

    protected static ?string $recordTitleAttribute = 'title';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                app(UserDocumentFolderTemplateSyncService::class)->syncForUser($this->ownerRecord);

                return $query->withCount([
                    'files',
                    'files as opened_files_count' => fn ($filesQuery) => $filesQuery->whereNotNull('opened_at'),
                ])->orderBy('title');
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Cartella')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('files_count')
                    ->label('File')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato lettura')
                    ->badge()
                    ->state(function (UserDocumentFolder $record): string {
                        $files = (int) ($record->files_count ?? 0);
                        $opened = (int) ($record->opened_files_count ?? 0);

                        if ($files === 0) {
                            return 'Vuota';
                        }

                        if ($opened === 0) {
                            return 'Non letta';
                        }

                        if ($opened < $files) {
                            return 'Parziale';
                        }

                        return 'Completata';
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Completata' => 'success',
                            'Parziale' => 'warning',
                            'Non letta' => 'danger',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_files')
                    ->label('File')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (UserDocumentFolder $record): string => UserDocumentFolderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
