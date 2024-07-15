<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            [
                'id' => 1,
                'group' => 'analyse',
                'name' => 'commercials',
                'locked' => 0,
                'payload' => '[]',
                'created_at' => '2024-07-08 09:23:25',
                'updated_at' => '2024-07-08 09:28:00',
            ],
            [
                'id' => 2,
                'group' => 'analyse',
                'name' => 'internal_ndds',
                'locked' => 0,
                'payload' => '[{"ndd": "cofim-menuiserie.com"}]',
                'created_at' => '2024-07-08 09:23:25',
                'updated_at' => '2024-07-08 09:28:00',
            ],
            [
                'id' => 3,
                'group' => 'analyse',
                'name' => 'ndd_client_rejecteds',
                'locked' => 0,
                'payload' => '[{"ndd": "yahoo.fr"}, {"ndd": "hotmail.com"}, {"ndd": "hotmail.fr"}, {"ndd": "gmail.com"}, {"ndd": "artisan.fr"}]',
                'created_at' => '2024-07-08 09:23:25',
                'updated_at' => '2024-07-08 09:28:00',
            ],
            [
                'id' => 4,
                'group' => 'analyse',
                'name' => 'scorings',
                'locked' => 0,
                'payload' => '[{"score-max": "10", "score-min": "0", "category": "bas"}, {"score-max": "20", "score-min": "11", "category": "moyen"}, {"score-max": "30", "score-min": "21", "category": "Important"}, {"score-max": "40", "score-min": "31", "category": "hot"}]',
                'created_at' => '2024-07-08 09:23:25',
                'updated_at' => '2024-07-08 09:28:00',
            ],
            [
                'id' => 5,
                'group' => 'analyse',
                'name' => 'contact_scorings',
                'locked' => 0,
                'payload' => '[{"name": "METIER X", "score": "10"}, {"name": "CONDUITE DE TRAVAUX", "score": "19"}]',
                'created_at' => '2024-07-08 09:23:25',
                'updated_at' => '2024-07-08 09:28:00',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'name' => $setting['name']],
                [
                    'locked' => $setting['locked'],
                    'payload' => $setting['payload'],
                    'created_at' => Carbon::parse($setting['created_at']),
                    'updated_at' => Carbon::parse($setting['updated_at']),
                ]
            );
        }
    }
}
