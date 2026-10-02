{{--
    Depot et armement des editions de la lettre hebdomadaire.

    Page d'outillage, protegee par le meme DEPLOY_TOKEN que le reste de
    /deploy : elle n'existe pas tant que la variable est vide. Volontairement
    sans style elabore — c'est un outil, pas une page du produit, et chaque
    ligne de CSS ici serait une ligne a maintenir pour personne.

    AUCUNE ADRESSE EMAIL N'APPARAIT : la liste des destinataires ne sort pas
    du serveur, et cette page se consulte depuis un navigateur ordinaire.

    Variables : $token, $editions, $aide, $message, $erreur.
--}}
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Jeuncy — editions de la lettre</title>
    <style>
      body { font-family: system-ui, Segoe UI, Arial, sans-serif; margin: 0; padding: 24px; background: #FAFAF8; color: #1a1a1a; }
      .boite { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #e7e7e2; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
      h1 { font-size: 20px; color: #061D4F; margin: 0 0 4px; }
      h2 { font-size: 16px; color: #061D4F; margin: 0 0 12px; }
      label { display: block; font-weight: 600; font-size: 13px; margin: 14px 0 4px; }
      input[type=text], textarea { width: 100%; box-sizing: border-box; font-family: ui-monospace, Consolas, monospace; font-size: 13px; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
      textarea { min-height: 180px; }
      button { background: #FF2D55; color: #fff; border: 0; border-radius: 999px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; }
      button.sobre { background: #061D4F; padding: 6px 14px; font-size: 13px; }
      table { width: 100%; border-collapse: collapse; font-size: 13px; }
      th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
      code { background: #f3f3f0; padding: 1px 5px; border-radius: 4px; }
      .avis { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
      .ok { background: #e8f6ee; color: #14532d; }
      .ko { background: #fdeaea; color: #7f1d1d; }
      .note { font-size: 13px; color: #555; line-height: 20px; }
    </style>
  </head>
  <body>
    @if ($message)
      <div class="boite avis ok">{{ $message }}</div>
    @endif
    @if ($erreur)
      <div class="boite avis ko">{{ $erreur }}</div>
    @endif

    <div class="boite">
      <h1>La lettre Jeuncy — editions</h1>
      <p class="note">
        Deposer une edition ne l'envoie pas et ne l'arme pas : elle arrive en
        <code>BROUILLON</code>. C'est <strong>armer</strong> qui la rend
        envoyable — et c'est a ce moment-la que les espaces reserves sont
        verifies. Une edition <code>ENVOYEE</code> ne repart jamais.
      </p>
    </div>

    <div class="boite">
      <h2>Editions</h2>
      @if ($editions->isEmpty())
        <p class="note">Aucune edition pour l'instant.</p>
      @else
        <table>
          <tr><th>Identifiant</th><th>Objet</th><th>Statut</th><th>Envois</th><th>Actions</th></tr>
          @foreach ($editions as $edition)
            <tr>
              <td><code>{{ $edition->slug }}</code></td>
              <td>{{ $edition->subject }}</td>
              <td>
                {{ $edition->status->value }}
                @if ($edition->sent_at)
                  <br /><span class="note">{{ $edition->sent_at->format('d/m/Y H:i') }}</span>
                @endif
              </td>
              <td>{{ $edition->sent_count }} ok / {{ $edition->failed_count }} ko</td>
              <td>
                <a href="/deploy/{{ $token }}/newsletter/editions/{{ $edition->slug }}/apercu" target="_blank">Apercu HTML</a><br />
                <a href="/deploy/{{ $token }}/newsletter/editions/{{ $edition->slug }}/apercu?format=texte" target="_blank">Apercu texte</a><br />
                <a href="/deploy/{{ $token }}/newsletter?edition={{ $edition->slug }}" target="_blank">Compter (a blanc)</a>
                @if ($edition->status->value !== 'ENVOYEE')
                  <form method="POST" action="/deploy/{{ $token }}/newsletter/editions/{{ $edition->slug }}/statut" style="margin-top:8px;">
                    <input type="hidden" name="prete" value="{{ $edition->status->value === 'PRETE' ? '0' : '1' }}" />
                    <button class="sobre" type="submit">{{ $edition->status->value === 'PRETE' ? 'Desarmer' : 'Armer (PRETE)' }}</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </table>
      @endif
    </div>

    <div class="boite">
      <h2>Deposer ou corriger une edition</h2>
      <p class="note">
        Reutiliser un identifiant existant remplace son contenu et la remet en
        <code>BROUILLON</code> — sauf si elle est deja partie, ou si une partie
        des destinataires a deja ete servie.
      </p>
      <p class="note">
        <strong>Espaces reserves</strong>, remplaces au moment de l'envoi (donc
        toujours a jour), dans le HTML comme dans le texte :
        @foreach ($aide as $nom => $description)
          <br /><code>[[{{ $nom }}]]</code> — {{ $description }}
        @endforeach
        <br />Un espace reserve inconnu fait echouer l'envoi au lieu de partir tel quel.
      </p>
      <p class="note">
        Le <strong>corps seul</strong> : pas d'en-tete, pas de logo, pas de pied
        de page, pas de lien de desinscription — la coque s'en charge, et c'est
        ce qui garantit qu'aucune edition ne peut partir sans lien pour se
        desinscrire.
      </p>

      <form method="POST" action="/deploy/{{ $token }}/newsletter/editions">
        <label for="slug">Identifiant (ex : 2026-10-06-lettre-02)</label>
        <input type="text" id="slug" name="slug" required />

        <label for="subject">Objet du message</label>
        <input type="text" id="subject" name="subject" required />

        <label for="html">Corps HTML</label>
        <textarea id="html" name="html" required></textarea>

        <label for="text">Corps en texte brut</label>
        <textarea id="text" name="text" required></textarea>

        <p style="margin-top:18px;"><button type="submit">Deposer en brouillon</button></p>
      </form>
    </div>
  </body>
</html>
