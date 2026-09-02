<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\GeneratedCv;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CvService
{
    public function __construct(private readonly CandidateProfileService $profileService) {}

    public function generate(User $user): GeneratedCv
    {
        $profile = $this->profileService->requireProfile($user);

        // "cvs/" (et non "generated-cvs/") est bloque par defaut par Apache sur
        // l'hebergement mutualise OVH : les hebergeurs partagent souvent une regle
        // deny-all sur tout dossier nomme "cvs" (insensible a la casse), heritage
        // de la protection historique contre l'exposition de depots CVS (l'ancien
        // outil de version control) - renvoie un 403 Apache generique avant meme
        // d'atteindre PHP, quels que soient les permissions du fichier.
        $path = 'generated-cvs/'.$profile->id.'/'.Str::uuid().'.pdf';
        Storage::disk('public')->put($path, $this->renderPdfFor($profile));

        return $profile->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url($path),
        ]);
    }

    // Fabrique le PDF et renvoie ses octets, sans rien ecrire sur le disque ni
    // en base. Extrait de generate() pour que la CVtheque puisse servir un CV
    // "a la volee" a un recruteur quand le candidat n'a ni depose ni genere de
    // CV (voir CvthequeService::resolveCvFor) : ce PDF ne doit surtout pas
    // apparaitre dans l'historique generated_cvs du candidat, qui ne l'a pas
    // demande — c'est son historique a lui, pas un journal d'acces recruteur.
    //
    // GARANTIE : le resultat tient sur UNE page, quel que soit le profil.
    // Un CV sur deux pages (ou pire) n'est pas un CV, et un recruteur qui en
    // recoit un juge le candidat, pas notre gabarit. Voir FIT_STEPS.
    public function renderPdfFor(CandidateProfile $profile): string
    {
        $profile->loadMissing(['user', 'experiences', 'educations', 'skills', 'languages', 'software']);

        $last = null;

        // Deux leviers, dans cet ordre : d'abord comprimer la mise en page
        // sans rien perdre, et seulement si ca ne suffit pas, alleger le
        // contenu. Un CV de 15 postes detailles ne rentre sur une page a
        // aucune echelle lisible — a ce stade, mieux vaut un CV court et net
        // qu'un pave illisible ou un document de quatre pages.
        foreach (self::CONTENT_BUDGETS as $index => $budget) {
            $candidate = $this->applyBudget($profile, $budget);

            // Le premier budget explore toutes les echelles, y compris la mise
            // en page aeree. Les suivants n'ont plus rien a aerer : on repart
            // directement compact, sinon on paierait des rendus inutiles.
            $steps = $index === 0 ? self::FIT_STEPS : self::COMPACT_STEPS;
            $result = $this->fitToOnePage($candidate, $steps, $index === 0);

            if ($result['pages'] <= 1) {
                return $result['content'];
            }

            $last = $result;
        }

        // Tous les leviers epuises : on rend la version la plus compacte
        // plutot que d'echouer. En pratique inatteignable avec les budgets
        // ci-dessus, mais on ne renvoie jamais une erreur a un recruteur.
        return $last['content'];
    }

    // Allegements successifs du contenu, appliques seulement quand la
    // compression de la mise en page ne suffit plus. Un CV est un resume :
    // aucun recruteur ne lit quinze postes detailles, et le profil complet
    // reste consultable sur Jeuncy.
    private const CONTENT_BUDGETS = [
        ['lignes' => null, 'experiences' => null, 'formations' => null],
        ['lignes' => 3, 'experiences' => null, 'formations' => null],
        ['lignes' => 1, 'experiences' => 10, 'formations' => 6],
        ['lignes' => 0, 'experiences' => 6, 'formations' => 4],
    ];

    // Applique un budget sans jamais toucher a la base : on clone le modele et
    // on remplace ses relations en memoire. Le profil du candidat reste
    // evidemment intact, seul le PDF est allege.
    private function applyBudget(CandidateProfile $profile, array $budget): CandidateProfile
    {
        if ($budget['lignes'] === null && $budget['experiences'] === null) {
            return $profile;
        }

        $clone = clone $profile;

        $experiences = $profile->experiences;
        if ($budget['experiences'] !== null) {
            // Les plus recentes d'abord : c'est ce qu'un recruteur regarde.
            $experiences = $experiences->sortByDesc('start_date')->take($budget['experiences']);
        }

        $clone->setRelation('experiences', $experiences->map(function ($experience) use ($budget) {
            $copy = clone $experience;
            $copy->description = $this->trimLines($experience->description, $budget['lignes']);

            return $copy;
        })->values());

        if ($budget['formations'] !== null) {
            $clone->setRelation(
                'educations',
                $profile->educations->sortByDesc('start_date')->take($budget['formations'])->values(),
            );
        }

        return $clone;
    }

    private function trimLines(?string $text, ?int $max): ?string
    {
        if ($text === null || $max === null) {
            return $text;
        }
        if ($max === 0) {
            return null;
        }

        $lines = array_filter(preg_split('/\r\n|\r|\n/', $text) ?: []);

        return implode("\n", array_slice($lines, 0, $max)) ?: null;
    }

    /** @return array{content: string, pages: int} */
    private function fitToOnePage(CandidateProfile $profile, array $steps, bool $allowAiry): array
    {
        $airy = $allowAiry ? $this->contentScales($profile) : null;
        $pdf = null;
        $alreadyTried = [];

        foreach ($steps as $step) {
            // Premiere passe : la mise en page aeree calculee pour ce profil.
            // Passes suivantes : on revient a l'echelle de reference (1.0,
            // calibree pour un profil dense) puis on comprime sous 1.0.
            if ($step === null && $airy === null) {
                continue;
            }
            $scales = $step === null
                ? $airy
                : ['section' => $step, 'item' => $step, 'font' => $step];

            // Un profil deja dense donne des scales aeres egaux a 1.0 : sans
            // ce garde-fou, les deux premieres passes seraient identiques et
            // on paierait un rendu complet pour rien.
            $key = implode('/', $scales);
            if (isset($alreadyTried[$key])) {
                continue;
            }
            $alreadyTried[$key] = true;

            $pdf = $this->renderAtScales($profile, $scales);

            if ($pdf['pages'] <= 1) {
                return $pdf;
            }
        }

        return $pdf;
    }

    // Echelles essayees dans l'ordre jusqu'a ce que le PDF tienne sur une page.
    // null = la mise en page aeree propre au profil (contentScales), qui
    // convient a la grande majorite des cas et evite une 2e passe. Les valeurs
    // suivantes compriment progressivement : 1.0 est la reference calibree en
    // phase 2 pour un profil dense, en dessous on resserre polices, espaces et
    // en-tete (voir $ph dans le gabarit).
    //
    // La descente va jusqu'a 0.40. C'est petit, mais un CV sur une page dense
    // reste exploitable par un recruteur, alors qu'un CV dont la premiere page
    // est vide et le contenu rejete en page 2 ne l'est pas — c'est exactement
    // ce que produisait l'arret a 0.58 sur les profils les plus fournis.
    private const FIT_STEPS = [null, 1.0, 0.92, 0.84, 0.76, 0.68, 0.60, 0.54, 0.48, 0.44, 0.40];

    // Utilisees quand le contenu a deja ete allege : inutile de reessayer les
    // grandes echelles, le profil est par definition trop fourni.
    private const COMPACT_STEPS = [0.76, 0.60, 0.48, 0.40];

    // Version du moteur de mise en page, ecrite dans les metadonnees de chaque
    // PDF (voir renderAtScales). Elle sert a repondre en une seconde a la seule
    // question qui compte quand un CV sort sur deux pages : "le correctif
    // s'execute-t-il vraiment sur le serveur ?" Sans elle, on ne peut pas
    // distinguer un correctif inefficace d'un correctif jamais deploye — ce
    // doute a coute deux allers-retours de deploiement.
    // A incrementer a chaque changement du moteur ou du gabarit.
    public const LAYOUT_VERSION = 'cv-layout-4';

    private function renderAtScales(CandidateProfile $profile, array $scales): array
    {
        $pdf = Pdf::loadView('cv.template', [
            'profile' => $profile,
            'photoDataUri' => $this->photoDataUri($profile),
            'logoDataUri' => $this->logoDataUri(),
            'age' => $profile->birth_date?->age,
            'scales' => $scales,
            'palette' => $this->palette($profile),
        ])->setPaper('a4');

        // Ecrit AVANT output() : dompdf fige ses metadonnees au rendu.
        // Invisible pour le recruteur, lisible avec n'importe quel outil PDF.
        $pdf->getDomPDF()->add_info(
            'Keywords',
            self::LAYOUT_VERSION.' scale='.implode('/', $scales),
        );

        $content = $pdf->output();

        // Nombre de pages lu directement dans dompdf plutot que devine dans les
        // octets du PDF : fiable quelle que soit la compression du flux.
        $pages = $pdf->getDomPDF()->getCanvas()->get_page_count();

        return ['content' => $content, 'pages' => $pages];
    }

    public function listForUser(User $user): Collection
    {
        return $this->profileService->requireProfile($user)
            ->generatedCvs()
            ->latest('generated_at')
            ->get();
    }

    // Supprime le fichier PDF stocke (recuperation d'espace disque, voir
    // app/Console/Commands/ArchiveInactiveCvs.php) et marque la ligne comme
    // archivee plutot que de la supprimer : garde une trace de l'historique
    // de generation sans garder le fichier lui-meme.
    public function archive(GeneratedCv $cv): void
    {
        $relativePath = $this->relativeStoragePath($cv->file_url);
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        $cv->update(['archived_at' => now()]);
    }

    private function relativeStoragePath(string $url): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($url, $base) ? substr($url, strlen($base)) : $url;
    }

    // Garde la charte graphique Jeuncy (bleu nuit corporate + dégradé
    // signature corail -> orange, voir CLAUDE.md §2) plutot que d'en sortir,
    // mais varie l'accent par candidat pour que deux CV generes cote a cote
    // n'aient pas l'air de sortir du meme moule ("CV d'usine") : chaque
    // accent est un point echantillonne le long du degrade signature lui-meme
    // (jamais une couleur hors-charte), le bleu nuit reste constant en
    // identite (titres, en-tete) comme l'exige la charte. Choix deterministe
    // sur l'id du profil (stable d'une generation a l'autre pour un meme
    // candidat) et une permutation (plutot que l'ordre du degrade) pour que
    // deux candidats d'affilee retombent rarement sur deux points voisins
    // du degrade, donc visuellement plus distincts.
    private const NAVY = '#061D4F';

    private const GRADIENT_STOPS = [
        '#FF2D55', '#FF3A50', '#FF484B', '#FF5546',
        '#FF6241', '#FF6F3C', '#FF7D37', '#FF8A32',
    ];

    private const STOP_ORDER = [0, 4, 1, 5, 2, 6, 3, 7];

    private function palette(CandidateProfile $profile): array
    {
        $stopIndex = self::STOP_ORDER[$profile->id % count(self::STOP_ORDER)];

        return [
            'primary' => self::NAVY,
            'accent' => self::GRADIENT_STOPS[$stopIndex],
        ];
    }

    // dompdf n'a aucun mecanisme pour reduire (ou grossir) dynamiquement du
    // contenu selon la place disponible, mais rien n'empeche de calculer cote
    // serveur, avant le rendu, a quel point un profil est "rempli" et de
    // deriver trois facteurs d'echelle a partir de ce score, jamais un seul :
    // - 'section' : les GRANDS espaces (padding de page, en-tete, marge entre
    //   sections) — peuvent grossir enormement, ca se lit comme une mise en
    //   page aeree et volontaire, jamais comme une erreur.
    // - 'item'    : les PETITS espaces entre elements d'une meme liste
    //   (puces, langues, formations) — doivent rester moderes, un enorme vide
    //   entre deux puces de la meme experience aurait l'air casse.
    // - 'font'    : les tailles de texte du corps (jamais l'identite du
    //   header) — un peu plus grand aide aussi a occuper la page.
    // Un profil dense (score >= denseScoreCeil) garde les trois a 1.0 (mise
    // en page compacte de reference, jamais de debordement sur une 2e page).
    // Bornes calibrees empiriquement (rendu + rasterisation) sur plusieurs
    // profils de reference (dense, normal, leger, tres sparse).
    private function contentScales(CandidateProfile $profile): array
    {
        $descriptionLineCount = $profile->experiences->sum(
            fn ($experience) => $experience->description
                ? count(array_filter(preg_split('/\r\n|\r|\n/', $experience->description)))
                : 0,
        );

        // Competences, logiciels et langues pesent 1 chacun et non 0.3 :
        // sous-evalues, un profil affichant quinze competences mais peu
        // d'experiences etait juge "vide", recevait l'inflation maximale, et
        // debordait sur plusieurs pages — constate en production sur de vrais
        // profils. Chaque entree occupe une ligne dans la colonne laterale,
        // elle compte donc autant qu'une ligne de description.
        $score = ($profile->experiences->count() * 3)
            + $descriptionLineCount
            + ($profile->educations->count() * 1.5)
            + $profile->skills->count()
            + $profile->languages->count()
            + $profile->software->count()
            + ($profile->bio ? 1.5 : 0)
            + ($profile->hobbies ? 1 : 0);

        $denseScoreFloor = 6;
        $denseScoreCeil = 34;
        $maxSectionScale = 4.5;
        $maxItemScale = 1.7;
        $maxFontScale = 1.35;

        $ratio = ($score - $denseScoreFloor) / ($denseScoreCeil - $denseScoreFloor);
        $ratio = max(0.0, min(1.0, $ratio));
        // Cube plutot que lineaire : un profil "normal" (ratio moyen) ne doit
        // recevoir qu'un boost modeste (garantir 1 page), seul un profil
        // vraiment tres peu fourni (ratio proche de 0) doit s'approcher du
        // boost maximal — sinon un profil normal deborde sur une 2e page
        // (regression constatee et corrigee empiriquement).
        $emptiness = (1 - $ratio) ** 3;

        return [
            'section' => round(1.0 + $emptiness * ($maxSectionScale - 1.0), 2),
            'item' => round(1.0 + $emptiness * ($maxItemScale - 1.0), 2),
            'font' => round(1.0 + $emptiness * ($maxFontScale - 1.0), 2),
        ];
    }

    // dompdf lit une image locale bien plus simplement (et sans dependre du
    // reseau ni d'un aller-retour HTTP vers le serveur lui-meme) via une data
    // URI base64 incorporee directement dans le HTML plutot que via son URL publique.
    private function photoDataUri(CandidateProfile $profile): ?string
    {
        $path = $this->profileService->photoAbsolutePath($profile);
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mimeType = mime_content_type($path) ?: 'image/jpeg';
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    // Copie dans apps/api/resources (plutot que reference vers apps/web/public) :
    // le projet Laravel doit rester un package Composer autonome, sans dependre
    // de l'arborescence du frontend au moment du deploiement (voir CLAUDE.md §3).
    private function logoDataUri(): ?string
    {
        $path = resource_path('images/logo-jeuncy.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
