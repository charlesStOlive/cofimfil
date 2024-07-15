<?php

namespace App\Filament\Resources\MsgUserResource\Pages;

use Filament\Forms;
use Filament\Actions;
use App\Classes\EmailAnalyser;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\RichEditor;
use App\Filament\Resources\MsgUserResource;

class ViewMsgUser extends ViewRecord
{
    protected static string $resource = MsgUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
           Actions\Action::make('testConnection')
                ->label('Simuler un email')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->form([
                    TextInput::make('test_from')->label('From')->default('alexis.clement@suscillon.com'),
                    TextInput::make('test_tos')->label('To')->helperText('Séparer les valeurs par une ",", la première valeur sera la cible MsgraphUser, elle doit exister !')->default(fn () => $this->record->email),
                    TextInput::make('test_bccs')->label('Cc')->helperText('Séparer les valeurs par une ",",'),
                    TextInput::make('subject')->label('Sujet')->default('Hello World !'),
                    RichEditor::make('body')->label('body')->default('<p>Du contenu</p>'),
                ])
                ->modalHeading('Créer un faux email')
                ->modalSubmitActionLabel('Exécuter le test')
                ->action(function (array $data, $livewire) {
                    $fromTemp = $data['test_from'];
                    $toTemp = $data['test_tos'];
                    $dataEmail = $data;
                    $dataEmail['body'] = [
                        'content' => $data['body'],
                        'contentType' => "html",
                    ];
                    $dataEmail['from']['emailAddress']['address'] = $email = trim($fromTemp);
                    $toResipients = [];
                    $tos = explode(',', trim($dataEmail['test_tos']));
                    foreach ($tos as $to) {
                        $toResipients[] = ['emailAddress' => ['address' => trim($to)]];
                    }
                    $dataEmail['toRecipients'] = $toResipients;
                    $bccs = explode(',', trim($dataEmail['test_bccs']));
                    $bccResipients = [];
                    foreach ($bccs as $bcc) {
                        $bccResipients[] = ['emailAddress' => ['address' => trim($bcc)]];
                    }
                    $dataEmail['bccRecipients'] = $bccResipients;
                    \Log::info($dataEmail);
                    $emailAnalyser = new EmailAnalyser($dataEmail, $this->getRecord());
                    $emailAnalyser->analyse();
                    $livewire->dispatch('refreshExampleRelationManager');
                    return;
                }),
        ];
    }

}
