{{--
    Coque HTML commune a toutes les editions de la lettre hebdomadaire.

    C'EST LE SEUL FICHIER DE LA LETTRE QUI SOIT DU CODE. Le corps de chaque
    edition vit en base (table newsletter_editions) et est injecte ici : une
    nouvelle lettre se depose depuis le navigateur, sans aucun envoi FTP. La
    coque, elle, ne change pas d'une semaine a l'autre.

    CE QUE LA COQUE PORTE, ET QUI N'EST DONC JAMAIS LA RESPONSABILITE DE CELUI
    QUI ECRIT L'EDITION : l'en-tete, le logo, le rappel de pourquoi on recoit
    ce message, et surtout le LIEN DE DESINSCRIPTION. Le laisser au redacteur
    aurait voulu dire qu'une edition peut partir sans — une fois suffit.

    POURQUOI TOUT EST EN style="" INLINE. Gmail, Outlook et la plupart des
    webmails suppriment purement et simplement une balise <style> en tete de
    document : une feuille de style ici produirait un email sans aucune mise
    en forme chez la majorite des destinataires. Meme raison pour la mise en
    page en <table> plutot qu'en flex/grid, qu'Outlook (moteur Word) ne sait
    pas rendre.

    Le degrade signature porte AUSSI un background-color corail : un client
    qui ignore linear-gradient affiche alors du corail, pas du blanc.

    Largeur 600 px maximum, et width:100% dessous : au-dela, les webmails de
    bureau coupent, et en dessous de 600 px le tableau se replie tout seul sur
    un telephone — c'est de la que vient la majorite du public (CLAUDE.md §10).

    Variables : $corps (HTML de l'edition, deja substitue), $preheader,
    $lienDesinscription (URL signee, propre a CE destinataire).
--}}
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Jeuncy — la lettre</title>
  </head>
  <body style="margin:0; padding:0; background-color:#FAFAF8;">
    {{-- Pre-header : ce que la boite mail affiche a cote de l'objet, puis masque. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFAF8;">
      <tr>
        <td align="center" style="padding:24px 16px;">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#FFFFFF; border-radius:16px; overflow:hidden;">

            {{-- Degrade signature : si un client mail l'ignore, il reste du corail. --}}
            <tr><td style="height:5px; background-color:#FF2D55; background-image:linear-gradient(90deg,#FF2D55,#FF8A32); font-size:0; line-height:0;">&nbsp;</td></tr>

            <tr>
              <td align="center" style="background-color:#061D4F; padding:28px 24px;">
                <img src="https://jeuncy.com/logo/logo-dark.png" width="64" height="64" alt="Jeuncy" style="display:block; border:0; width:64px; height:64px;" />
                <div style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#FF8A32; padding-top:12px;">La lettre Jeuncy</div>
                <div style="font-family:'Poppins','Segoe UI',Arial,sans-serif; font-size:24px; font-weight:700; color:#FFFFFF; padding-top:4px;">Match ton alternance</div>
              </td>
            </tr>

            {{-- Le corps de l'edition, tel qu'il a ete depose. UNE SEULE
                 cellule : celui qui redige ecrit du HTML ordinaire (des <p>,
                 des <div>, un tableau imbrique s'il veut un encadre) et n'a
                 pas a connaitre la structure en lignes de la coque. --}}
            <tr>
              <td style="padding:32px 24px 0 24px; font-family:'Inter','Segoe UI',Arial,sans-serif; font-size:16px; line-height:26px; color:#1a1a1a;">
                {!! $corps !!}
              </td>
            </tr>

            <tr><td style="padding:28px 24px 0 24px;"><div style="height:1px; background-color:#e7e7e2; font-size:0; line-height:0;">&nbsp;</div></td></tr>

            <tr>
              <td align="center" style="padding:18px 24px 28px 24px; font-family:'Inter','Segoe UI',Arial,sans-serif; font-size:12px; line-height:20px; color:#6b6b6b;">
                Tu reçois ce message parce que tu as créé un compte candidat sur
                <a href="https://jeuncy.com" style="color:#061D4F; text-decoration:underline;">jeuncy.com</a>.<br />
                <a href="{{ $lienDesinscription }}" style="color:#6b6b6b; text-decoration:underline;">Je ne veux plus recevoir cette lettre</a>
                &nbsp;·&nbsp;
                <a href="https://jeuncy.com/confidentialite" style="color:#6b6b6b; text-decoration:underline;">Confidentialité</a>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
