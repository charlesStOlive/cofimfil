<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // Assurez-vous d'utiliser le modèle User ou celui que vous utilisez pour les utilisateurs Filament
use Illuminate\Support\Facades\Hash;

class FilamentUserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'Charles',
            'email' => 'charles@notilac.fr',
            'password' => Hash::make(env('TEMP_P_1')), // Assurez-vous de hacher le mot de passe
        ]);

        User::create([
            'name' => 'Pierrick',
            'email' => 'p.taillandier@menuiserie-cofim.com',
            'password' => Hash::make(env('TEMP_P_2')), // Assurez-vous de hacher le mot de passe
        ]);
    }
}