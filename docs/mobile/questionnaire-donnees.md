# Brouillon — questionnaires données/âge (Google Data Safety + Apple Privacy)

> À valider par Pierre avant soumission : c'est une déclaration légale
> (RGPD/App Store), pas juste un formulaire.

## Âge minimum

16 ans partout (déclaré à l'inscription côté serveur depuis 2026-10-09).
Les deux formulaires demandent "âge minimum des utilisateurs" → **16**.

## Données collectées (base commune aux deux stores)

| Donnée                                         | Collectée                       | Pourquoi                           | Partagée avec un tiers ?                                                                                                 |
| ---------------------------------------------- | ------------------------------- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Email, mot de passe                            | Oui                             | Compte, connexion                  | Non (sauf Google si connexion Google)                                                                                    |
| Nom, prénom, date de naissance                 | Oui                             | Profil, vérif. âge                 | Non (nom complet jamais montré aux entreprises, voir `CandidateCardPresenter` — juste prénom + initiale + tranche d'âge) |
| Téléphone, adresse                             | Oui (facultatif)                | Contact après candidature          | Non                                                                                                                      |
| Photo de profil                                | Oui (bientôt obligatoire)       | Carte du deck de match             | Visible par les entreprises seulement si le candidat l'autorise (`show_photo_to_employers`)                              |
| CV (PDF, expériences, formations, compétences) | Oui                             | Candidature                        | Visible par une entreprise seulement après candidature reçue                                                             |
| Localisation approximative (GPS arrondi ~1 km) | Oui, facultative                | Rayon de recherche d'offres        | Non — jamais lue par le deck entreprise (colonnes séparées en base)                                                      |
| SIRET entreprise                               | Oui (comptes COMPANY/CFA)       | Vérifier que l'entreprise existe   | Consultée auprès du registre public (recherche-entreprises.api.gouv.fr)                                                  |
| Email (envoi transactionnel)                   | —                               | Mot de passe oublié, notifications | Oui : **Resend** (sous-traitant technique, envoi d'email uniquement)                                                     |
| Email/nom (connexion Google)                   | Si l'utilisateur choisit Google | Authentification                   | Oui : **Google** (OAuth)                                                                                                 |

## Ce qui n'est PAS collecté

- Pas de paiement (Stripe est câblé mais désactivé, mode gratuit)
- Pas de micro/caméra en continu (la photo de profil passe par le sélecteur
  d'image natif, pas un flux vidéo)
- Pas de contacts, pas d'identifiants publicitaires, pas de tracking tiers

## Chiffrement et suppression

- Données chiffrées en transit (HTTPS)
- Suppression de compte accessible depuis l'app (écran "Confidentialité et
  données"), email de confirmation requis
- Export des données personnelles possible (JSON, feuille de partage iOS)

## Google Play Data Safety — réponses suggérées

- Localisation approximative : collectée, facultative, usage "fonctionnalité
  de l'app", **non partagée**
- Infos personnelles (nom, email, téléphone, adresse) : collectées, usage
  "fonctionnalité de l'app" + "comptes", **non partagées** avec des tiers
  publicitaires
- Photos : collectées, facultatives, usage "fonctionnalité de l'app"
- Fichiers/documents (CV) : collectés, usage "fonctionnalité de l'app"
- Chiffrement en transit : Oui
- Suppression des données : Oui, depuis l'app

## Apple App Privacy (Nutrition Label) — réponses suggérées

- "Data Used to Track You" : **Aucune**
- "Data Linked to You" : identifiants de compte, infos de contact,
  localisation approximative, contenu utilisateur (photo, CV)
- "Data Not Linked to You" : aucune à déclarer

## Reste à faire

- Pierre valide ou corrige ce tableau avant de le reporter dans les consoles
- Captures d'écran (étape séparée, nécessite un téléphone)
