<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Software;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

// Lecture d'un CV PDF pour pre-remplir le profil du candidat.
//
// Aucune IA n'est disponible dans cet environnement (meme limitation que
// Stripe/Google OAuth dans CLAUDE.md), l'extraction est donc structurelle :
// on repere les intitules de rubriques ("EXPERIENCES", "FORMATION",
// "LANGUES"...) puis, dans chaque rubrique, les entrees sont delimitees par
// les plages de dates — c'est le seul repere fiable et commun a tous les
// gabarits de CV.
//
// Ce que ce service NE fait pas, volontairement : deviner. Une entree sans
// date reconnaissable n'est pas proposee, un mot absent du referentiel n'est
// pas transforme en competence. Mieux vaut proposer trois experiences justes
// que six dont deux inventees, que le candidat devra traquer et corriger.
//
// Limite connue : sur un CV a mise en page multi-colonnes, le texte extrait
// melange l'ordre de lecture des colonnes et les entrees peuvent se retrouver
// tronquees ou melangees. C'est pourquoi le resultat est presente au candidat
// comme des suggestions a relire, jamais applique automatiquement.
class CvImportService
{
    private const MIN_REFERENTIAL_LENGTH = 3;

    private const MAX_SUGGESTIONS = 25;

    // Une entree de CV est ancree par sa plage de dates. Couvre les formes
    // courantes : "2020 - 2022", "janv. 2020 - dec. 2021", "01/2020 - 06/2022",
    // "Depuis 2023", "2023 - aujourd'hui", "sept. 2023 - present".
    // Mois francais ET anglais : beaucoup de CV utilisent des gabarits
    // anglophones (Canva, LinkedIn) qui laissent "JUN 2025" tel quel.
    private const MONTHS = 'janvier|janv|jan|fevrier|fev|feb|mars|mar|avril|avr|apr|mai|may|juillet|juil|juin|jui|jun|jul|aout|aou|aug|septembre|sept|sep|octobre|oct|novembre|nov|decembre|dec';

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
            'experiences' => $this->extractEntries($sections['experiences'] ?? '', 'experience'),
            'educations' => $this->extractEntries($sections['educations'] ?? '', 'education'),
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

    // Decoupe le CV en rubriques a partir de ses intitules. Tout ce qui
    // precede la premiere rubrique reconnue est l'en-tete (nom, coordonnees),
    // deja traite par les extracteurs par expression reguliere.
    // Variantes accentuees ecrites explicitement : le motif est applique au
    // texte d'origine pour preserver les positions (voir splitIntoSections).
    private const SECTION_HEADINGS = [
        'experiences' => 'exp[ée]riences?(?: professionnelles?| pro)?|parcours professionnel|emplois?|stages?',
        'educations' => 'formations?|dipl[ôo]mes?|[ée]tudes|scolarit[ée]|cursus',
        'languages' => 'langues?',
        'skills' => 'comp[ée]tences?|savoir[- ]faire|qualit[ée]s?',
        'software' => 'logiciels?|outils?|informatique',
        'hobbies' => "centres? d'int[ée]r[êe]ts?|loisirs|int[ée]r[êe]ts?|hobbies",
    ];

    // Decoupage par POSITION dans le texte, et non ligne par ligne : sur un
    // vrai CV, l'extraction PDF colle souvent l'intitule au contenu qui suit
    // ("PARCOURS PROFESSIONNELJUN 2025 - Act"), et un decoupage par lignes ne
    // reconnait alors plus aucune rubrique. Constate sur de vrais CV.
    //
    // @return array<string, string> texte de chaque rubrique
    private function splitIntoSections(string $text): array
    {
        // Recherche dans le texte d'origine, accents inclus dans les motifs :
        // passer par une version sans accents decalerait toutes les positions
        // (un caractere accente occupe 2 octets en UTF-8, son equivalent
        // ASCII un seul), et les rubriques seraient tronquees de travers.
        $marks = [];
        foreach (self::SECTION_HEADINGS as $key => $pattern) {
            // Strictement en debut de ligne. Sans cette contrainte, le mot
            // "experience" d'une phrase de presentation ("je souhaite mettre a
            // profit mon experience en vente") etait pris pour l'intitule de
            // la rubrique, qui commencait alors au mauvais endroit — constate
            // sur un vrai CV.
            // Pas de \b apres l'intitule : l'extraction PDF colle souvent la
            // suite au titre ("PARCOURS PROFESSIONNELJUN 2025"), et entre "L"
            // et "J" il n'y a justement aucune frontiere de mot. On exige a la
            // place que la suite ne soit pas une minuscule, ce qui distingue
            // un titre suivi de son contenu d'un simple debut de mot.
            // (?-i) desactive l'insensibilite a la casse dans ce seul
            // lookahead : sans lui, le drapeau /i du motif global rend aussi
            // [a-z] insensible, une majuscule est alors consideree comme une
            // minuscule et le titre colle a la suite ne matche jamais.
            $notLowercase = '(?!(?-i)[a-zàâäçéèêëîïôöûùüÿñæœ])';
            if (! preg_match_all('/(?:^|\n)[ \t]*('.$pattern.')'.$notLowercase.'[ \t]*:?/iu', $text, $all, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            // A egalite, l'intitule en majuscules l'emporte : c'est la mise en
            // forme quasi systematique des titres de rubrique sur un CV, et
            // elle distingue le vrai titre d'une occurrence en prose.
            $best = null;
            foreach ($all as $match) {
                $word = $match[1][0];
                $isUpper = $word === mb_strtoupper($word);
                if ($best === null || ($isUpper && ! $best['upper'])) {
                    $best = [
                        'upper' => $isUpper,
                        'start' => $match[0][1] + strlen($match[0][0]),
                    ];
                }
            }

            $marks[] = ['key' => $key, 'start' => $best['start']];
        }

        if ($marks === []) {
            return [];
        }

        usort($marks, fn ($a, $b) => $a['start'] <=> $b['start']);

        $sections = [];
        foreach ($marks as $i => $mark) {
            $end = $marks[$i + 1]['start'] ?? strlen($text);
            $sections[$mark['key']] = substr($text, $mark['start'], $end - $mark['start']);
        }

        return $sections;
    }

    // Une entree = une plage de dates, plus la ligne qui porte l'identite du
    // poste ou du diplome. Deux dispositions coexistent dans les vrais CV, et
    // il faut savoir les distinguer :
    //
    //  - classique : intitule, organisation, puis dates ;
    //  - gabarits type Canva : la date d'abord, l'identite juste apres, avec
    //    l'entreprise EN CAPITALES collee au poste par l'extraction PDF
    //    ("SURPRISE ROSESetter commercial/ Madrid freelance ...").
    //
    // L'entreprise en capitales collee au poste est le signal le plus fiable :
    // quand une ligne candidate en contient une, c'est elle l'identite, ou
    // qu'elle se trouve. A defaut on retombe sur la disposition classique (ce
    // qui precede la date), puis sur ce qui la suit.
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

        $entries = [];
        foreach ($anchors as $n => $anchor) {
            $previous = $n > 0 ? $anchors[$n - 1]['index'] : -1;
            $next = $anchors[$n + 1]['index'] ?? count($lines);

            // La ligne de dates porte parfois l'identite ("Vendeuse - Decathlon
            // - 2023"), parfois la fin de la description de l'entree
            // precedente : on la nettoie et on la traite comme une candidate
            // parmi d'autres plutot que de lui faire confiance d'emblee.
            $inline = trim(preg_replace($this->dateRangeRegex(), '', $anchor['line']) ?? '');
            $inline = trim($inline, " \t·|,-–—:");

            $before = array_values(array_filter(
                array_slice($lines, $previous + 1, $anchor['index'] - $previous - 1),
                fn ($l) => $this->extractDateRange($l) === null,
            ));
            $after = array_slice($lines, $anchor['index'] + 1, $next - $anchor['index'] - 1);

            $identity = $this->pickIdentity($inline, $after, $before);
            if ($identity === null) {
                continue;
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

    // Choisit, parmi les lignes qui entourent une date, celle qui identifie
    // l'entree, et en tire intitule, organisation et description.
    //
    // @return array{title: string, organisation: ?string, description: ?string}|null
    private function pickIdentity(string $inline, array $after, array $before): ?array
    {
        $lastBefore = $before === [] ? '' : $before[count($before) - 1];

        // 1. Une entreprise en capitales tranche immediatement, ou qu'elle soit.
        $candidats = [
            [$inline, $after],
            [$after[0] ?? '', array_slice($after, 1)],
            [$lastBefore, $after],
        ];

        foreach ($candidats as [$ligne, $reste]) {
            if ($ligne === '') {
                continue;
            }
            $parsed = $this->splitCapitalisedOrganisation($ligne);
            if ($parsed !== null && $this->looksLikeATitle($parsed['title'])) {
                return $parsed + ['description' => $this->joinDescription($reste)];
            }
        }

        // 2. Disposition classique : intitule puis organisation AVANT la date.
        if ($before !== []) {
            $heads = array_slice($before, -2);
            $title = $this->shorten($heads[0]);
            if ($this->looksLikeATitle($title)) {
                return [
                    'title' => $title,
                    'organisation' => isset($heads[1]) ? $this->shorten($heads[1]) : null,
                    'description' => $this->joinDescription($after),
                ];
            }
        }

        // 3. La date ouvre l'entree : l'identite suit.
        if ($inline !== '' && $this->looksLikeATitle($this->shorten($inline))) {
            return [
                'title' => $this->shorten($inline),
                'organisation' => isset($after[0]) ? $this->shorten($after[0]) : null,
                'description' => $this->joinDescription(array_slice($after, 1)),
            ];
        }

        if ($after !== [] && $this->looksLikeATitle($this->shorten($after[0]))) {
            return [
                'title' => $this->shorten($after[0]),
                'organisation' => isset($after[1]) ? $this->shorten($after[1]) : null,
                'description' => $this->joinDescription(array_slice($after, 2)),
            ];
        }

        return null;
    }

    // "SURPRISE ROSESetter commercial/ Madrid freelance Gestion des messages"
    // devient organisation "SURPRISE ROSE", intitule "Setter commercial".
    //
    // L'extraction PDF colle l'entreprise (en capitales) au poste : la
    // frontiere est le passage d'une suite de capitales a une capitale suivie
    // d'une minuscule. Le poste s'arrete ensuite au premier separateur, ce qui
    // suit etant le lieu puis la description.
    //
    // @return array{title: string, organisation: string}|null
    private function splitCapitalisedOrganisation(string $line): ?array
    {
        if (! preg_match('/^([\p{Lu}][\p{Lu}\s&\'.\-]{2,58}?)(?=\p{Lu}\p{Ll})(.+)$/u', $line, $m)) {
            return null;
        }

        $organisation = trim($m[1], " \t.-'&");
        $rest = trim($m[2]);

        $title = preg_split('#\s*(?:[/·|]|\s-\s)\s*#u', $rest)[0] ?? $rest;

        if (mb_strlen($organisation) < 3 || trim((string) $title) === '') {
            return null;
        }

        return [
            'title' => $this->shorten((string) $title),
            'organisation' => $this->shorten($organisation),
        ];
    }

    // 2000 caracteres : la limite exacte acceptee par le profil
    // (StoreExperienceRequest). Sur un CV a colonnes, l extraction agrege
    // parfois plusieurs blocs dans une meme description et depassait cette
    // limite — l API refusait alors l entree, et le candidat ne voyait qu un
    // echec sans cause. On tronque plutot que de perdre l experience entiere.
    private const MAX_DESCRIPTION_LENGTH = 2000;

    private function joinDescription(array $lines): ?string
    {
        $lines = array_values(array_filter(
            array_map('trim', $lines),
            fn ($l) => $l !== '',
        ));

        if ($lines === []) {
            return null;
        }

        return Str::limit(implode('
', $lines), self::MAX_DESCRIPTION_LENGTH, '');
    }

    // Filtre de vraisemblance. Sur un CV a colonnes, l'extraction PDF rend un
    // texte melange d'ou l'on ne peut tirer que des phrases de description
    // prises pour des intitules. Proposer ces entrees est PIRE que n'en
    // proposer aucune : le candidat doit alors les supprimer une par une avant
    // de ressaisir les vraies.
    private function looksLikeATitle(string $value): bool
    {
        $value = trim($value);

        if (mb_strlen($value) < 3 || mb_strlen($value) > 80) {
            return false;
        }

        if (preg_match('/[.…]$/u', $value)) {
            return false;
        }

        // Dix mots et non huit : des intitules reels comme "Community Manager
        // et creatrice de contenu" depassent huit mots.
        return str_word_count(Str::ascii($value), 0) <= 10;
    }

    // L'extraction PDF colle regulierement plusieurs elements sur une meme
    // ligne. On tronque plutot que de refuser l'entree : le candidat corrige
    // un intitule trop long bien plus vite qu'il ne ressaisit tout.
    private function shorten(string $value): string
    {
        return Str::limit(trim($value, " \t·|,-–—:"), 80, '');
    }

    private function dateRangeRegex(): string
    {
        $m = self::MONTHS;
        $point = '(?:(?:'.$m.')\.?\s*)?(?:\d{1,2}[\/.])?(?:19|20)\d{2}';
        // "act" seul est frequent sur les gabarits anglophones ("JUN 2025 - Act").
        $now = "aujourd'hui|aujourdhui|present|actuel(?:lement)?|act\b|en cours|a ce jour|now|today";

        return '/(?:depuis\s+('.$point.'))|(?:('.$point.')\s*(?:-|–|—|a|au|jusqu\'?a|>)\s*('.$point.'|'.$now.'))/iu';
    }

    /** @return array{start: ?string, end: ?string}|null */
    private function extractDateRange(string $line): ?array
    {
        $normalized = Str::lower(Str::ascii($line));
        if (! preg_match($this->dateRangeRegex(), $normalized, $m)) {
            return null;
        }

        // Forme "Depuis 2023" : debut connu, fin ouverte.
        if (($m[1] ?? '') !== '') {
            return ['start' => $this->toDate($m[1], false), 'end' => null];
        }

        $end = $m[3] ?? '';
        $isOngoing = (bool) preg_match('/aujourd|present|actuel|en cours|a ce jour/u', $end);

        $startDate = $this->toDate($m[2] ?? '', false);
        $endDate = $isOngoing ? null : $this->toDate($end, true);

        // Le profil exige une date de fin posterieure au debut
        // (after_or_equal:start_date). Sur un texte melange, l extraction peut
        // apparier deux dates qui n appartiennent pas a la meme entree et
        // produire une plage inversee : l API refusait alors l entree entiere.
        // Mieux vaut une date de fin inconnue qu une experience perdue.
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $endDate = null;
        }

        return ['start' => $startDate, 'end' => $endDate];
    }

    // Normalise en date SQL. Un CV donne rarement le jour : on retient le 1er
    // du mois pour un debut, le dernier jour connu pour une fin, et janvier
    // (ou decembre) quand seule l'annee est indiquee.
    private function toDate(string $fragment, bool $isEnd): ?string
    {
        if (! preg_match('/((?:19|20)\d{2})/', $fragment, $y)) {
            return null;
        }
        $year = (int) $y[1];

        $month = $isEnd ? 12 : 1;
        if (preg_match('/(\d{1,2})[\/.](?:19|20)\d{2}/', $fragment, $m)) {
            $month = max(1, min(12, (int) $m[1]));
        } elseif (preg_match('/('.self::MONTHS.')/i', $fragment, $m)) {
            $month = $this->monthNumber($m[1]);
        }

        $day = $isEnd ? (int) date('t', mktime(0, 0, 0, $month, 1, $year)) : 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // Prefixes francais ET anglais. Les plus longs d'abord : "juil" doit etre
    // teste avant "jui", et "jun"/"jul" existent cote anglais — sans eux,
    // "JUN 2025" retombait silencieusement sur janvier.
    private const MONTH_PREFIXES = [
        'janv' => 1, 'jan' => 1, 'fevr' => 2, 'fev' => 2, 'feb' => 2,
        'mars' => 3, 'mar' => 3, 'avr' => 4, 'apr' => 4, 'mai' => 5, 'may' => 5,
        'juin' => 6, 'jun' => 6, 'juil' => 7, 'jul' => 7,
        // 'jui' seul est ambigu (juin ou juillet) et frequent sur les CV
        // mis en page a l'etranger. Juin plutot que janvier par defaut : se
        // tromper d'un mois est sans consequence, se tromper de semestre
        // fausse la chronologie du parcours.
        'jui' => 6,
        'aout' => 8, 'aou' => 8, 'aug' => 8, 'sept' => 9, 'sep' => 9,
        'octo' => 10, 'oct' => 10, 'nov' => 11, 'dec' => 12,
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

    // Nom et prenom, lus en tete de CV. C'est ce qui permet a l'import de
    // CREER le profil d'un candidat qui vient d'arriver, au lieu de lui
    // demander de saisir ses infos avant de pouvoir importer quoi que ce soit.
    //
    // Les CV placent quasi systematiquement l'identite sur les toutes
    // premieres lignes, souvent en capitales, et parfois coupee en deux
    // ("ALEXANDRE" puis "LEYVA" — constate sur un vrai CV). On agrege donc les
    // premieres lignes qui ressemblent a un nom, puis on coupe : premier mot =
    // prenom, le reste = nom. Convention imparfaite pour un prenom compose,
    // mais le candidat relit et corrige, et c'est infiniment moins couteux que
    // de tout ressaisir.
    //
    // @return array{first: ?string, last: ?string}
    private function extractName(string $text): array
    {
        $words = [];

        foreach (array_slice($this->toLines($text), 0, 5) as $line) {
            // Une ligne d'identite ne contient que des lettres, espaces, tirets
            // et apostrophes : ni chiffre, ni @, ni ponctuation de phrase.
            if (! preg_match("/^[\p{L}][\p{L}\-' ]{1,60}$/u", $line)) {
                break;
            }
            // Un intitule de rubrique en tete de page n'est pas un nom.
            if ($this->looksLikeSectionHeading($line)) {
                break;
            }

            $words = array_merge($words, preg_split('/\s+/u', trim($line)));

            // Deux lignes suffisent (nom coupe en deux), et au-dela de quatre
            // mots on ramasse un titre de poste plutot qu'une identite.
            if (count($words) >= 2) {
                break;
            }
        }

        $words = array_values(array_filter($words, fn ($w) => $w !== ''));
        if (count($words) < 2 || count($words) > 4) {
            return ['first' => null, 'last' => null];
        }

        $normalize = fn (string $w) => Str::title(Str::lower($w));

        return [
            'first' => $normalize($words[0]),
            'last' => implode(' ', array_map($normalize, array_slice($words, 1))),
        ];
    }

    private function looksLikeSectionHeading(string $line): bool
    {
        foreach (self::SECTION_HEADINGS as $pattern) {
            if (preg_match('/^(?:'.$pattern.')$/iu', trim($line))) {
                return true;
            }
        }

        return false;
    }

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

    // Code postal francais : 5 chiffres isoles, en excluant 00000 et les
    // suites qui font partie d'un nombre plus long (montant, numero de rue).
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

        if (preg_match('/\bpermis\s*(?:de\s*conduire)?\b/i', $text)) {
            return 'Permis B';
        }

        return null;
    }

    // Cherche dans le texte les noms deja connus de Jeuncy. Aucune invention :
    // si le mot n'est pas dans le referentiel, il n'est pas propose.
    private function matchReferential(string $text, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            $needle = trim((string) $name);
            if (mb_strlen($needle) < self::MIN_REFERENTIAL_LENGTH) {
                continue;
            }

            // Frontieres de mot uniquement quand le nom commence et finit par
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

    private const COMMON_LANGUAGES = [
        'Anglais', 'Espagnol', 'Allemand', 'Italien', 'Portugais', 'Catalan',
        'Arabe', 'Chinois', 'Russe', 'Neerlandais', 'Japonais', 'Francais',
    ];

    // Cherche d'abord dans la rubrique "Langues" si elle existe (le niveau y
    // suit immediatement la langue), sinon dans tout le texte.
    // Cherche dans TOUT le texte et non dans la seule rubrique "Langues" :
    // sur un CV a deux colonnes, l'extraction entrelace les colonnes et les
    // frontieres de rubriques deviennent fausses — la rubrique "Langues" y
    // contenait des competences, et inversement (constate sur un vrai CV).
    // Chercher partout est sans risque ici : une langue est reconnue par son
    // nom, pris dans une liste fermee, pas par sa position.
    private function extractLanguages(string $fullText): array
    {
        $haystack = Str::ascii($fullText);
        $found = [];

        foreach (self::COMMON_LANGUAGES as $language) {
            $needle = Str::ascii($language);
            if (! preg_match('/\b'.preg_quote($needle, '/').'\b/i', $haystack, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // Niveau cherche dans les ~40 caracteres qui suivent : au-dela, il
            // appartient vraisemblablement a une autre ligne du CV.
            $after = substr($haystack, $m[0][1] + strlen($needle), 40);
            $level = $this->readLanguageLevel($after);

            $found[] = ['name' => $language, 'level' => $level];
        }

        return $found;
    }

    // Niveau CECRL (B2) ou son equivalent en toutes lettres, tres frequent sur
    // les CV de jeunes candidats ("Anglais : courant").
    // On retient l'indice le PLUS PROCHE de la langue, code CECRL ou mot.
    // Prendre systematiquement le code d'abord donnait "Espagnol B2" pour un
    // "Espagnol langue maternelle" suivi, quelques mots plus loin, d'un
    // "Anglais B2" — le texte des CV a colonnes rapproche des lignes qui ne se
    // suivent pas a l'ecran.
    private function readLanguageLevel(string $after): ?string
    {
        $candidates = [];

        if (preg_match('/\b([ABC][12])\b/i', $after, $m, PREG_OFFSET_CAPTURE)) {
            $candidates[] = ['at' => $m[1][1], 'level' => Str::upper($m[1][0])];
        }

        foreach (['bilingue' => 'C2', 'maternelle' => 'C2', 'natif' => 'C2', 'courant' => 'C1',
            'avance' => 'B2', 'intermediaire' => 'B1', 'scolaire' => 'A2',
            'notions' => 'A1', 'debutant' => 'A1'] as $word => $level) {
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
