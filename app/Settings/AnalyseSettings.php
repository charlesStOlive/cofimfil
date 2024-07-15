<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AnalyseSettings extends Settings
{
    public array $commercials;

    public array $internal_ndds;
    
    public array $ndd_client_rejecteds;

    public array $scorings;

    public array $contact_scorings;

    public string $category_no_score;

    public static function group(): string
    {
        return 'analyse';
    }
}