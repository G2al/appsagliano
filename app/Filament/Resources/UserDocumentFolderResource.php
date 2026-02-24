<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserDocumentFolderResource\Pages;
use App\Filament\Resources\UserDocumentFolderResource\RelationManagers\FilesRelationManager;
use App\Models\UserDocumentFolder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserDocumentFolderResource extends Resource
{
    protected static ?string $model = UserDocumentFolder::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Cartella documento';

    protected static ?string $pluralModelLabel = 'Cartelle documenti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Utente')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('title')
                    ->label('Nome cartella')
                    ->required()
                    ->disabled(fn (?UserDocumentFolder $record): bool => (int) ($record?->document_folder_template_id ?? 0) > 0)
                    ->dehydrated(fn (?UserDocumentFolder $record): bool => (int) ($record?->document_folder_template_id ?? 0) === 0)
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utente')
                    ->formatStateUsing(fn (UserDocumentFolder $record): string => $record->user?->full_name ?: 'N/D')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Cartella')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('template.title')
                    ->label('Template')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('files_count')
                    ->counts('files')
                    ->label('File'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserDocumentFolders::route('/'),
            'edit' => Pages\EditUserDocumentFolder::route('/{record}/edit'),
        ];
    }
}
