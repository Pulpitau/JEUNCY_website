{{--
    Page de desinscription de la lettre hebdomadaire.

    DEUX ETATS, UNE SEULE VUE : la demande (un bouton a presser) et la
    confirmation. Les separer aurait double le nombre de fichiers a deployer
    pour quinze lignes de difference.

    AUCUNE DONNEE PERSONNELLE N'EST AFFICHEE — pas meme l'adresse email du
    destinataire. Le lien se retrouve dans un historique de navigateur, se
    transfere avec l'email, et peut etre ouvert par un antivirus de
    messagerie : la page ne doit rien apprendre a qui la regarde.

    Variables : $fait (bool), $action (URL signee a poster).
--}}
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Jeuncy — la lettre</title>
  </head>
  <body style="margin:0; padding:0; background-color:#FAFAF8; font-family:'Inter','Segoe UI',Arial,sans-serif; color:#1a1a1a;">
    <div style="max-width:520px; margin:0 auto; padding:48px 20px;">
      <div style="background-color:#FFFFFF; border-radius:16px; overflow:hidden; border:1px solid #e7e7e2;">
        <div style="height:5px; background-color:#FF2D55; background-image:linear-gradient(90deg,#FF2D55,#FF8A32);"></div>
        <div style="padding:32px 28px;">
          @if ($fait)
            <h1 style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-size:22px; color:#061D4F; margin:0 0 12px;">C'est fait</h1>
            <p style="font-size:15px; line-height:24px; margin:0 0 18px;">
              Tu ne recevras plus la lettre hebdomadaire de Jeuncy. Ton compte,
              lui, n'a pas bougé : tes candidatures, tes notifications et les
              messages liés à ton activité continuent d'arriver normalement.
            </p>
            <p style="font-size:14px; line-height:22px; color:#6b6b6b; margin:0;">
              Tu as changé d'avis ? Écris-nous à
              <a href="mailto:{{ $contact }}" style="color:#061D4F;">{{ $contact }}</a>.
            </p>
          @else
            <h1 style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-size:22px; color:#061D4F; margin:0 0 12px;">Ne plus recevoir la lettre</h1>
            <p style="font-size:15px; line-height:24px; margin:0 0 22px;">
              Un clic sur le bouton et la lettre hebdomadaire s'arrête. Ton
              compte et tes candidatures ne sont pas touchés.
            </p>
            {{-- Le bouton POSTE : un GET ne doit jamais desinscrire. Les
                 antivirus de messagerie et les apercus de lien ouvrent les
                 URL d'un email sans que personne n'ait clique — un GET
                 agissant aurait desinscrit des gens qui n'ont rien demande. --}}
            <form method="POST" action="{{ $action }}" style="margin:0;">
              <button type="submit" style="background-color:#FF2D55; color:#FFFFFF; font-family:'Poppins','Segoe UI',Arial,sans-serif; font-size:16px; font-weight:600; border:0; border-radius:999px; padding:14px 30px; cursor:pointer;">
                Confirmer la désinscription
              </button>
            </form>
            <p style="font-size:14px; line-height:22px; color:#6b6b6b; margin:22px 0 0;">
              Tu peux aussi simplement fermer cette page : rien ne sera changé.
            </p>
          @endif
        </div>
      </div>
      <p style="text-align:center; font-size:12px; color:#9ca3af; margin:18px 0 0;">Jeuncy — Match ton alternance</p>
    </div>
  </body>
</html>
