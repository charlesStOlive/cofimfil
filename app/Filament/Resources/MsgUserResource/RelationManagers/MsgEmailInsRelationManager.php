<?php 

namespace App\Filament\Resources\MsgUserResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Classes\EmailAnalyser;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;

class MsgEmailInsRelationManager extends RelationManager
{
    protected static string $relationship = 'msg_email_ins';

    protected static string $resource = PostResource::class;

    protected $listeners = ['refreshExampleRelationManager' => '$refresh'];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('test_from')->label('From')->default('alexis.clement@suscillon.com'),
                TextInput::make('test_tos')->label('to')->helperText('Séparer les valeurs par une ",", la première valeur sera la cible MsgraphUser, elle doit exister !')->default($this->getOwnerRecord()->email),
                TextInput::make('subject')->label('Sujet')->default('Hello World !'),
                RichEditor::make('body')->label('body')->default('<p>Du contenu</p>'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(function ($record) {
                        if ($record->is_rejected) {
                            return 'danger';
                        } elseif ($record->forwarded_to) {
                            return 'warning';
                        } elseif ($record->move_to_folder) {
                            return 'info';
                        } else {
                            return 'success';
                        }
                    })
                    ->icon(function ($record) {
                        if ($record->is_rejected) {
                            return 'heroicon-o-x-circle';
                        } elseif ($record->forwarded_to) {
                            return 'heroicon-o-paper-airplane';
                        } elseif ($record->move_to_folder) {
                            return 'heroicon-o-archive-box';
                        } else {
                            return 'heroicon-o-check-circle';
                        }
                    }),
                Tables\Columns\IconColumn::make('is_from_commercial')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->timezone('Europe/Paris')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('is_from_commercial')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_from_commercial', true)),
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        Section::make('email_data')
                            ->schema([
                                TextInput::make('from')->columnSpan(4),
                                FilamentJsonColumn::make('tos')->label('autres cibles')->columnSpan(4)->viewerOnly(),
                                TextInput::make('subject')->columnSpan(2),
                                FilamentJsonColumn::make('data_mail')->columnSpan(4)->viewerOnly(),
                            ])->columns(4)->columnSpan(4)->collapsed(),
                        Section::make('analyse')
                            ->schema([
                                Checkbox::make('is_rejected')->label('Abandonné')->columnSpan(2),
                                TextInput::make('reject_info')->columnSpan(2),
                                TextInput::make('move_to_folder')->columnSpan(4),
                                Checkbox::make('is_from_commercial')->columnSpan(2),
                                TextInput::make('regex_key_value')->columnSpan(2),
                                Checkbox::make('is_mail_response')->label('Réponse ou Transfert détecté dans l\'objet')->columnSpan(4),
                                TextInput::make('new_subject')->columnSpan(2),
                                TextInput::make('category')->columnSpan(2),
                                TextInput::make('forwarded_to')->label('adresse transfert')->columnSpan(2),
                            ])->columns(4)->columnSpan(4),
                        Section::make('Selssy')
                            ->schema([
                                Checkbox::make('has_client'),
                                Checkbox::make('has_contact'),
                                Checkbox::make('has_staff')->columnSpan(2),
                                Checkbox::make('has_score'),
                                TextInput::make('score'),
                                Checkbox::make('has_contact_job'),
                                TextInput::make('score_job'),
                                FilamentJsonColumn::make('data_sellsy')->columnSpan(4)->viewerOnly(),
                            ])->columns(4)->columnSpan(4)->visible(fn ($record) => $record->has_sellsy_call),
                    ]),
            ]);
    }
}
