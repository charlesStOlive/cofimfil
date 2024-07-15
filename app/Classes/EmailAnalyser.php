<?php

namespace App\Classes;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/

use App\Models\MsgUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Settings\AnalyseSettings;
use App\Classes\Services\SellsyService;

class EmailAnalyser
{
    private array $emailData;
    public string $from;
    public array $toRecipients;
    public string $fromNdd;
    public string $subject;
    public string $category;
    public string $body;
    public bool $forbiddenNdd = false;
    public bool $forward = false;
    public bool $has_score = false;
    public bool $hasContact = false;
    public bool $hasClient = false;
    public int $score = 0;
    private MsgUser $user;
    public $emailIn;

    public function __construct(array $email, MsgUser $user)
    {

        $this->user = $user;
        $this->emailIn = $user->msg_email_ins()->make();
        $this->extractEmailDetails($email);
    }

    private function extractEmailDetails($email): void
    {
        // Extraire les infos de bases.
        $this->emailIn->data_mail = $email;
        $sender = Arr::get($email, 'sender.emailAddress.address');
        $from = Arr::get($email, 'from.emailAddress.address');
        $this->emailIn->from = $from ?? $sender;
        $this->emailIn->subject = $subject = Arr::get($email, 'subject');
        if (stripos($subject, 'Re:') === 0 || stripos($subject, 'Fwd:') === 0 || stripos($subject, 'Fw:') === 0) {
            $this->emailIn->is_mail_response = true;
        }
        $tos = $this->getEmailToAddresses($email['toRecipients'] ?? []);
        $bcc =  $this->getEmailToAddresses($email['bccRecipients'] ?? []);
        $this->body = Arr::get($email, 'body.content');
        $this->emailIn->tos = array_merge($tos, $bcc);
        $this->emailIn->Save();
    }

    private function getEmailToAddresses($recipients)
    {
        $emails = [];
        //\Log::info('getEmailToAddresses');
        //\Log::info('user->email : '.$this->user->email);

        foreach ($recipients as $recipient) {
            if (isset($recipient['emailAddress']['address'])) {
                $email = $recipient['emailAddress']['address'];
                if ($email != $this->user->email) {
                    $emails[] = $email;
                }
            }
        }
        return $emails;
    }

    public function analyse(): void
    {
        $emailToAnalyse = $this->checkIfEmailIsToAnalyse();
        \Log::info('emailToAnalyse');
        \Log::info($emailToAnalyse);
        if ($emailToAnalyse === false) {
            \Log::info('emailToAnalyse false');
            return;
        }
        if ($emailToAnalyse === 'commerciaux') {
            // $this->forwardEmailFromCommerciaux();
            $this->emailIn->is_from_commercial = true;
            $regexKeyValue = $this->findEmailInBody($this->body);
            \Log::info('regexKeyValue : '.$regexKeyValue);
            if ($regexKeyValue) {
                $this->emailIn->regex_key_value = $regexKeyValue;
            } else {
                $this->emailIn->is_rejected = true;
                $this->emailIn->reject_info = 'Mail d\'un Com. Sans clefs';
                $this->emailIn->save();
                return;
            }
        }
        \Log::info('sellsy call');
        $this->emailIn->has_sellsy_call = true;
        $sellsy = $this->getContactAndClient();
        $this->emailIn->data_sellsy = $sellsy;
        \Log::info('after sellsy call');
        if (isset($sellsy['error'])) {
            $this->emailIn->is_rejected = true;
            $this->emailIn->reject_info = 'Abandonnée Error Sellsy';
            $this->emailIn->save();
        } else {
            if (isset($sellsy['contact'])) {
                \Log::info('contact OK');
                $this->emailIn->has_contact = true;
                \Log::info($sellsy['contact']['position']);
                if ($position = $sellsy['contact']['position'] ?? false) {
                    $this->emailIn->has_contact_job = true;
                    $score = $this->getContactJobScore($position);
                    if ($score != null) {
                        $this->emailIn->score_job = $score;
                    }
                }
            } else {
                \Log::info('client pas ok');
            }
            if (isset($sellsy['client'])) {
                \Log::info('client OK');
                $this->emailIn->has_client = true;
                $nameClient = $sellsy['client']['name'] ?? null;
                $nameClient = Str::limit($nameClient, 10);
                $codeClient = $sellsy['client']['progi-code-cli'] ?? null;
                $this->emailIn->new_subject = sprintf('{%s}-{%s}|%s', $codeClient, $nameClient,  $this->emailIn->subject);
                \Log::info($sellsy['client']['noteclient']);
                if (isset($sellsy['client']['noteclient'])) {
                    $score = $this->convertIntValue($sellsy['client']['noteclient']);
                    \Log::info('score' . $score);
                    if (is_null($score)) {
                        \Log::info('est null');
                        $this->emailIn->category = app(AnalyseSettings::class)->category_no_score;
                    } else {
                        $this->emailIn->score = $score;
                        $this->emailIn->has_score = true;
                    }
                } else {
                    $this->emailIn->category = app(AnalyseSettings::class)->category_no_score;
                }
            } else {
                \Log::info('client pas oK');
            }
            if (isset($sellsy['staff']['email'])) {
                $staffMail = $sellsy['staff']['email'];
                $this->emailIn->has_staff = true;
                if ($this->user->email != $staffMail) {
                    \Log::info('user email et staff dff');
                    if (!in_array($staffMail, $this->emailIn->tos)) {
                        \Log::info('pas dans la liste des destinataires');
                        $this->emailIn->move_to_folder = 'archive_outils';
                        $this->setScore();
                        $this->emailIn->forwarded_to = $staffMail;
                        $this->emailIn->save();
                        return;
                    } else {
                        \Log::info('Il est ddéjà dans la liste des destinataires mise dans un dossier');
                        $this->emailIn->move_to_folder = 'archive_outils';
                        $this->emailIn->category = 'Archivé';
                        $this->emailIn->save();
                        return;
                    }
                } else {
                    \Log::info('user email et staff identique');
                }
            }
            $this->setScore();
            $this->emailIn->save();
        }
    }

    private function getDomainFromEmail(string $email): ?string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }

    private function checkIfEmailIsToAnalyse()
    {
        $ndd = $this->getDomainFromEmail($this->emailIn->from);
        \Log::info($ndd);
        if (in_array($ndd, $this->getInternalNdds()) && !in_array($this->emailIn->from, $this->getCommerciaux())) {
            $this->emailIn->is_rejected = true;
            $this->emailIn->reject_info = 'Abandonnée NDD';
            $this->emailIn->save();
            return false;
        } else if (in_array($this->emailIn->from, $this->getCommerciaux())) {
            $this->emailIn->is_from_commercial = true;
            return 'commerciaux';
        } else {
            return true;
        }
    }

    private function getContactAndClient(): array
    {
        $sellsy = new SellsyService();
        \Log::info('getContactAndClient :'.$this->emailIn->regex_key_value);
        if ($this->emailIn->regex_key_value) {
            return $sellsy->searchContactByEmail($this->emailIn->regex_key_value);
        } else {
            return $sellsy->searchContactByEmail($this->emailIn->from);
        }
    }

    private function setScore()
    {
        if ($this->emailIn->has_score || $this->emailIn->has_contact_job) {
            $score = intval($this->emailIn->score) + intval($this->emailIn->score_job);
            $this->emailIn->category = $this->getScoreCategory($score);
        }
    }



    function findEmailInBody($body)
    {
        // La regex pour capturer les emails précédés de 'emailde:'
        $regex = '/emailde:\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/';

        // Recherche des correspondances
        if (preg_match($regex, $body, $matches)) {
            // Si une correspondance est trouvée, retourner l'email
            \Log::info('matches');
            \Log::info($matches);
            return $matches[1];
        } else {
            // Si aucune correspondance n'est trouvée, retourner null
            return null;
        }
    }

    private function convertIntValue($valeur)
    {
        if (is_null($valeur)) {
            return null;
        }
        return intval($valeur);
    }

    private  function isFromCommercial(array $emails)
    {
    }

    private function getCommerciaux(): array
    {
        $commerciaux = app(AnalyseSettings::class)->commercials;
        // Extraire et retourner les emails des commerciaux
        return array_map(function ($commercial) {
            return $commercial['email'];
        }, $commerciaux);
    }

    private function getInternalNdds(): array
    {
        $ndds =  app(AnalyseSettings::class)->internal_ndds;
        return array_map(function ($ndd) {
            return $ndd['ndd'];
        }, $ndds);
    }

    private function getForbiddenClientNdd(): array
    {
        $ndds =  app(AnalyseSettings::class)->ndd_client_rejecteds;
        return array_map(function ($ndd) {
            return $ndd['ndd'];
        }, $ndds);
    }

    private function getScoreCategory(int $score): string
    {
        $scorings = $this->getScorings();

        foreach ($scorings as $scoring) {
            if ($score >= $scoring['score_min'] && $score <= $scoring['score_max']) {
                return $scoring['category'];
            }
        }

        return 'unknown'; // Retourne 'unknown' si aucune catégorie n'est trouvée
    }

    private function getScorings(): array
    {
        $scorings = app(AnalyseSettings::class)->scorings;

        // Transformer les données en un tableau associatif pour un accès plus facile
        $formattedScorings = array_map(function ($scoring) {
            return [
                'score_max' => (int)$scoring['score-max'],
                'score_min' => (int)$scoring['score-min'],
                'category' => $scoring['category'],
            ];
        }, $scorings);

        return $formattedScorings;
    }


    private function getContactJobScore(string $jobName): int
    {
        $scorings = $this->getContactScorings();

        foreach ($scorings as $scoring) {
            if (strcasecmp($scoring['name'], $jobName) === 0) {
                return $scoring['score'];
            }
        }

        return 0; // Retourne 0 si aucun score n'est trouvé pour le nom du métier
    }

    private function getContactScorings(): array
    {
        $scorings = app(AnalyseSettings::class)->contact_scorings;

        // Transformer les données en un tableau associatif pour un accès plus facile
        $formattedScorings = array_map(function ($scoring) {
            return [
                'name' => $scoring['name'],
                'score' => (int)$scoring['score'],
            ];
        }, $scorings);

        return $formattedScorings;
    }
}
