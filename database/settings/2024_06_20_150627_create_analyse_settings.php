<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('analyse.commercials', []);
        $this->migrator->add('analyse.internal_ndds', []);
        $this->migrator->add('analyse.ndd_client_rejecteds', []);
        $this->migrator->add('analyse.category_no_score', 'Sans Score');
        $this->migrator->add('analyse.scorings', []);
        $this->migrator->add('analyse.contact_scorings', []);
    }
};
