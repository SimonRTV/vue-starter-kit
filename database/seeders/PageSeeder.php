<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::factory()->published()->create([
            'title' => 'À propos',
            'slug' => 'a-propos',
            'excerpt' => 'Découvrez notre équipe, nos valeurs et notre mission.',
            'body' => 'Nous créons des produits soigneusement conçus qui aident les équipes à donner le meilleur d’elles-mêmes.',
        ]);

        Page::factory()->published()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'excerpt' => 'Découvrez la meilleure façon de contacter notre équipe.',
            'body' => 'Envoyez-nous un message et un membre de notre équipe vous répondra.',
        ]);

        Page::factory()->published()->create([
            'title' => 'Politique de confidentialité',
            'slug' => 'politique-de-confidentialite',
            'excerpt' => 'Comment nous recueillons, utilisons et protégeons vos informations.',
            'body' => 'Cette politique de confidentialité explique comment vos informations sont traitées lorsque vous utilisez nos services.',
        ]);

        Page::factory()->draft()->create([
            'title' => 'Services',
            'slug' => 'services',
            'excerpt' => 'Un aperçu des services que nous proposons.',
            'body' => 'Cette page brouillon est prête à accueillir le détail de vos services.',
        ]);
    }
}
