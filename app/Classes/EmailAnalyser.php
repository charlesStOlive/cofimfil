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
    public string $contentType;
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
        $this->contentType = Arr::get($email, 'body.contentType');
        $this->emailIn->tos = array_merge($tos, $bcc);
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
        // \Log::info('emailToAnalyse');
        // \Log::info($emailToAnalyse);
        if ($emailToAnalyse === false) {
            \Log::info('emailToAnalyse false');
            return;
        }
        if ($emailToAnalyse === 'commerciaux') {
            // $this->forwardEmailFromCommerciaux();
            $this->emailIn->is_from_commercial = true;
            $regexKeyValue = $this->findEmailInBody($this->body);
            if ($regexKeyValue) {
                $this->emailIn->regex_key_value = $regexKeyValue;
            } else {
                $this->emailIn->is_rejected = true;
                $this->emailIn->reject_info = 'Abdn Com/Adv ss clefs';
                $this->emailIn->save(); 
                return;
            }
        }
        $this->emailIn->has_sellsy_call = true;
        $sellsy = $this->getContactAndClient();
        $this->emailIn->data_sellsy = $sellsy;
        if (isset($sellsy['error'])) {
            $this->emailIn->is_rejected = true;
            $this->emailIn->reject_info = 'Abdn Inc Sellsy';
            $this->emailIn->save(); \Log::info('je save---------------------107');
        } else {
            if (isset($sellsy['contact'])) {
                $this->emailIn->has_contact = true;
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
                $codeSubject = sprintf('{%s}-{%s}', $codeClient, $nameClient);
                if (strpos($this->emailIn->subject, $codeSubject) === false) {
                    $this->emailIn->new_subject = $this->rebuildSubject($this->emailIn->subject, $codeSubject);
                } else {
                    $this->emailIn->new_subject = $this->emailIn->subject;
                }
                if (isset($sellsy['client']['noteclient'])) {
                    $score = $this->convertIntValue($sellsy['client']['noteclient']);
                    if (is_null($score)) {
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
                    if (!in_array($staffMail, $this->emailIn->tos)) {
                        $this->emailIn->move_to_folder = 'x-projet-notation';
                        $this->setScore();
                        $this->emailIn->forwarded_to = $staffMail;
                        $this->emailIn->save(); 
                        return;
                    } else {
                        \Log::info('Il est ddéjà dans la liste des destinataires mise dans un dossier');
                        $this->emailIn->move_to_folder = 'x-projet-notation';
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

    // Fonction pour détecter les préfixes et reconstruire le sujet
    function rebuildSubject($subject, $codeSubject)
    {
        // Regex pour détecter les préfixes (Re, Fw, etc.) suivi éventuellement par des chiffres (ex: Re: ou Fw: ou Fwd: etc.)
        $regex = '/^(Re|Fw|Fwd)(\[\d+\])?(\s*:\s*)?/i';

        // Rechercher le préfixe dans le sujet
        if (preg_match($regex, $subject, $matches)) {
            // Extraire le préfixe détecté
            $prefix = $matches[0];
            // Reconstruire le sujet en gardant le préfixe, ajoutant le code, et le reste du sujet
            return sprintf('%s%s|%s', $prefix, $codeSubject, preg_replace($regex, '', $subject));
        } else {
            // Pas de préfixe détecté, simplement ajouter le code au début
            return sprintf('%s|%s', $codeSubject, $subject);
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
        if (in_array($ndd, $this->getInternalNdds()) && !in_array($this->emailIn->from, $this->getCommerciaux())) {
            $this->emailIn->is_rejected = true;
            $this->emailIn->reject_info = 'Abdn NDD';
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
        if ($this->emailIn->regex_key_value) {
            return $sellsy->searchContactByEmail($this->emailIn->regex_key_value);
        } else {
            return $sellsy->searchContactByEmail($this->emailIn->from);
        }
    }

    private function setScore()
    {
        // if ($this->emailIn->has_score || $this->emailIn->has_contact_job) {
        //     $score = intval($this->emailIn->score) + intval($this->emailIn->score_job);
        //     $this->emailIn->category = $this->getScoreCategory($score);
        // }
        $score = null;
        if ($this->emailIn->has_score) {
            $score = intval($this->emailIn->score);
            if($this->emailIn->has_contact_job) {
                $score += intval($this->emailIn->score_job);
            }
            $this->emailIn->category = $this->getScoreCategory($score);
        } else {
            $this->emailIn->category = app(AnalyseSettings::class)->category_no_score;
        }
    }



    function findEmailInBody($body)
    {
        \Log::info('analyse et transformation temp du body***');
        $body = strip_tags($body);
        // La regex pour capturer les emails précédés de 'emailde:'
        $regex = '/[eE]mail[Dd]e=\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})(?=\s|$)/';

        // Recherche des correspondances
        if (preg_match($regex, $body, $matches)) {
            // Si une correspondance est trouvée, retourner l'email
            return $matches[1];
        } else {
            // Si aucune correspondance n'est trouvée, retourner null
            return null;
        }
    }

    function getBodyWithReplacedKey()
    {
        // // La regex pour capturer et enlever les emails précédés de 'emailde:'
        // $regex = '/emailde:\s*[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        // // Remplace toutes les occurrences trouvées par une chaîne vide
        // $bodyWithoutKey = preg_replace($regex, '', $this->emailIn->body);
        // // Retourner le corps du mail modifié
        // return $bodyWithoutKey;
        $regex = '/[eE]mail[Dd]e=\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})(?=\s|$)/';
        // Remplace toutes les occurrences trouvées par 'emailtransféréde:'
        $replacement = 'emailtransféréde= $1 ';
        \Log::info($this->body);
        \Log::info($this->contentType);
        $bodyWithoutKey = preg_replace($regex, $replacement, $this->body);
        // Retourner le corps du mail modifié
        return $bodyWithoutKey;
    }

    private function convertIntValue($valeur)
    {
        if (is_null($valeur)) {
            return null;
        }
        return intval($valeur);
    }

    private function getCommerciaux(): array
    {
        $commerciaux = app(AnalyseSettings::class)->commercials;
        // Extraire et retourner les emails des commerciaux
        $commerciaux =  array_map(function ($commercial) {
            return $commercial['email'];
        }, $commerciaux);
        $advs = MsgUser::pluck('email')->toArray();
        return array_merge($advs, $commerciaux);
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
