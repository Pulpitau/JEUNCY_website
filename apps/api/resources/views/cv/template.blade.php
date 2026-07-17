<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 10.5px; margin: 0; }

        /* Rectangle navy positionne derriere le contenu, en dehors du flux du
           tableau : dompdf n'etire pas le fond d'une cellule de tableau au-dela de
           son contenu (et "height" sur une table-cell casse carrement la pagination),
           donc le bandeau plein-hauteur de la sidebar est peint separement, a la
           taille exacte d'une page A4, puis le contenu (tableau) est superpose par-dessus. */
        .sidebar-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 34%;
            height: 842pt;
            background-color: #061D4F;
            z-index: 0;
        }

        /* Un positioned descendant peint toujours au-dessus du flux normal, quel
           que soit son ordre dans le DOM : le tableau de contenu doit donc etre
           lui-meme positionne avec un z-index superieur pour rester visible
           par-dessus le bandeau navy. */
        .layout { position: relative; z-index: 1; display: table; width: 100%; table-layout: fixed; }

        .sidebar {
            display: table-cell;
            width: 34%;
            color: #FFFFFF;
            padding: 34px 22px;
            vertical-align: top;
        }
        .main {
            display: table-cell;
            width: 66%;
            background-color: #FFFFFF;
            padding: 36px 34px;
            vertical-align: top;
        }

        .avatar-wrap { text-align: center; margin-bottom: 16px; }
        .avatar-img {
            width: 104px;
            height: 104px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #FF8A32;
        }
        .avatar-fallback {
            display: inline-block;
            width: 104px;
            height: 104px;
            border-radius: 50%;
            background-color: #FF2D55;
            color: #ffffff;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            line-height: 104px;
        }

        .name { text-align: center; font-size: 17px; font-weight: bold; margin: 0; color: #ffffff; }
        .name-divider { width: 38px; height: 2px; background-color: #FF8A32; margin: 10px auto 22px; }

        .sidebar-section { margin-bottom: 22px; }
        .sidebar-heading {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #FF8A32;
            margin: 0 0 9px;
        }
        .contact-item { font-size: 9.5px; margin-bottom: 6px; color: #E9ECF5; }

        .skill-pill {
            display: inline-block;
            font-size: 8.5px;
            background-color: #0D2A63;
            border: 1px solid #3E5490;
            color: #ffffff;
            border-radius: 10px;
            padding: 3px 9px;
            margin: 0 5px 5px 0;
        }

        .edu-item { margin-bottom: 12px; }
        .edu-degree { font-size: 10px; font-weight: bold; margin: 0; color: #ffffff; }
        .edu-school { font-size: 9px; color: #C7CEE3; margin: 2px 0; }
        .edu-dates { font-size: 8px; color: #8E9BC4; margin: 0; }

        .main-heading {
            font-size: 12px;
            font-weight: bold;
            color: #061D4F;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0 0 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #FF2D55;
        }
        .main-section { margin-bottom: 22px; }
        .bio { font-size: 10.5px; line-height: 1.6; color: #333333; margin: 0; }

        .exp-item { margin-bottom: 15px; padding-left: 14px; border-left: 3px solid #FF8A32; }
        .exp-title { font-size: 11px; font-weight: bold; color: #061D4F; margin: 0; }
        .exp-company { font-size: 9.5px; color: #FF2D55; font-weight: bold; margin: 2px 0; }
        .exp-dates {
            font-size: 8px;
            color: #999999;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 0 5px;
        }
        .exp-description { font-size: 9.5px; line-height: 1.5; color: #444444; margin: 0; }

        .footer { margin-top: 14px; font-size: 7.5px; color: #bbbbbb; }
    </style>
</head>
<body>
    <div class="sidebar-backdrop"></div>
    <div class="layout">
        <div class="sidebar">
            <div class="avatar-wrap">
                @if($photoDataUri)
                    <img src="{{ $photoDataUri }}" class="avatar-img">
                @else
                    <div class="avatar-fallback">{{ strtoupper(mb_substr($profile->first_name, 0, 1).mb_substr($profile->last_name, 0, 1)) }}</div>
                @endif
            </div>
            <p class="name">{{ $profile->first_name }} {{ $profile->last_name }}</p>
            <div class="name-divider"></div>

            <div class="sidebar-section">
                <p class="sidebar-heading">Contact</p>
                @if($profile->phone)
                    <div class="contact-item">{{ $profile->phone }}</div>
                @endif
                <div class="contact-item">{{ $profile->user->email }}</div>
                @if($profile->city)
                    <div class="contact-item">{{ $profile->city }}@if($profile->postal_code) ({{ $profile->postal_code }})@endif</div>
                @endif
            </div>

            @if($profile->skills->isNotEmpty())
                <div class="sidebar-section">
                    <p class="sidebar-heading">Compétences</p>
                    <div>
                        @foreach($profile->skills as $skill)
                            <span class="skill-pill">{{ $skill->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($profile->educations->isNotEmpty())
                <div class="sidebar-section">
                    <p class="sidebar-heading">Formations</p>
                    @foreach($profile->educations->sortByDesc('start_date') as $education)
                        <div class="edu-item">
                            <p class="edu-degree">{{ $education->degree }}</p>
                            <p class="edu-school">{{ $education->school }}</p>
                            <p class="edu-dates">
                                {{ $education->start_date->format('Y') }} —
                                {{ $education->end_date?->format('Y') ?? 'en cours' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="main">
            @if($profile->bio)
                <div class="main-section">
                    <p class="main-heading">Profil</p>
                    <p class="bio">{{ $profile->bio }}</p>
                </div>
            @endif

            @if($profile->experiences->isNotEmpty())
                <div class="main-section">
                    <p class="main-heading">Expériences</p>
                    @foreach($profile->experiences->sortByDesc('start_date') as $experience)
                        <div class="exp-item">
                            <p class="exp-title">{{ $experience->title }}</p>
                            <p class="exp-company">{{ $experience->company }}@if($experience->location) &middot; {{ $experience->location }}@endif</p>
                            <p class="exp-dates">
                                {{ $experience->start_date->format('m/Y') }} —
                                {{ $experience->end_date?->format('m/Y') ?? "aujourd'hui" }}
                            </p>
                            @if($experience->description)
                                <p class="exp-description">{{ $experience->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="footer">CV généré via Jeuncy — Ton alternance commence ici.</p>
        </div>
    </div>
</body>
</html>
