<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @php
        // Marges/tailles de base calibrees pour ne jamais deborder sur une 2e
        // page avec un profil dense (voir CvService::contentScales). $scales
        // fournit trois facteurs quand le profil est peu fourni : 'section'
        // pour les grands espaces (padding de page, en-tete, entre sections),
        // 'item' pour les petits espaces entre elements d'une meme liste (ne
        // doivent pas devenir enormes, sinon ca a l'air casse), et 'font'
        // pour les tailles de texte du corps — un profil dense garde les
        // trois a 1.0 (mise en page compacte de reference).
        $sec = fn (float $base) => round($base * $scales['section']);
        $it = fn (float $base) => round($base * $scales['item']);
        $fs = fn (float $base) => round($base * $scales['font'], 1);
        // Accent propre au candidat, echantillonne dans le degrade signature
        // Jeuncy (voir CvService::palette) — reste dans la charte graphique
        // tout en variant d'un CV genere a l'autre.
        $primary = $palette['primary'];
        $accent = $palette['accent'];
    @endphp
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 12px; margin: 0; }

        .page { padding: {{ $sec(38) }}px 52px {{ $sec(30) }}px; }

        /* En-tete pleine largeur (photo + identite + contact), pas de bandeau
           colore plein-hauteur ici : contrairement a l'ancienne version, le
           gabarit n'a plus besoin du hack "sidebar-backdrop" positionne en
           absolu puisqu'aucune zone ne doit plus s'etendre sur toute la
           hauteur de la page. */
        .header { display: table; width: 100%; table-layout: fixed; margin-bottom: {{ $sec(18) }}px; }
        .header-photo { display: table-cell; width: 180px; vertical-align: top; }
        .header-info { display: table-cell; vertical-align: top; padding-left: 22px; }
        .header-logo { display: table-cell; width: 180px; vertical-align: top; text-align: right; }
        /* Meme taille que la photo de profil (demande explicite). */
        .header-logo-img { width: 170px; height: auto; }

        .avatar-img {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid {{ $accent }};
        }
        .avatar-fallback {
            display: inline-block;
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background-color: {{ $accent }};
            color: #ffffff;
            font-size: 52px;
            font-weight: bold;
            text-align: center;
            line-height: 170px;
        }

        .name { font-size: 23px; font-weight: bold; color: {{ $primary }}; margin: 0 0 3px; text-transform: uppercase; }
        .headline { font-size: 14px; color: #444444; margin: 0 0 8px; }
        .contact-row { font-size: 11px; color: #333333; margin: 0 0 4px; }
        .contact-row span { margin-right: 4px; }
        .meta-row { font-size: 10.5px; color: #777777; margin: 0; }

        /* Permis : affiche en evidence pres des coordonnees (demande explicite
           "tres important"), pas noye dans le meta-row discret age/adresse. */
        .license-row { font-size: 11px; font-weight: bold; color: {{ $accent }}; margin: 0 0 4px; }

        .header-divider { border-bottom: 2px solid {{ $primary }}; margin-bottom: {{ $sec(18) }}px; }

        /* Table plutot que flex/grid (non fiables sous dompdf) pour la mise en
           page a deux colonnes du corps du CV. */
        .columns { display: table; width: 100%; table-layout: fixed; }
        .col-side {
            display: table-cell;
            width: 30%;
            vertical-align: top;
            padding-right: 22px;
            border-right: 1px solid #DADFEA;
        }
        .col-main { display: table-cell; width: 70%; vertical-align: top; padding-left: 26px; }

        .section { margin-bottom: {{ $sec(16) }}px; }
        .section-heading {
            font-size: {{ $fs(12) }}px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: {{ $primary }};
            margin: 0 0 {{ $it(10) }}px;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $accent }};
        }

        .bio { font-size: {{ $fs(11.5) }}px; line-height: 1.6; color: #333333; margin: 0; }

        ul.bullet-list { margin: 0; padding-left: 16px; }
        ul.bullet-list li { font-size: {{ $fs(11) }}px; line-height: 1.5; color: #333333; margin-bottom: {{ $it(6) }}px; }

        .edu-item { margin-bottom: {{ $it(12) }}px; }
        .edu-school { font-size: {{ $fs(11.5) }}px; font-weight: bold; color: {{ $primary }}; margin: 0; }
        .edu-degree { font-size: {{ $fs(11) }}px; color: #333333; margin: 3px 0; }
        .edu-dates { font-size: {{ $fs(9.5) }}px; color: #999999; margin: 0; }

        .lang-item { margin-bottom: {{ $it(5) }}px; }
        .lang-name { font-size: {{ $fs(11) }}px; font-weight: bold; color: {{ $primary }}; }
        .lang-level { font-size: {{ $fs(10.5) }}px; color: #777777; }

        .hobbies-text { font-size: {{ $fs(10.5) }}px; line-height: 1.55; color: #333333; margin: 0; }

        .exp-item { margin-bottom: {{ $it(16) }}px; }
        .exp-head { display: table; width: 100%; table-layout: fixed; margin-bottom: 2px; }
        .exp-company { display: table-cell; font-size: {{ $fs(12.5) }}px; font-weight: bold; color: {{ $primary }}; }
        .exp-dates {
            display: table-cell;
            width: 140px;
            text-align: right;
            font-size: {{ $fs(9.5) }}px;
            color: #999999;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .exp-subhead { font-size: {{ $fs(11) }}px; color: {{ $accent }}; font-weight: bold; margin: 0 0 6px; }

    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-photo">
                @if($photoDataUri)
                    <img src="{{ $photoDataUri }}" class="avatar-img">
                @else
                    <div class="avatar-fallback">{{ strtoupper(mb_substr($profile->first_name, 0, 1).mb_substr($profile->last_name, 0, 1)) }}</div>
                @endif
            </div>
            <div class="header-info">
                <p class="name">{{ $profile->first_name }} {{ $profile->last_name }}</p>
                @if($profile->headline)
                    <p class="headline">{{ $profile->headline }}</p>
                @endif
                @if($profile->driving_license)
                    <p class="license-row">Permis {{ $profile->driving_license }}</p>
                @endif
                <p class="contact-row">
                    @if($profile->phone)<span>{{ $profile->phone }}</span> &middot; @endif
                    <span>{{ $profile->user->email }}</span>
                </p>
                @php
                    $locationParts = array_filter([
                        $profile->address,
                        trim(($profile->postal_code ?? '').' '.($profile->city ?? '')),
                    ]);
                @endphp
                @if($age || $locationParts)
                    <p class="meta-row">
                        @if($age){{ $age }} ans @endif
                        @if($age && $locationParts) &middot; @endif
                        {{ implode(', ', $locationParts) }}
                    </p>
                @endif
            </div>
            @if($logoDataUri)
                <div class="header-logo">
                    <img src="{{ $logoDataUri }}" class="header-logo-img">
                </div>
            @endif
        </div>
        <div class="header-divider"></div>

        <div class="columns">
            <div class="col-side">
                @if($profile->skills->isNotEmpty())
                    <div class="section">
                        <p class="section-heading">Compétences</p>
                        <ul class="bullet-list">
                            @foreach($profile->skills as $skill)
                                <li>{{ $skill->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($profile->languages->isNotEmpty())
                    <div class="section">
                        <p class="section-heading">Langues</p>
                        @foreach($profile->languages as $language)
                            <div class="lang-item">
                                <span class="lang-name">{{ $language->name }}</span>
                                <span class="lang-level"> — {{ $language->level }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($profile->educations->isNotEmpty())
                    <div class="section">
                        <p class="section-heading">Formations</p>
                        @foreach($profile->educations->sortByDesc('start_date') as $education)
                            <div class="edu-item">
                                <p class="edu-school">{{ $education->school }}</p>
                                <p class="edu-degree">{{ $education->degree }}</p>
                                <p class="edu-dates">
                                    {{ $education->start_date->format('Y') }} —
                                    {{ $education->end_date?->format('Y') ?? 'en cours' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($profile->hobbies)
                    <div class="section">
                        <p class="section-heading">Loisirs</p>
                        <p class="hobbies-text">{{ $profile->hobbies }}</p>
                    </div>
                @endif
            </div>

            <div class="col-main">
                @if($profile->bio)
                    <div class="section">
                        <p class="section-heading">Profil</p>
                        <p class="bio">{{ $profile->bio }}</p>
                    </div>
                @endif

                @if($profile->experiences->isNotEmpty())
                    <div class="section">
                        <p class="section-heading">Parcours professionnel</p>
                        @foreach($profile->experiences->sortByDesc('start_date') as $experience)
                            <div class="exp-item">
                                <div class="exp-head">
                                    <div class="exp-company">{{ $experience->company }}</div>
                                    <div class="exp-dates">
                                        {{ $experience->start_date->format('m/Y') }} —
                                        {{ $experience->end_date?->format('m/Y') ?? "aujourd'hui" }}
                                    </div>
                                </div>
                                <p class="exp-subhead">{{ $experience->title }}@if($experience->location) &middot; {{ $experience->location }}@endif</p>
                                @if($experience->description)
                                    @php
                                        $descriptionLines = collect(preg_split('/\r\n|\r|\n/', $experience->description))
                                            ->map(fn ($line) => trim($line))
                                            ->filter();
                                    @endphp
                                    <ul class="bullet-list">
                                        @foreach($descriptionLines as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
