<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksPanelModules;
use App\Filament\Resources\DocumentFolderTemplateResource\Pages;
use App\Models\DocumentFolderTemplate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DocumentFolderTemplateResource extends Resource
{
    use ChecksPanelModules;

    protected static ?string $model = DocumentFolderTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'Cartelle';
    protected static ?string $navigationGroup = 'Impostazioni';
    protected static ?string $modelLabel = 'Cartella';
    protected static ?string $pluralLabel = 'Cartelle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Nome cartella')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (?DocumentFolderTemplate $record) => Rule::unique('document_folder_templates', 'title')->ignore($record?->id)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('userFolders'))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Cartella')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_folders_count')
                    ->label('Utenti collegati')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentFolderTemplates::route('/'),
            'create' => Pages\CreateDocumentFolderTemplate::route('/create'),
            'edit' => Pages\EditDocumentFolderTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanAccessModules([User::PANEL_MODULE_DOCUMENTS]);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }
}
