<?php

namespace App\Filament\Pages;

use Filament\Forms;
use App\Models\MsgUser;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\AnalyseSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use App\Classes\Services\SellsyService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Actions\Action;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;

class ManageAnalyse extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $settings = AnalyseSettings::class;

    public static function getNavigationLabel(): string
    {
        return 'Options';
    }

    protected static ?int $navigationSort = 2;

    public function form(Form $form): Form
    {
        $userOptions = MsgUser::getLocalUserEmail();


        return $form
            ->schema([
                Section::make('Catégories')
                    ->description('Allocatoin des catégories en fonction du score')
                    ->schema([
                        TextInput::make('category_no_score')->label('Catégorie pour client sans Score (null) dans Sellsy')->required(),
                        Repeater::make('scorings')->label('Catégorie allouée en fonction du score ( score client + scorejob si existe )')
                            ->schema([
                                TextInput::make('score-min')->integer()->required(),
                                TextInput::make('score-max')->integer()->required(),
                                TextInput::make('category')->label('nom de la catégorie')->required(),
                            ])
                            ->addActionLabel('Ajouter un score mix/max')
                            ->columns(3),
                    ]),
                Section::make('contact_scorings')
                    ->description('Grille des scores des metiers.')
                    ->schema([
                        Repeater::make('contact_scorings')
                            ->schema([
                                TextInput::make('name')->columnSpan(2)->required(),
                                TextInput::make('score')->integer()->required(),
                            ])
                            ->addActionLabel('Ajouter un métier de contact')
                            ->columns(3),
                    ]),

                Section::make('Commerciaux')
                    ->description('Liste des adresses emails commerciaux qui seront exploités lors d\'un transfert de mail.')
                    ->schema([
                        Repeater::make('commercials')
                            ->schema([
                                TextInput::make('email')->email()->required(),
                            ])
                            ->addActionLabel('Ajouter un commercial')
                            ->grid(2),
                    ]),
                Section::make('Nom de domaines bloqués')
                    ->description('Liste des noms de domainex internes qui ne seront pas étudié sauf si commercial.')
                    ->schema([
                        Repeater::make('internal_ndds')
                            ->schema([
                                TextInput::make('ndd')->prefixIcon('heroicon-m-at-symbol')->placeholder('exemple.com')->required(),
                            ])
                            ->addActionLabel('Ajouter un Nom de domaine interne')
                            ->grid(2),
                    ]),
                Section::make('Nom de domaine exterieurs')
                    ->description('Liste des noms de domainex exterieurs qui ne seront pas étudié si le contact est inconnu.')
                    ->schema([
                        Repeater::make('ndd_client_rejecteds')
                            ->schema([
                                TextInput::make('ndd')->prefixIcon('heroicon-m-at-symbol')->placeholder('exemple.com')->required(),
                            ])
                            ->addActionLabel('Ajouter un Nom de domaine externe')
                            ->grid(2),
                    ]),
            //     Section::make('Test de connection')
            //         ->description('Tester la connexion avec Sellsy.')
            //         ->schema([
            //             Actions::make([
            //                 Action::make('testConnection')
            //                     ->label('Simuler un email')
            //                     ->icon('heroicon-o-play')
            //                     ->color('primary')
            //                     ->form([
            //                         // Select::make('msg_id')
            //                         //     ->label('Choisissez un Email')
            //                         //     ->options($userOptions)
            //                         //     ->default(function () use ($userOptions) {
            //                         //         return !empty($userOptions) ? array_key_first($userOptions) : null;
            //                         //     }),
            //                         TextInput::make('test_from')->label('From')->default('alexis.clement@suscillon.com'),
            //                         TextInput::make('test_tos')->label('to')->helperText('Séparer les valeurs par une ",", la première valeur sera la cible MsgraphUser, elle doit exister !')->default(function () use ($userOptions) {
            //                             return !empty($userOptions) ? array_key_first($userOptions) : null;
            //                         }),
            //                         TextInput::make('subject')->label('Sujet')->default('Hello World !'),
            //                         RichEditor::make('body')->label('body')->default('<p>Du contenu</p>'),
            //                     ])
            //                     ->modalHeading('Créer un faux email')
            //                     ->modalSubmitActionLabel('Exécuter le test')
            //                     ->action(function (Forms\Set $set, array $data) {
            //                         // $email = $data['parametre'] ?? null;
            //                         $data['from']['emailAddress']['address'] = $email = trim($data['test_from']);
            //                         $toResipients = [];
            //                         $tos = explode(',', trim($data['test_tos']));
            //                         foreach ($tos as $to) {
            //                             $toResipients[] = ['emailAddress' => ['address' => trim($to)]];
            //                         }
            //                         $data['toRecipients'] = $toResipients;
            //                         unset($data['test_from']);
            //                         unset($data['test_tos']);
            //                         //\Log::info($data);
            //                         $sellsy = new SellsyService();
            //                         $result = $sellsy->searchContactByEmail($email);
            //                         // $result = $sellsy->getCustomFields();
            //                         $set('test_result', $result);
            //                     }),

            //             ]),
            //             FilamentJsonColumn::make('test_result')->viewerOnly(),
            //         ]),
            ])->columns(1);
    }
}
