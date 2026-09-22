<?php

namespace Tests\Feature;

use App\Support\MatchPerimeter;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Perimetre d'ouverture du modele match (MOBILE.md §6, decision 6).
 *
 * Le test le plus important est le premier : une valeur VIDE ferme tout.
 * C'est l'inverse de LBA_DEPARTEMENTS, et l'inverse d'un defaut « ouvert »
 * qu'une variable d'environnement oubliee au deploiement declencherait sans
 * que personne ne s'en apercoive — sauf les employeurs de toute la France,
 * a qui l'on montrerait des cartes de mineurs.
 */
class MatchPerimeterTest extends TestCase
{
    public function test_default_is_the_launch_department(): void
    {
        // config/services.php : env('JEUNCY_MATCH_DEPARTEMENTS', '66').
        $this->assertSame(['66'], MatchPerimeter::departments());
        $this->assertTrue(MatchPerimeter::isOpen('66000'));
        $this->assertFalse(MatchPerimeter::isOpen('75001'));
    }

    public function test_empty_value_means_closed(): void
    {
        Config::set('services.jeuncy.match_departements', '');

        $this->assertSame([], MatchPerimeter::departments());
        $this->assertFalse(MatchPerimeter::isOpen('66000'));
        $this->assertFalse(MatchPerimeter::isOpen('75001'));
    }

    public function test_star_means_all(): void
    {
        Config::set('services.jeuncy.match_departements', '*');

        $this->assertSame(['*'], MatchPerimeter::departments());
        $this->assertTrue(MatchPerimeter::isOpen('75001'));
    }

    public function test_list_restricts(): void
    {
        Config::set('services.jeuncy.match_departements', '66,11');

        $this->assertTrue(MatchPerimeter::isOpen('66000'));
        $this->assertTrue(MatchPerimeter::isOpen('11100'));
        $this->assertFalse(MatchPerimeter::isOpen('34000'));
    }

    public function test_corsica_and_overseas_codes(): void
    {
        Config::set('services.jeuncy.match_departements', '2A,971');

        $this->assertTrue(MatchPerimeter::isOpen('20000'), 'Ajaccio est en Corse-du-Sud (2A).');
        $this->assertTrue(MatchPerimeter::isOpen('97100'), 'La Guadeloupe se lit sur trois chiffres.');
        $this->assertFalse(MatchPerimeter::isOpen('97400'), 'La Reunion (974) reste fermee.');
    }

    public function test_an_unusable_postal_code_is_never_open(): void
    {
        Config::set('services.jeuncy.match_departements', '66,11');

        // Une offre sans code postal n'est « ouverte » nulle part. C'est
        // pour cela que DiscoverService exige le code postal AVANT de
        // consulter le perimetre : « pas encore ouvert ici » serait faux et
        // inexploitable la ou « indique le code postal » dit quoi faire.
        $this->assertFalse(MatchPerimeter::isOpen(null));
        $this->assertFalse(MatchPerimeter::isOpen(''));
        $this->assertFalse(MatchPerimeter::isOpen('xx'));
    }
}
