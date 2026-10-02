<?php

namespace Database\Factories;

use App\Enums\NewsletterEditionStatus;
use App\Models\NewsletterEdition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterEdition>
 */
class NewsletterEditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'lettre-'.fake()->unique()->numberBetween(1, 100000),
            'subject' => 'La lettre Jeuncy',
            'html' => '<p>Salut, voila les nouvelles.</p>',
            'text' => 'Salut, voila les nouvelles.',
            'status' => NewsletterEditionStatus::BROUILLON,
        ];
    }

    // Etat par defaut, nomme explicitement : un test qui dit `brouillon()`
    // se lit mieux qu'un test qui compte sur le defaut de la fabrique.
    public function brouillon(): static
    {
        return $this->state(fn () => [
            'status' => NewsletterEditionStatus::BROUILLON,
            'prepared_at' => null,
        ]);
    }

    public function prete(): static
    {
        return $this->state(fn () => [
            'status' => NewsletterEditionStatus::PRETE,
            'prepared_at' => now(),
        ]);
    }

    public function envoyee(): static
    {
        return $this->state(fn () => [
            'status' => NewsletterEditionStatus::ENVOYEE,
            'prepared_at' => now()->subDay(),
            'sent_at' => now(),
        ]);
    }

    /** Avec un espace reserve, pour exercer la substitution. */
    public function avecCompteur(): static
    {
        return $this->state(fn () => [
            'html' => '<p>Il y a [[offres_total]] offres en ligne.</p>',
            'text' => 'Il y a [[offres_total]] offres en ligne.',
        ]);
    }

    /** Avec un espace reserve INCONNU : l'envoi doit refuser de partir. */
    public function avecGabaritCasse(): static
    {
        return $this->state(fn () => [
            'html' => '<p>Il y a [[ofres_total]] offres en ligne.</p>',
            'text' => 'Il y a [[ofres_total]] offres en ligne.',
        ]);
    }
}
