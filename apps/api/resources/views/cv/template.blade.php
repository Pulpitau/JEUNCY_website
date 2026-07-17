<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 11px; }

        .header { background-color: #061D4F; color: #FFFFFF; padding: 18px 20px; }
        .header .name { font-size: 22px; font-weight: bold; margin: 0; }
        .header .contact { font-size: 10px; margin-top: 6px; color: #FAFAF8; }

        .section { margin-top: 18px; }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #FF2D55;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #FF8A32;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .bio { font-size: 11px; line-height: 1.5; }

        .item { margin-bottom: 10px; }
        .item-title { font-size: 12px; font-weight: bold; margin: 0; }
        .item-subtitle { font-size: 10px; color: #444444; margin: 1px 0; }
        .item-dates { font-size: 9px; color: #777777; margin: 1px 0; }
        .item-description { font-size: 10px; line-height: 1.4; margin-top: 3px; }

        .skills { font-size: 10px; }
        .skill-badge {
            display: inline-block;
            background-color: #FAFAF8;
            border: 1px solid #FF8A32;
            color: #061D4F;
            padding: 3px 10px;
            margin: 0 6px 6px 0;
        }

        .footer { margin-top: 24px; font-size: 8px; color: #999999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <p class="name">{{ $profile->first_name }} {{ $profile->last_name }}</p>
        <div class="contact">
            @if($profile->city){{ $profile->city }}@if($profile->postal_code) ({{ $profile->postal_code }})@endif @endif
            @if($profile->phone) &middot; {{ $profile->phone }} @endif
            &middot; {{ $profile->user->email }}
        </div>
    </div>

    @if($profile->bio)
        <div class="section">
            <div class="section-title">À propos</div>
            <p class="bio">{{ $profile->bio }}</p>
        </div>
    @endif

    @if($profile->experiences->isNotEmpty())
        <div class="section">
            <div class="section-title">Expériences</div>
            @foreach($profile->experiences->sortByDesc('start_date') as $experience)
                <div class="item">
                    <p class="item-title">{{ $experience->title }}</p>
                    <p class="item-subtitle">{{ $experience->company }}@if($experience->location) &middot; {{ $experience->location }}@endif</p>
                    <p class="item-dates">
                        {{ $experience->start_date->format('m/Y') }} —
                        {{ $experience->end_date?->format('m/Y') ?? 'aujourd\'hui' }}
                    </p>
                    @if($experience->description)
                        <p class="item-description">{{ $experience->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($profile->educations->isNotEmpty())
        <div class="section">
            <div class="section-title">Formations</div>
            @foreach($profile->educations->sortByDesc('start_date') as $education)
                <div class="item">
                    <p class="item-title">{{ $education->degree }}</p>
                    <p class="item-subtitle">{{ $education->school }}@if($education->field_of_study) &middot; {{ $education->field_of_study }}@endif</p>
                    <p class="item-dates">
                        {{ $education->start_date->format('m/Y') }} —
                        {{ $education->end_date?->format('m/Y') ?? 'en cours' }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    @if($profile->skills->isNotEmpty())
        <div class="section">
            <div class="section-title">Compétences</div>
            <div class="skills">
                @foreach($profile->skills as $skill)
                    <span class="skill-badge">{{ $skill->name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">CV généré via Jeuncy — Ton alternance commence ici.</div>
</body>
</html>
