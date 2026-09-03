<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Software;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

// Lecture d'un CV PDF pour remplir le profil du candidat.
//
// C'est le parcours d'entree principal : le candidat importe le CV qu'il a
// fait AILLEURS (Canva, Word, LinkedIn, Europass). L'extraction doit donc
// tenir sur des mises en page qu'on ne choisit pas, et dans les deux langues.
//
// Aucune IA n'est disponible ici (meme limitation que Stripe/Google OAuth dans
// CLAUDE.md) : l'extraction est structurelle. Une entree est ancree par sa
// DATE — le seul repere commun a tous les gabarits — et son identite est lue
// autour de cette ancre, dans l'ordre ou le CV la presente.
//
// Principe directeur : ne jamais inventer. Une entree douteuse n'est pas
// proposee. Le candidat corrige plus vite trois entrees justes qu'il ne
// supprime six entrees dont deux sont fausses.
class CvImportService
{
    private const MIN_REFERENTIAL_LENGTH = 3;

    private const MAX_SUGGESTIONS = 25;

    // Limite exacte acceptee par le profil (StoreExperienceRequest) : une
    // description plus longue faisait rejeter l'entree entiere par l'API.
    private const MAX_DESCRIPTION_LENGTH = 2000;

    // Mois francais ET anglais, formes longues AVANT les courtes : sans cet
    // ordre, "jui" avale "juillet" et "aug" avale "august", le reste du mot
    // empeche alors la date de matcher et l'entree disparait entierement.
    private const MONTHS = 'janvier|january|janv|jan'
        .'|f[eé]vrier|february|f[eé]vr|f[eé]v|feb'
        .'|mars|march|mar'
        .'|avril|april|avr|apr'
        .'|mai|may'
        .'|juillet|july|juil|jul'
        .'|juin|june|jui|jun'
        .'|ao[uû]t|august|ao[uû]|aug'
        .'|septembre|september|sept|sep'
        .'|octobre|october|octo|oct'
        .'|novembre|november|nov'
        .'|d[eé]cembre|december|d[eé]c';

    // Mots qui designent une fin ouverte. "current" et "ongoing" sont produits
    // tels quels par l'editeur Europass en ligne.
    private const ONGOING = "aujourd'hui|aujourdhui|a ce jour|en cours|actuel(?:lement)?|act"
        .'|present(?:ly)?|current(?:ly)?|ongoing|now|today';

    public function parse(UploadedFile $file): array
    {
        $text = (new Parser)->parseFile($file->getRealPath())->getText();

        $sections = $this->splitIntoSections($text);
        $name = $this->extractName($text);

        return [
            'first_name' => $name['first'],
            'last_name' => $name['last'],
            'email' => $this->extractEmail($text),
            'phone' => $this->extractPhone($text),
            'postal_code' => $this->extractPostalCode($text),
            'linkedin_url' => $this->extractLinkedinUrl($text),
            'driving_license' => $this->extractDrivingLicense($text),
            'skills' => $this->matchReferential($text, Skill::query()->pluck('name')->all()),
            'software' => $this->matchReferential($text, Software::query()->pluck('name')->all()),
            'languages' => $this->extractLanguages($text),
            'experiences' => $this->collectEntries($sections, $text, 'experience'),
            'educations' => $this->collectEntries($sections, $text, 'education'),
            'raw_text' => trim($text),
        ];
    }

    /** @return string[] */
    private function toLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return array_values(array_filter(
            array_map(fn (string $l) => trim(preg_replace('/\s+/u', ' ', $l) ?? ''), $lines),
            fn (string $l) => $l !== '',
        ));
    }

    // --- Rubriques --------------------------------------------------------

    // "stages" et "alternances" ne figurent volontairement PAS seuls : un
    // intitule de poste commencant par "Stage chez..." etait pris pour un
    // titre de rubrique, ce qui decoupait le CV au mauvais endroit.
    private const SECTION_HEADINGS = [
        'experiences' => 'exp[ée]riences?(?: professionnelles?| pro| et stages?)?'
            .'|parcours(?: professionnel)?|carri[èe]re|emplois'
            .'|work experience|professional experience|employment(?: history)?|career',
        'educations' => 'formations?|dipl[ôo]mes?|[ée]tudes|scolarit[ée]|cursus'
            .'|education|academic background|qualifications',
        'languages' => 'langues?|languages?',
        'skills' => 'comp[ée]tences?|savoir[- ]faire|savoir[- ][êe]tre|qualit[ée]s?|skills',
        'software' => 'logiciels?|outils?|informatique|software|tools',
        'hobbies' => "centres? d'int[ée]r[êe]ts?|loisirs|int[ée]r[êe]ts?|hobbies|interests",
    ];

    // Mots qui designent un DIPLOME. Testes en priorite sur l'intitule : c'est
    // lui qui dit si l'entree est une formation, pas l'organisation. Un
    // service civique effectue dans un lycee reste une experience.
    private const DIPLOMA_HINTS = 'bts|but|dut|cap|bep|bac(?:calaur[ée]at)?|licence|master|mast[èe]re'
        .'|bachelor|doctorat|dipl[ôo]me|titre pro(?:fessionnel)?|brevet|mention compl[ée]mentaire'
        .'|degree|certificate|formation';

    // Mots qui designent un ETABLISSEMENT. Indice plus faible : utilise
    // seulement quand l'intitule ne tranche pas.
    private const SCHOOL_HINTS = 'lyc[ée]e|coll[èe]ge|universit[ée]|[ée]cole|institut|iut|cfa|greta|afpa'
        .'|academy|university|school|college|campus';

    private function splitIntoSections(string $text): array
    {
        // Recherche dans le texte d'origine, accents inclus dans les motifs :
        // passer par une version sans accents decalerait toutes les positions
        // (un caractere accente occupe 2 octets en UTF-8).
        $marks = [];

        foreach (self::SECTION_HEADINGS as $key => $pattern) {
            // Un intitule occupe SA ligne, ou est colle a son contenu par
            // l'extraction PDF ("PARCOURS PROFESSIONNELJUN 2025"). D'ou
            // l'absence de \b final et l'exigence d'une suite non minuscule.
            // (?-i) neutralise le drapeau /i dans ce seul lookahead : sinon
            // [a-z] accepte aussi les majuscules et le test ne filtre plus rien.
            $notLowercase = '(?!(?-i)[a-zàâäçéèêëîïôöûùüÿñæœ])';
            if (! preg_match_all('/(?:^|\n)[ \t]*('.$pattern.')'.$notLowercase.'[ \t]*:?/iu', $text, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            // A egalite, l'intitule en majuscules l'emporte : c'est la mise en
            // forme quasi systematique des titres de rubrique, et elle
            // distingue le vrai titre d'une occurrence en prose.
            $best = null;
            foreach ($all as $match) {
                $word = $match[1][0];
                $isUpper = $word === mb_strtoupper($word);
                if ($best === null || ($isUpper && ! $best['upper'])) {
                    $best = ['upper' => $isUpper, 'start' => $match[0][1] + strlen($match[0][0])];
                }
            }

            $marks[] = ['key' => $key, 'start' => $best['start']];
        }

        if ($marks === []) {
            return [];
        }

        usort($marks, fn ($a, $b) => $a['start'] <=> $b['start']);

        // Position de fin de l'en-tete : tout ce qui precede la premiere
        // rubrique. Sert a exclure nom, email et telephone du balayage global.
        $sections = ['__header_end' => (string) $marks[0]['start']];

        foreach ($marks as $i => $mark) {
            $end = $marks[$i + 1]['start'] ?? strlen($text);
            $sections[$mark['key']] = substr($text, $mark['start'], $end - $mark['start']);
        }

        return $sections;
    }

    // --- Collecte des entrees ---------------------------------------------

    // Les deux sources sont exploitees puis fusionnees.
    //
    // La rubrique seule ne suffit pas : sur un CV a deux colonnes,
    // l'extraction melange l'ordre de lecture et l'intitule se retrouve
    // parfois APRES son contenu, d'ou des frontieres fausses. Sur un vrai CV,
    // la rubrique ne contenait que 3 des 5 experiences pourtant toutes
    // presentes avec leurs dates.
    //
    // Le balayage global seul ne suffit pas non plus : il classe par
    // mots-cles, moins sur que de savoir sous quel intitule l'entree figurait.
    // La rubrique passe donc en premier et gagne au dedoublonnage.
    private function collectEntries(array $sections, string $text, string $type): array
    {
        $key = $type === 'experience' ? 'experiences' : 'educations';

        return $this->mergeEntries(
            $this->extractEntries($sections[$key] ?? '', $type),
            $this->extractEntriesWithoutSections($sections, $text, $type),
        );
    }

    // Balayage du document entier, sans s'appuyer sur les intitules.
    //
    // L'EN-TETE est retire avant balayage : il contient le nom, l'email, le
    // telephone et parfois une date de naissance, dont le service fabriquait
    // des experiences fantomes ("Curriculum Vitae", une adresse email en
    // guise d'intitule). Constate sur 4 CV sur 5.
    private function extractEntriesWithoutSections(array $sections, string $text, string $type): array
    {
        $headerEnd = (int) ($sections['__header_end'] ?? 0);
        $body = $headerEnd > 0 ? substr($text, $headerEnd) : $text;

        return array_values(array_filter(
            $this->extractEntries($body, $type),
            function (array $entry) use ($type) {
                $intitule = $entry['title'] ?? $entry['degree'] ?? '';
                $organisation = (string) ($entry['company'] ?? $entry['school'] ?? '');
                $estFormation = $this->looksLikeEducation($intitule, $organisation);

                return $type === 'education' ? $estFormation : ! $estFormation;
            },
        ));
    }

    // L'INTITULE tranche en premier. Un "Service civique" effectue dans un
    // lycee est une experience, pas une formation : classer sur
    // l'organisation seule faisait disparaitre ces emplois des experiences.
    private function looksLikeEducation(string $intitule, string $organisation): bool
    {
        if (preg_match('/\b(?:'.self::DIPLOMA_HINTS.')/iu', $intitule)) {
            return true;
        }

        return (bool) preg_match('/\b(?:'.self::SCHOOL_HINTS.')/iu', $organisation);
    }

    // Deux entrees sont identiques si elles partagent la date de debut et un
    // intitule qui commence pareil : l'extraction produit parfois la meme
    // entree tronquee differemment selon la source.
    private function mergeEntries(array $prioritaires, array $complements): array
    {
        $signature = function (array $entry): string {
            $intitule = $entry['title'] ?? $entry['degree'] ?? '';

            return ($entry['start_date'] ?? '').'|'.Str::lower(Str::limit(Str::ascii($intitule), 25, ''));
        };

        $vues = [];
        $resultat = [];

        foreach ([$prioritaires, $complements] as $liste) {
            foreach ($liste as $entry) {
                $cle = $signature($entry);
                if (isset($vues[$cle])) {
                    continue;
                }
                $vues[$cle] = true;
                $resultat[] = $entry;
            }
        }

        return $resultat;
    }

    // --- Decoupage en entrees ---------------------------------------------

    // Une entree = une date, plus l'identite qui l'accompagne. L'identite peut
    // se trouver AVANT la date (disposition francaise classique), APRES
    // (gabarits Canva et anglophones), ou SUR la meme ligne. Le service
    // determine l'orientation entree par entree au lieu de la supposer :
    // supposer "avant" decalait systematiquement chaque entree d'un cran sur
    // les CV date-en-tete, chacune recevant l'identite de la precedente.
    private function extractEntries(string $sectionText, string $type): array
    {
        $lines = $this->toLines($sectionText);

        $anchors = [];
        foreach ($lines as $i => $line) {
            $dates = $this->extractDateRange($line);
            if ($dates !== null) {
                $anchors[] = ['index' => $i, 'dates' => $dates, 'line' => $line];
            }
        }

        $orientation = $this->detectOrientation($lines, $anchors);

        $entries = [];
        $consumed = [];

        foreach ($anchors as $n => $anchor) {
            $previous = $n > 0 ? $anchors[$n - 1]['index'] : -1;
            $next = $anchors[$n + 1]['index'] ?? count($lines);

            $inline = $this->stripDates($anchor['line']);

            // Une ligne deja utilisee comme identite par l'entree precedente
            // ne peut pas resservir : sans cela, deux entrees consecutives
            // recevaient le meme intitule.
            $before = [];
            for ($i = $previous + 1; $i < $anchor['index']; $i++) {
                if (! isset($consumed[$i]) && $this->extractDateRange($lines[$i]) === null) {
                    $before[] = ['index' => $i, 'text' => $lines[$i]];
                }
            }

            $after = [];
            for ($i = $anchor['index'] + 1; $i < $next; $i++) {
                $after[] = ['index' => $i, 'text' => $lines[$i]];
            }

            $identity = $this->pickIdentity($inline, $after, $before, $orientation);
            if ($identity === null) {
                continue;
            }

            foreach ($identity['used'] as $index) {
                $consumed[$index] = true;
            }

            $entry = [
                'start_date' => $anchor['dates']['start'],
                'end_date' => $anchor['dates']['end'],
            ];

            if ($type === 'experience') {
                $entry['title'] = $identity['title'];
                $entry['company'] = $identity['organisation'];
                $entry['description'] = $identity['description'];
            } else {
                $entry['degree'] = $identity['title'];
                $entry['school'] = $identity['organisation'];
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    // Choisit l'identite de l'entree parmi ce qui entoure la date.
    //
    // @param  array<int, array{index:int, text:string}>  $after
    // @param  array<int, array{index:int, text:string}>  $before
    // @return array{title:string, organisation:?string, description:?string, used:int[]}|null
    // Un CV emploie UNE disposition, pas une par entree. La deviner a chaque
    // fois faisait donner a chaque entree l'identite de la precedente sur les
    // gabarits date-en-tete : les lignes qui precedent la date y sont la
    // description de l'entree d'avant, et elles ressemblent assez a un
    // intitule pour l'emporter.
    //
    // Signal decisif et stable : la PREMIERE entree. Sur un CV date-en-tete,
    // la rubrique s'ouvre par la date, il n'y a donc rien d'exploitable avant
    // elle. Sur un CV classique, l'intitule et l'organisation la precedent.
    //
    // @param  string[]  $lines
    // @param  array<int, array{index:int}>  $anchors
    private function detectOrientation(array $lines, array $anchors): string
    {
        if ($anchors === []) {
            return 'before';
        }

        for ($i = $anchors[0]['index'] - 1; $i >= 0; $i--) {
            if ($this->couldBeIdentity($lines[$i]) && $this->looksLikeATitle($this->shorten($lines[$i]))) {
                return 'before';
            }
        }

        return 'after';
    }

    private function pickIdentity(string $inline, array $after, array $before, string $orientation = 'before'): ?array
    {
        // 1. La ligne de dates porte elle-meme l'identite ("Livreur - La Poste
        //    - 12/2025"). On la decoupe sur ses separateurs : sans ce
        //    decoupage, l'intitule recevait la ligne entiere et depassait le
        //    filtre de vraisemblance, l'entree etant alors abandonnee.
        $aucuneEntrepriseCollee = $this->splitGluedOrganisation($after[0]['text'] ?? '') === null
            && $this->splitGluedOrganisation($before[count($before) - 1]['text'] ?? '') === null;

        if ($inline !== '' && $aucuneEntrepriseCollee && $this->couldBeIdentity($inline)) {
            $parts = $this->splitOnSeparators($inline);
            if ($parts !== [] && $this->looksLikeATitle($parts[0])) {
                return [
                    'title' => $this->shorten($parts[0]),
                    'organisation' => isset($parts[1]) ? $this->shorten($parts[1]) : null,
                    'description' => $this->joinDescription($this->texts($after)),
                    'used' => [],
                ];
            }
        }

        // 2. L'identite suit la date (gabarits Canva et anglophones) OU la
        //    precede (disposition francaise classique). On retient le cote le
        //    plus convaincant plutot que d'en privilegier un a l'aveugle.
        $apres = $this->readIdentityBlock($after, true);
        $avant = $this->readIdentityBlock($before, false);

        // L'entreprise en capitales collee au poste (score 3) est le signal le
        // plus fiable qui existe : elle l'emporte sur l'orientation. Sinon
        // c'est l'orientation de la rubrique qui tranche, et le cote oppose ne
        // sert que de repli quand le cote attendu ne donne rien.
        $prefere = $orientation === 'after' ? $apres : $avant;
        $repli = $orientation === 'after' ? $avant : $apres;

        $meilleur = match (true) {
            ($apres['score'] ?? 0) === 3 => $apres,
            ($avant['score'] ?? 0) === 3 => $avant,
            $prefere !== null => $prefere,
            default => $repli,
        };

        if ($meilleur === null) {
            return null;
        }

        // La description ne peut venir que d'apres la date : ce qui precede
        // appartient a la mise en page, pas au recit de l'entree.
        $description = $meilleur['side'] === 'after'
            ? $this->joinDescription(array_slice($this->texts($after), count($meilleur['used'])))
            : $this->joinDescription($this->texts($after));

        return [
            'title' => $meilleur['title'],
            'organisation' => $meilleur['organisation'],
            'description' => $description,
            'used' => $meilleur['used'],
        ];
    }

    /** @param  array<int, array{index:int, text:string}>  $block */
    private function texts(array $block): array
    {
        return array_map(fn (array $l) => $l['text'], $block);
    }

    // Lit une ou deux lignes comme "intitule + organisation", en devinant
    // laquelle est laquelle, et note la confiance obtenue.
    //
    // @param  array<int, array{index:int, text:string}>  $block
    // @return array{title:string, organisation:?string, used:int[], score:int, side:string}|null
    private function readIdentityBlock(array $block, bool $isAfter): ?array
    {
        if ($block === []) {
            return null;
        }

        // Cote "avant", l'identite est ce qui touche la date, donc les
        // DERNIERES lignes ; cote "apres", les PREMIERES.
        $candidates = $isAfter ? array_slice($block, 0, 2) : array_slice($block, -2);

        $side = $isAfter ? 'after' : 'before';

        // L entreprise collee au poste est cherchee AVANT tout filtrage de
        // longueur : ces lignes sont longues precisement parce que
        // l extraction PDF y a colle la description, et les ecarter revenait a
        // perdre le signal le plus fiable dont on dispose.
        foreach ($candidates as $rang => $candidate) {
            $glued = $this->splitGluedOrganisation($candidate['text']);
            if ($glued !== null) {
                return $glued + [
                    'used' => array_map(fn (array $l) => $l['index'], array_slice($candidates, 0, $rang + 1)),
                    'score' => 3,
                    'side' => $side,
                ];
            }
        }

        $lines = array_values(array_filter(
            $candidates,
            fn (array $l) => $this->couldBeIdentity($l['text']),
        ));

        if ($lines === []) {
            return null;
        }

        $first = $this->shorten($this->splitOnSeparators($lines[0]['text'])[0] ?? $lines[0]['text']);
        if (! $this->looksLikeATitle($first)) {
            return null;
        }

        $second = isset($lines[1])
            ? $this->shorten($this->splitOnSeparators($lines[1]['text'])[0] ?? $lines[1]['text'])
            : null;

        // Les CV placent tantot l'intitule avant l'organisation, tantot
        // l'inverse. Une ligne entierement en capitales, ou qui nomme un
        // etablissement, est l'organisation.
        // Deux indices, du plus sur au moins sur : la seconde ligne nomme un
        // poste alors que la premiere non ; ou la premiere ligne est une
        // organisation manifeste (capitales, etablissement scolaire).
        $premierEstOrganisation = $second !== null
            && (
                ($this->looksLikeAJob($second) && ! $this->looksLikeAJob($first))
                || ($this->looksLikeOrganisation($first) && ! $this->looksLikeOrganisation($second))
            );

        return [
            'title' => $premierEstOrganisation ? $second : $first,
            'organisation' => $premierEstOrganisation ? $first : $second,
            'used' => array_map(
                fn (array $l) => $l['index'],
                array_slice($lines, 0, $second === null ? 1 : 2),
            ),
            'score' => $second === null ? 1 : 2,
            'side' => $side,
        ];
    }

    // Une ligne d'identite n'est ni une coordonnee, ni un intitule de
    // rubrique, ni une phrase. Ce filtre est ce qui empeche l'en-tete du CV et
    // les titres de rubrique de devenir des experiences fantomes.
    private function couldBeIdentity(string $line): bool
    {
        $line = trim($line);

        if ($line === '' || mb_strlen($line) > 120) {
            return false;
        }
        if (preg_match('#@|https?://|www\.#iu', $line)) {
            return false;
        }
        if (preg_match('/^(?:\+?\d[\d .\-]{7,})$/u', $line)) {
            return false;
        }

        return ! $this->looksLikeSectionHeading($line);
    }

    // Vocabulaire des postes qu'occupent reellement les candidats de Jeuncy.
    // Sert a savoir laquelle des deux lignes est l'intitule quand le CV les
    // presente dans l'ordre inverse (organisation puis poste) : sans cela,
    // "Transdev / Agent d'exploitation" donnait entreprise "Agent
    // d'exploitation" et poste "Transdev".
    private const JOB_HINTS = 'agent|employ[ée]|vendeu|serveu|assistant|charg[ée]|responsable'
        .'|technicien|stagiaire|alternant|apprenti|animateur|animatrice|caissi'
        .'|livreur|manutention|commercial|conseill|h[ôo]te|h[ôo]tesse|equipier|[ée]quipier'
        .'|preparateur|pr[ée]parateur|ouvrier|b[ée]n[ée]vole|volontaire|stage|job'
        .'|manager|developp|d[ée]velopp|community|secr[ée]taire|barman|cuisinier|plongeur';

    private function looksLikeAJob(string $value): bool
    {
        return (bool) preg_match('/\b(?:'.self::JOB_HINTS.')/iu', $value);
    }

    private function looksLikeOrganisation(string $value): bool
    {
        $value = trim($value);

        if ($value !== '' && $value === mb_strtoupper($value) && preg_match('/\p{L}{3,}/u', $value)) {
            return true;
        }

        return (bool) preg_match('/\b(?:'.self::SCHOOL_HINTS.')/iu', $value);
    }

    // "LOVISAResponsable de Magasin" devient organisation "LOVISA", intitule
    // "Responsable de Magasin".
    //
    // Exige qu'il n'y ait AUCUN espace a la frontiere : c'est ce qui distingue
    // un collage d'extraction PDF d'un nom compose legitime. Sans cette
    // condition, "SNCF Voyageurs", "BTS Management" ou "IUT Lyon 3" etaient
    // decoupes de travers et l'intitule du diplome disparaissait.
    //
    // @return array{title:string, organisation:string}|null
    private function splitGluedOrganisation(string $line): ?array
    {
        if (! preg_match('/^(\p{Lu}[\p{Lu}\s&\'.\-]{1,56}\p{Lu})(\p{Lu}\p{Ll}.+)$/u', $line, $m)) {
            return null;
        }

        $organisation = trim($m[1], " \t.-'&");
        $rest = trim($m[2]);
        $title = $this->splitOnSeparators($rest)[0] ?? $rest;

        // Un intitule de rubrique colle a la prose qui le suit
        // ("PROFILDynamique et orientee clients...") a exactement la meme forme
        // qu une entreprise collee a un poste. Sans ce garde-fou, la rubrique
        // devenait l employeur et la premiere phrase de la bio un intitule.
        if ($this->looksLikeSectionHeading($organisation) || $this->isTemplateLabel($organisation)) {
            return null;
        }
        if (mb_strlen($organisation) < 3 || trim($title) === '' || ! $this->looksLikeATitle($title)) {
            return null;
        }

        return [
            'title' => $this->shorten($title),
            'organisation' => $this->shorten($organisation),
        ];
    }

    /** @return string[] */
    private function splitOnSeparators(string $value): array
    {
        $parts = preg_split('#\s*(?:[/·|•]|\s[-–—]\s|,\s)\s*#u', trim($value)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    private function joinDescription(array $lines): ?string
    {
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        if ($lines === []) {
            return null;
        }

        return Str::limit(implode("\n", $lines), self::MAX_DESCRIPTION_LENGTH, '');
    }

    // Filtre de vraisemblance : un intitule est court et n'est pas une phrase.
    // Applique apres decoupage sur les separateurs, jamais a une ligne
    // entiere — sinon le budget de mots etait consomme par l'organisation et
    // le lieu, et l'entree abandonnee sans trace.
    private function looksLikeATitle(string $value): bool
    {
        $value = trim($value);

        if (mb_strlen($value) < 3 || mb_strlen($value) > 80) {
            return false;
        }
        if (preg_match('/[.…]$/u', $value)) {
            return false;
        }
        if (! preg_match('/\p{L}{2,}/u', $value)) {
            return false;
        }

        return str_word_count(Str::ascii($value), 0) <= 10;
    }

    private function shorten(string $value): string
    {
        return Str::limit(trim($value, " \t·|,-–—:"), 80, '');
    }

    private function looksLikeSectionHeading(string $line): bool
    {
        $line = trim($line, " \t:•-–—*");

        foreach (self::SECTION_HEADINGS as $pattern) {
            if (preg_match('/^(?:'.$pattern.')$/iu', $line)) {
                return true;
            }
        }

        return false;
    }

    // --- Dates ------------------------------------------------------------

    // Un point de date : "2023", "06/2024", "30/06/2024" (format natif
    // Europass), "sept. 2023", "August 2022".
    private function datePointPattern(): string
    {
        return '(?:(?:'.self::MONTHS.')\.?\s*)?(?:\d{1,2}[\/.]){0,2}(?:19|20)\d{2}';
    }

    private function dateRangeRegex(): string
    {
        $point = $this->datePointPattern();
        // Separateurs textuels entoures d'espaces ("to", "au", "until"), ou
        // tirets. "to" et "until" manquaient : toute la plage echouait, donc
        // l'entree disparaissait, sur des formes tres courantes en anglais.
        $sep = '(?:\s*[-–—>]\s*|\s+(?:a|au|to|until|till|jusqu\'?a)\s+)';

        return '/(?:depuis\s+('.$point.'))|(?:('.$point.')'.$sep.'('.$point.'|'.self::ONGOING.'))/iu';
    }

    // Une date SEULE ancre aussi une entree ("Bac Pro Commerce — 2021").
    // Sans cela, toute entree datee d'une seule annee etait silencieusement
    // ignoree — cas tres frequent pour les formations.
    private function singleDateRegex(): string
    {
        return '/(?<![\d\/.])('.$this->datePointPattern().')(?![\d\/.])/iu';
    }

    /** @return array{start: ?string, end: ?string}|null */
    private function extractDateRange(string $line): ?array
    {
        $normalized = Str::lower(Str::ascii($line));

        if (preg_match($this->dateRangeRegex(), $normalized, $m)) {
            // Forme "Depuis 2023" : debut connu, fin ouverte.
            if (($m[1] ?? '') !== '') {
                return ['start' => $this->toDate($m[1], false), 'end' => null];
            }

            $end = $m[3] ?? '';
            $isOngoing = (bool) preg_match('/'.self::ONGOING.'/u', $end);

            $startDate = $this->toDate($m[2] ?? '', false);
            $endDate = $isOngoing ? null : $this->toDate($end, true);

            // Le profil exige end_date >= start_date. Sur un texte melange,
            // deux dates de deux entrees differentes peuvent etre appariees :
            // mieux vaut une fin inconnue qu'une entree refusee par l'API.
            if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
                $endDate = null;
            }

            return ['start' => $startDate, 'end' => $endDate];
        }

        if (preg_match($this->singleDateRegex(), $normalized, $m)) {
            // Fin calculee sur la MEME periode, jamais laissee ouverte : un
            // "Job d'ete — juillet 2023" s'affichait "en cours", ce qui est
            // faux et donne au recruteur une image erronee du parcours.
            return [
                'start' => $this->toDate($m[1], false),
                'end' => $this->toDate($m[1], true),
            ];
        }

        return null;
    }

    // Retire toute date de la ligne, y compris les mois restes orphelins.
    // Le nettoyage porte sur le texte D'ORIGINE alors que la detection porte
    // sur sa version sans accents : sans le motif insensible aux accents ici,
    // "AOUT" restait dans la ligne et le residu devenait l'intitule.
    private function stripDates(string $line): string
    {
        $result = preg_replace($this->dateRangeRegex(), ' ', $line) ?? $line;
        $result = preg_replace($this->singleDateRegex(), ' ', $result) ?? $result;
        $result = preg_replace('/\b(?:'.self::MONTHS.')\.?\b/iu', ' ', $result) ?? $result;
        $result = preg_replace('/\b(?:'.self::ONGOING.')\b/iu', ' ', $result) ?? $result;

        return trim(preg_replace('/\s+/u', ' ', $result) ?? '', " \t·|,-–—:");
    }

    // Normalise en date SQL. Un CV donne rarement le jour : le 1er du mois
    // pour un debut, le dernier jour connu pour une fin.
    private function toDate(string $fragment, bool $isEnd): ?string
    {
        if (! preg_match('/((?:19|20)\d{2})/', $fragment, $y)) {
            return null;
        }
        $year = (int) $y[1];

        $month = $isEnd ? 12 : 1;

        if (preg_match('/(\d{1,2})[\/.](\d{1,2})[\/.](?:19|20)\d{2}/', $fragment, $m)) {
            // jj/mm/aaaa : le mois est le SECOND nombre.
            $month = max(1, min(12, (int) $m[2]));
        } elseif (preg_match('/(\d{1,2})[\/.](?:19|20)\d{2}/', $fragment, $m)) {
            $month = max(1, min(12, (int) $m[1]));
        } elseif (preg_match('/('.self::MONTHS.')/i', $fragment, $m)) {
            $month = $this->monthNumber($m[1]);
        }

        $day = $isEnd ? (int) date('t', mktime(0, 0, 0, $month, 1, $year)) : 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // Prefixes francais ET anglais, les plus longs d'abord. "jui" seul est
    // ambigu (juin ou juillet) : juin par defaut, se tromper d'un mois etant
    // sans consequence la ou retomber sur janvier fausse la chronologie.
    private const MONTH_PREFIXES = [
        'janv' => 1, 'jan' => 1,
        'fevr' => 2, 'fev' => 2, 'feb' => 2,
        'mars' => 3, 'marc' => 3, 'mar' => 3,
        'avr' => 4, 'apr' => 4,
        'mai' => 5, 'may' => 5,
        'juil' => 7, 'jul' => 7,
        'juin' => 6, 'jun' => 6, 'jui' => 6,
        'aout' => 8, 'aug' => 8, 'aou' => 8,
        'sept' => 9, 'sep' => 9,
        'octo' => 10, 'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];

    private function monthNumber(string $name): int
    {
        $prefix = Str::lower(Str::ascii($name));

        foreach (self::MONTH_PREFIXES as $key => $number) {
            if (Str::startsWith($prefix, $key)) {
                return $number;
            }
        }

        return 1;
    }

    // --- Identite du candidat ---------------------------------------------

    // Libelles de gabarit a ne jamais prendre pour un nom : "Curriculum Vitae"
    // figure en tete de tous les Europass classiques et etait lu comme
    // l'identite du candidat.
    private const NOT_A_NAME = 'curriculum vitae|curriculum|resume|cv|profil|profile|candidature'
        .'|contact|coordonn[ée]es|informations?|a propos|about( me)?';

    /** @return array{first: ?string, last: ?string} */
    private function extractName(string $text): array
    {
        // Douze lignes et non six : sur un CV a deux colonnes, l'extraction
        // rend d'abord la colonne etroite de gauche (CONTACT, telephone,
        // email, COMPETENCES...) et le nom n'arrive qu'ensuite. S'arreter a la
        // premiere ligne non conforme ne trouvait alors aucun nom.
        $retenus = [];

        foreach (array_slice($this->toLines($text), 0, 12) as $index => $line) {
            $line = trim($line);

            // "SURNAME, Firstname" : format impose par Europass.
            if (preg_match('/^([\p{L}][\p{L}\-\' ]{1,40}),\s*([\p{L}][\p{L}\-\' ]{1,40})$/u', $line, $m)) {
                return [
                    'first' => $this->normalizeName($m[2]),
                    'last' => $this->normalizeName($m[1]),
                ];
            }

            if ($this->looksLikeSectionHeading($line) || $this->isTemplateLabel($line)) {
                continue;
            }
            if (! preg_match('/^[\p{L}][\p{L}\-\' ]{1,60}$/u', $line)) {
                continue;
            }

            $mots = array_values(array_filter(preg_split('/\s+/u', $line) ?: []));
            if ($mots === [] || count($mots) > 4) {
                continue;
            }

            $retenus[] = [
                'index' => $index,
                'mots' => $mots,
                'capitales' => $line === mb_strtoupper($line),
            ];
        }

        // Les CV ecrivent presque toujours l'identite en capitales, et c'est ce
        // qui la distingue d'une competence ou d'un titre de rubrique reste
        // dans la fenetre de recherche : sans cette preference, un CV a deux
        // colonnes donnait le nom "Accueil Client", lu dans la liste des
        // competences de la colonne de gauche. On ne retombe sur une ligne en
        // casse normale que si aucune ligne en capitales ne convient.
        foreach ([true, false] as $exigerCapitales) {
            $nom = $this->firstNameCandidate($retenus, $exigerCapitales);
            if ($nom !== null) {
                return $nom;
            }
        }

        return ['first' => null, 'last' => null];
    }

    /**
     * @param  array<int, array{index:int, mots:string[], capitales:bool}>  $retenus
     * @return array{first: string, last: string}|null
     */
    private function firstNameCandidate(array $retenus, bool $exigerCapitales): ?array
    {
        $eligibles = $exigerCapitales
            ? array_values(array_filter($retenus, fn (array $l) => $l['capitales']))
            : $retenus;

        // Le nom tient sur une seule ligne.
        foreach ($eligibles as $ligne) {
            if (count($ligne['mots']) >= 2) {
                return $this->splitName($ligne['mots']);
            }
        }

        // Nom coupe sur deux lignes CONSECUTIVES ("ALEXANDRE" puis "LEYVA").
        // L'adjacence est exigee : sans elle, on assemblerait un mot de la
        // colonne de gauche avec un prenom lu quatre lignes plus bas.
        foreach ($eligibles as $rang => $ligne) {
            $suivante = $eligibles[$rang + 1] ?? null;
            if (
                $suivante !== null
                && count($ligne['mots']) === 1
                && count($suivante['mots']) === 1
                && $suivante['index'] === $ligne['index'] + 1
            ) {
                return $this->splitName([$ligne['mots'][0], $suivante['mots'][0]]);
            }
        }

        return null;
    }

    /**
     * @param  string[]  $words
     * @return array{first: string, last: string}
     */
    private function splitName(array $words): array
    {
        return [
            'first' => $this->normalizeName($words[0]),
            'last' => implode(' ', array_map(fn ($w) => $this->normalizeName($w), array_slice($words, 1))),
        ];
    }

    private function isTemplateLabel(string $line): bool
    {
        return (bool) preg_match('/^(?:'.self::NOT_A_NAME.')$/iu', trim($line));
    }

    private function normalizeName(string $word): string
    {
        return Str::title(Str::lower(trim($word)));
    }

    // --- Coordonnees ------------------------------------------------------

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractPhone(string $text): ?string
    {
        if (preg_match('/(?:\+33[\s.-]?|0)[1-9](?:[\s.-]?\d{2}){4}/', $text, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    // Code postal francais : 5 chiffres isoles, hors 00000 et hors suites
    // appartenant a un nombre plus long.
    private function extractPostalCode(string $text): ?string
    {
        if (preg_match('/(?<!\d)((?:0[1-9]|[1-9]\d)\d{3})(?!\d)/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractLinkedinUrl(string $text): ?string
    {
        if (preg_match('#(?:https?://)?(?:www\.)?linkedin\.com/in/[A-Za-z0-9\-_%]+#i', $text, $matches)) {
            $url = $matches[0];

            return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://'.$url;
        }

        return null;
    }

    private function extractDrivingLicense(string $text): ?string
    {
        if (preg_match('/permis\s*(?:de\s*conduire\s*)?:?\s*([A-E]\d?)\b/i', $text, $matches)) {
            return 'Permis '.Str::upper($matches[1]);
        }

        if (preg_match('/\b(?:permis\s*(?:de\s*conduire)?|driving licen[cs]e)\b/i', $text)) {
            return 'Permis B';
        }

        return null;
    }

    // --- Referentiels -----------------------------------------------------

    private function matchReferential(string $text, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            $needle = trim((string) $name);
            if (mb_strlen($needle) < self::MIN_REFERENTIAL_LENGTH) {
                continue;
            }

            // Frontieres de mot seulement quand le nom commence et finit par
            // un caractere de mot : "Excel/Word" ou "C++" cassent \b.
            $quoted = preg_quote($needle, '/');
            $pattern = preg_match('/^\w/', $needle) && preg_match('/\w$/', $needle)
                ? '/\b'.$quoted.'\b/iu'
                : '/'.$quoted.'/iu';

            if (preg_match($pattern, $text)) {
                $found[] = $needle;
            }

            if (count($found) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $found;
    }

    // Nom francais retenu, mais reconnu aussi sous sa forme anglaise : un CV
    // en anglais ecrit "English", "Spanish", "German", et aucune langue
    // n'etait alors detectee.
    private const LANGUAGES = [
        'Anglais' => 'anglais|english',
        'Espagnol' => 'espagnol|spanish',
        'Allemand' => 'allemand|german',
        'Italien' => 'italien|italian',
        'Portugais' => 'portugais|portuguese',
        'Catalan' => 'catalan',
        'Arabe' => 'arabe|arabic',
        'Chinois' => 'chinois|chinese|mandarin',
        'Russe' => 'russe|russian',
        'Neerlandais' => 'neerlandais|dutch',
        'Japonais' => 'japonais|japanese',
        'Francais' => 'francais|french',
    ];

    // Cherche dans TOUT le texte : sur un CV a deux colonnes les frontieres de
    // rubriques sont fausses, et une langue se reconnait a son nom, pris dans
    // une liste fermee — chercher partout est donc sans risque ici.
    private function extractLanguages(string $fullText): array
    {
        $haystack = Str::ascii($fullText);
        $found = [];

        foreach (self::LANGUAGES as $name => $pattern) {
            if (! preg_match('/\b(?:'.$pattern.')\b/i', $haystack, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $after = substr($haystack, $m[0][1] + strlen($m[0][0]), 40);
            $found[] = ['name' => $name, 'level' => $this->readLanguageLevel($after)];
        }

        return $found;
    }

    // On retient l'indice le PLUS PROCHE de la langue, code CECRL ou mot :
    // prendre le code d'abord donnait "Espagnol B2" pour un "Espagnol langue
    // maternelle" suivi, quelques mots plus loin, d'un "Anglais B2".
    private function readLanguageLevel(string $after): ?string
    {
        $candidates = [];

        if (preg_match('/\b([ABC][12])\b/i', $after, $m, PREG_OFFSET_CAPTURE)) {
            $candidates[] = ['at' => $m[1][1], 'level' => Str::upper($m[1][0])];
        }

        $words = [
            'bilingue' => 'C2', 'bilingual' => 'C2', 'maternelle' => 'C2', 'native' => 'C2', 'natif' => 'C2',
            'courant' => 'C1', 'fluent' => 'C1',
            'avance' => 'B2', 'advanced' => 'B2',
            'intermediaire' => 'B1', 'intermediate' => 'B1',
            'scolaire' => 'A2', 'basic' => 'A2',
            'notions' => 'A1', 'debutant' => 'A1', 'beginner' => 'A1',
        ];

        foreach ($words as $word => $level) {
            if (preg_match('/\b'.$word.'/i', $after, $m, PREG_OFFSET_CAPTURE)) {
                $candidates[] = ['at' => $m[0][1], 'level' => $level];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $candidates[0]['level'];
    }
}
