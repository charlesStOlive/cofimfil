<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\MsgUser;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\MsgUserResource\Pages;
use App\Filament\Resources\MsgUserResource\RelationManagers;

class MsgUserResource extends Resource
{
    protected static ?string $model = MsgUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Utilisateurs emails';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('ms_id'),
                TextEntry::make('email'),
                // TextEntry::make('abn_secret'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('ms_id')->searchable()->sortable(),
                TextColumn::make('suscription_id'),
                ToggleColumn::make('is_test')->label('En test'),
                //
            ])
            ->filters([
                Filter::make('is_test')
                    ->toggle()
                    ->query(fn ($query)=> $query->where('is_test', true)),
            ])
            ->actions([
                Action::make('suscribe')
                    ->label('Souscrire')
                    ->requiresConfirmation()
                    ->modalDescription('Activez le mode test au préalable, si vous ne voulez pas modifier le mail')
                    ->action(fn (MsgUser $record) => $record->suscribe())
                    ->visible(fn (MsgUser $record): bool => $record->suscription_id === null),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (MsgUser $record) => $record->revokeSuscription())
                    ->visible(fn (MsgUser $record): bool => $record->suscription_id !== null),
                Action::make('refresh')
                    ->label('Refresh')
                    ->color('gray')
                    ->action(fn (MsgUser $record) => $record->refreshSuscription())
                    ->visible(fn (MsgUser $record): bool => $record->suscription_id !== null),
            ])
            ->recordUrl(
                fn (MsgUser $record): string => MsgUserResource::getUrl('view', ['record' => $record])
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MsgEmailInsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMsgUsers::route('/'),
            'create' => Pages\CreateMsgUser::route('/create'),
            'edit' => Pages\EditMsgUser::route('/{record}/edit'),
            'view' => Pages\ViewMsgUser::route('/{record}/view'),
        ];
    }
}
