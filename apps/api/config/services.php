<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM_EMAIL', 'no-reply@jeuncy.com'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Prix fixe (en centimes) de publication d'une offre seule, different
        // selon qu'elle est publiee par une entreprise ou par un CFA (voir
        // JobOfferService::priceCentsFor). Couvre la publication et RIEN
        // d'autre : ni l'acces aux candidatures, ni la CVtheque, qui passent
        // desormais exclusivement par l'abonnement (decision du 2026-08-17,
        // qui a supprime le deblocage a l'offre existant depuis le 2026-08-05).
        'company_offer_price_cents' => (int) env('STRIPE_COMPANY_OFFER_PRICE_CENTS', 999),
        'cfa_offer_price_cents' => (int) env('STRIPE_CFA_OFFER_PRICE_CENTS', 599),
        // Duree de mise en ligne achetee par ce paiement. Depuis le
        // 2026-09-10 le prix n'achete plus une publication definitive mais
        // une periode : passe ce delai l'offre sort de la ligne, et son
        // proprietaire la remet en ligne en repayant le meme montant.
        // AUCUN prelevement automatique : c'est un achat ponctuel qui
        // expire, pas un abonnement Stripe.
        'offer_publication_days' => (int) env('STRIPE_OFFER_PUBLICATION_DAYS', 30),
        // Abonnement mensuel (voir SubscriptionService::priceCentsFor) : tout
        // illimite — publication d'offres, acces aux candidatures de toutes les
        // offres, et acces a la CVtheque. Meme tarif entreprise et CFA depuis le
        // 2026-08-17 ("pour le moment", les deux grilles restent separees pour
        // pouvoir diverger a nouveau sans migration).
        'company_subscription_price_cents' => (int) env('STRIPE_COMPANY_SUBSCRIPTION_PRICE_CENTS', 49900),
        'cfa_subscription_price_cents' => (int) env('STRIPE_CFA_SUBSCRIPTION_PRICE_CENTS', 49900),
        // Tarif d'ouverture reserve aux 50 premiers abonnes, entreprises et CFA
        // confondus, verrouille a vie tant que l'abonnement continue (voir
        // SubscriptionService::founderSeatsRemaining et la migration
        // add_founder_rate_to_subscriptions_table).
        'founder_subscription_price_cents' => (int) env('STRIPE_FOUNDER_SUBSCRIPTION_PRICE_CENTS', 29900),
        'founder_seats_total' => (int) env('STRIPE_FOUNDER_SEATS_TOTAL', 50),
    ],

    // Coordonnees affichees sur la page Contact et destinataire du formulaire.
    // En config plutot qu'en dur dans le frontend : changer un numero ne doit
    // pas obliger a reconstruire et redeployer tout le bundle JavaScript.
    //
    // phone volontairement vide par defaut : tant qu'aucun numero reel n'est
    // fourni, la page n'affiche pas de bloc telephone plutot qu'un placeholder
    // — un faux numero sur une page de contact est pire que pas de numero.
    'contact' => [
        'email' => env('CONTACT_EMAIL', 'bonjour@jeuncy.com'),
        'phone' => env('CONTACT_PHONE'),
    ],

    // Import des offres de La bonne alternance (api.apprentissage.beta.gouv.fr),
    // decide le 2026-09-15 pour remplir Jeuncy d'offres en volume. Usage
    // gratuit, licence Etalab 2.0 : la source est mentionnee sur chaque offre.
    'lba' => [
        // Cle de PRODUCTION creee sur /fr/compte/profil (une cle sandbox
        // renvoie des donnees de test). Vide = import desactive, sans erreur.
        'api_key' => env('LBA_API_KEY'),
        'base_url' => env('LBA_BASE_URL', 'https://api.apprentissage.beta.gouv.fr/api'),
        // Perimetre d'import : seules les offres situees dans ces departements
        // sont conservees. Occitanie au depart (2026-09-15), France entiere
        // depuis le 2026-09-18 (decision du patron) : LBA_DEPARTEMENTS=* ou
        // vide = tous les departements. Une liste (« 66,11,34 ») restreint.
        'departements' => (function () {
            $brut = trim((string) env('LBA_DEPARTEMENTS', '*'));

            return $brut === '' || $brut === '*'
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $brut))));
        })(),
        // true : l'import de nuit LIT et COMPTE mais n'ecrit aucune offre — le
        // rapport (admin, onglet Offres partenaires) permet de juger le filtre
        // sur les vraies donnees avant de rien montrer aux candidats. Passer a
        // false quand les chiffres conviennent.
        'mesure_seulement' => filter_var(env('LBA_MESURE_SEULEMENT', false), FILTER_VALIDATE_BOOLEAN),
        // Employeurs jamais ecartes par le filtre des ecoles : l'ecole
        // partenaire, dont les offres sont les bienvenues. SIRET separes par
        // des virgules.
        'siret_whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('LBA_SIRET_WHITELIST', ''))))),
    ],

    // Modele economique, decide en reunion le 2026-09-15 : Jeuncy est
    // ENTIEREMENT GRATUIT pour les entreprises (publication, candidatures,
    // CVtheque), afin de remplir la plateforme en volume avant de monetiser.
    // La valeur se fait ailleurs : chaque jeune inscrit est un candidat pour
    // l'ecole partenaire (IDA), qui est remuneree par l'OPCO a l'inscription
    // d'un apprenti — d'ou l'interdiction de tout autre CFA (voir
    // inscription_cfa_ouverte).
    //
    // Deux drapeaux plutot qu'une suppression du code de paiement : Stripe,
    // l'essai et l'abonnement restent en place, desactives, pour pouvoir
    // rouvrir une grille tarifaire sans tout reecrire — les tests de ces
    // parcours tournent d'ailleurs toujours, gratuit=false.
    'jeuncy' => [
        // true : aucune etape de paiement n'existe pour une entreprise ; les
        // routes Stripe repondent PAYMENTS_DISABLED, l'offre d'ouverture est
        // annoncee indisponible, et hasPaidAccess() accorde tout aux
        // entreprises et CFA.
        'gratuit' => filter_var(env('JEUNCY_GRATUIT', true), FILTER_VALIDATE_BOOLEAN),
        // false : l'inscription en tant que CFA est refusee (formulaire et
        // Google). Les comptes CFA existants (IDA) ne sont pas touches : la
        // garde porte sur la CREATION de compte, jamais sur la connexion.
        // Un CFA qui s'inscrirait librement acceder aux memes candidats que
        // l'ecole partenaire — c'est precisement ce qu'on ne veut pas.
        'inscription_cfa_ouverte' => filter_var(env('JEUNCY_INSCRIPTION_CFA_OUVERTE', false), FILTER_VALIDATE_BOOLEAN),
        // Perimetre d'ouverture du modele match (MOBILE.md §6, decision 6) :
        // les Pyrenees-Orientales au lancement, ouverture departement par
        // departement ensuite. '*' = partout.
        //
        // ATTENTION, le sens d'une valeur VIDE est l'INVERSE de
        // lba.departements juste au-dessus : vide = FERME, aucun departement.
        // La leçon du 2026-09-18 (une liste vide aurait vide le site en une
        // nuit) vaut dans les deux sens — ici, le risque serait d'ouvrir la
        // France entiere par omission, c'est-a-dire de montrer des cartes de
        // mineurs a des employeurs qu'on n'a pas encore rencontres.
        //
        // Ce perimetre ne restreint QUE le cote employeur (Decouvrir des
        // candidats, interet employeur). La pile du candidat n'est jamais
        // bornee : il peut marquer son interet sur n'importe quelle offre
        // Jeuncy publiee.
        'match_departements' => (function () {
            $brut = trim((string) env('JEUNCY_MATCH_DEPARTEMENTS', '66'));

            if ($brut === '') {
                return [];
            }

            return $brut === '*'
                ? ['*']
                : array_values(array_filter(array_map('trim', explode(',', $brut))));
        })(),
        // Drapeau d'ouverture de « Decouvrir » cote CANDIDAT (MOBILE.md §10).
        //
        // Frein d'urgence, pas un interrupteur de lancement : le defaut est
        // VRAI. Le plan prevoyait d'ouvrir la pile candidat quand dix offres
        // Jeuncy existeraient a moins de 30 km ; la production n'en a qu'une,
        // et fermer sur ce critere reviendrait a masquer aussi les 7 779
        // offres partenaires, qui sont precisement le remplissage prevu en
        // attendant. Ce drapeau sert donc a refermer vite si la pile devait
        // montrer n'importe quoi, pas a attendre un seuil.
        //
        // Il ne touche QUE la pile du candidat : le deck employeur a sa
        // propre garde (match_departements ci-dessus), et un match deja noue
        // reste lisible des deux cotes quoi qu'il arrive.
        'match_actif' => filter_var(env('JEUNCY_MATCH_ACTIF', true), FILTER_VALIDATE_BOOLEAN),
        // Relances automatiques (matches:remind). FERME PAR DEFAUT, a
        // l'inverse de match_actif juste au-dessus.
        //
        // POURQUOI CETTE ASYMETRIE. La premiere passe reelle tombe sur tout
        // l'historique d'un coup — des candidatures et des interets vieux de
        // plusieurs mois, appartenant a de vraies personnes. Si la tache
        // etait planifiee des le deploiement, elle partirait au premier
        // passage du cron, dans l'heure, avant que quiconque ait pu regarder
        // ce qu'elle allait envoyer.
        //
        // La marche a suivre est donc : deployer, mesurer a blanc
        // (/deploy/{token}/matches-remind), puis mettre ce drapeau a true.
        'relances_actives' => filter_var(env('JEUNCY_RELANCES_ACTIVES', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
