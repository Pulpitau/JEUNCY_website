<?php

namespace Tests\Concerns;

use App\Http\Middleware\EnsureMatchAge;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * Outillage commun aux tests du lot « decouvrir-match ».
 *
 * POURQUOI CHARGER LES ROUTES ICI. Les cinq fichiers de routes du match ne
 * sont pas encore inclus dans routes/api.php : ce `require` appartient au
 * lot d'integration, qui fusionne les trois lots paralleles. Les tests les
 * chargent donc eux-memes, dans le meme groupe (prefixe `api`, middleware
 * `api`) que celui que l'integration leur donnera — ce qui a l'avantage de
 * prouver que chaque fichier est autonome.
 */
trait AideMatch
{
    protected function chargerLesRoutesDuMatch(): void
    {
        app('router')->aliasMiddleware('match.age', EnsureMatchAge::class);

        Route::middleware('api')->prefix('api')->group(function () {
            require base_path('routes/api/discover.php');
            require base_path('routes/api/interests.php');
            require base_path('routes/api/matches.php');
            require base_path('routes/api/external-interests.php');
            require base_path('routes/api/blocks-reports.php');
        });
    }

    // Perimetre du match ouvert sur le 66 (le defaut de production). Pose
    // explicitement : la cle de configuration appartient au lot A, et un
    // test ne doit pas dependre de l'ordre de fusion des lots.
    protected function ouvrirLePerimetre(string $valeur = '66'): void
    {
        Config::set('services.jeuncy.match_departements', $valeur);
    }

    protected function candidat(array $profil = [], array $compte = []): User
    {
        $user = User::factory()->candidate()->create($compte);

        CandidateProfile::factory()
            ->adult()
            ->located()
            ->create(array_merge(['user_id' => $user->id], $profil));

        return $user->fresh();
    }

    protected function employeur(array $entreprise = [], bool $verifiee = true): User
    {
        $user = User::factory()->company()->create();

        $factory = Company::factory()->located();
        if ($verifiee) {
            $factory = $factory->verified();
        }
        $factory->create(array_merge(['user_id' => $user->id], $entreprise));

        return $user->fresh();
    }

    protected function employeurCfa(array $organisation = []): User
    {
        $user = User::factory()->cfa()->create();

        CfaOrganization::factory()->verified()->located()
            ->create(array_merge(['user_id' => $user->id], $organisation));

        return $user->fresh();
    }

    protected function offrePubliee(User $employeur, array $attributs = []): JobOffer
    {
        $factory = JobOffer::factory()->published()->located();

        $lien = $employeur->company !== null
            ? ['company_id' => $employeur->company->id, 'cfa_organization_id' => null]
            : ['company_id' => null, 'cfa_organization_id' => $employeur->cfaOrganization?->id];

        return $factory->create(array_merge($lien, $attributs))->fresh();
    }

    /**
     * Jeton d'acces de cet utilisateur, avec remise a zero des gardes.
     *
     * PIEGE ATTRAPE ICI : le conteneur n'est pas reconstruit entre deux
     * requetes d'un meme test, et la garde d'authentification garde en
     * memoire le premier utilisateur qu'elle a resolu. Deux appels
     * successifs avec deux jetons differents partaient donc tous les deux
     * au nom du PREMIER — un test d'appartenance passait alors pour la
     * mauvaise raison (meme famille de piege que le cookie absent de
     * MobileAuthTest, CLAUDE.md §11).
     */
    protected function jeton(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return app(JwtService::class)->issueAccessToken($user);
    }

    protected function profilDe(User $user): CandidateProfile
    {
        return $user->candidateProfile()->firstOrFail();
    }
}
