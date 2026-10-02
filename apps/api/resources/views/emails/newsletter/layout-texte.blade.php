{{--
    Coque de la version TEXTE BRUT.

    POURQUOI UNE VERSION TEXTE EXISTE. Un message qui ne porte qu'une partie
    HTML est un signal de spam reconnu par tous les filtres : une lettre
    envoyee a 125 personnes sans version texte a de bonnes chances d'arriver
    dans les indesirables, et le travail d'ecriture avec. Elle sert aussi aux
    clients mail en mode texte et aux lecteurs d'ecran.

    Le pied est ici et non dans l'edition, exactement comme dans la coque HTML :
    le lien de desinscription ne doit pas pouvoir etre oublie par celui qui
    redige.

    {!! !!} et non {{ }} sur le lien : la signature contient des « & » que
    l'echappement HTML transformerait en « &amp; », rendant le lien
    inutilisable dans un corps en texte brut.
--}}
{!! $corps !!}

---
Tu reçois ce message parce que tu as créé un compte candidat sur
jeuncy.com. Pour ne plus recevoir cette lettre : {!! $lienDesinscription !!}
Confidentialité : https://jeuncy.com/confidentialite
