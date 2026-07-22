import { Link } from 'react-router-dom';

const LAST_UPDATED = '22 juillet 2026';

const DATA_CATEGORIES = [
  {
    title: 'Tous les comptes',
    items: ['Adresse email', 'Mot de passe (stocké de façon chiffrée)', 'Rôle du compte'],
  },
  {
    title: 'Candidats',
    items: [
      'Nom, prénom, titre professionnel, téléphone, date de naissance, adresse',
      'Photo de profil (facultative)',
      'Expériences, formations, compétences, langues, logiciels, loisirs, permis de conduire',
      'CV générés (PDF) et lettres de motivation envoyées',
    ],
  },
  {
    title: 'Entreprises et CFA',
    items: [
      'Raison sociale, SIRET, description, site web, ville',
      'Offres publiées et candidatures reçues',
    ],
  },
  {
    title: 'Paiement',
    items: [
      'Les coordonnées bancaires ne sont jamais stockées par Jeuncy : le paiement des offres est traité directement par Stripe',
      'Jeuncy conserve uniquement le statut et le montant du paiement, à des fins de facturation',
    ],
  },
  {
    title: 'Visioconférence',
    items: [
      'Nom de salle unique généré pour chaque démonstration',
      "Le flux audio/vidéo transite par l'infrastructure Jitsi (meet.jit.si) et n'est ni stocké ni enregistré par Jeuncy",
    ],
  },
] as const;

export function PrivacyPolicy() {
  return (
    <main>
      <section className="mx-auto max-w-3xl px-4 py-16">
        <h1 className="font-poppins text-3xl font-bold tracking-tight text-foreground md:text-4xl">
          Politique de confidentialité
        </h1>
        <p className="mt-2 font-inter text-sm text-muted-foreground">
          Dernière mise à jour : {LAST_UPDATED}
        </p>

        <div className="mt-10 space-y-10 font-inter text-sm leading-relaxed text-muted-foreground">
          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              1. Responsable du traitement
            </h2>
            <p className="mt-3">
              Jeuncy (
              <span className="text-foreground">[Raison sociale à compléter]</span>, voir
              nos{' '}
              <Link to="/mentions-legales" className="text-primary hover:underline">
                mentions légales
              </Link>
              ) est responsable du traitement des données personnelles décrites dans cette
              politique, en tant qu'éditeur de la plateforme jeuncy.com.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              2. Données que nous collectons
            </h2>
            <p className="mt-3">
              Selon le type de compte et l'usage de la plateforme, nous collectons les
              données suivantes :
            </p>
            <div className="mt-4 space-y-4">
              {DATA_CATEGORIES.map((category) => (
                <div key={category.title}>
                  <p className="font-medium text-foreground">{category.title}</p>
                  <ul className="mt-1 list-inside list-disc space-y-1">
                    {category.items.map((item) => (
                      <li key={item}>{item}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              3. Finalités du traitement
            </h2>
            <ul className="mt-3 list-inside list-disc space-y-1">
              <li>Créer et gérer votre compte et votre session (authentification)</li>
              <li>
                Mettre en relation les candidats avec les entreprises et CFA (recherche
                d'offres, candidatures, suivi de statut)
              </li>
              <li>
                Générer votre CV au format PDF à partir des informations de votre profil
              </li>
              <li>Permettre la publication et le paiement des offres d'emploi</li>
              <li>Organiser des visioconférences de démonstration</li>
              <li>
                Vous envoyer des notifications liées à votre activité sur la plateforme
              </li>
              <li>Assurer la sécurité du site et prévenir la fraude</li>
            </ul>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              4. Base légale
            </h2>
            <p className="mt-3">
              Ces traitements reposent sur l'exécution du contrat qui vous lie à Jeuncy
              (création de compte, mise en relation, génération de CV, paiement des
              offres), sur votre consentement (participation à une visioconférence de
              démonstration) et, ponctuellement, sur l'intérêt légitime de Jeuncy à
              assurer la sécurité et le bon fonctionnement de la plateforme.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              5. Destinataires des données
            </h2>
            <p className="mt-3">
              Vos données ne sont communiquées qu'aux destinataires suivants :
            </p>
            <ul className="mt-3 list-inside list-disc space-y-1">
              <li>
                L'équipe Jeuncy, dans la limite de ce qui est nécessaire à son activité
              </li>
              <li>
                Les entreprises ou CFA auxquels vous postulez (uniquement les informations
                de votre profil et de votre candidature)
              </li>
              <li>Stripe, pour le traitement sécurisé des paiements des offres</li>
              <li>
                Resend, pour l'envoi des emails transactionnels (réinitialisation de mot
                de passe, notifications)
              </li>
              <li>
                Jitsi (meet.jit.si), pour l'hébergement du flux de visioconférence lors
                d'une démonstration
              </li>
              <li>OVH, notre hébergeur, pour le stockage technique des données</li>
            </ul>
            <p className="mt-3">
              Jeuncy ne vend ni ne loue vos données personnelles à des tiers à des fins
              commerciales.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              6. Durée de conservation
            </h2>
            <ul className="mt-3 list-inside list-disc space-y-1">
              <li>
                Les données de votre compte sont conservées tant que celui-ci est actif
              </li>
              <li>
                En cas de suppression de votre compte, vos données sont supprimées dans un
                délai raisonnable, à l'exception des données que nous devons conserver
                pour respecter une obligation légale
              </li>
              <li>
                Les données de facturation liées aux paiements sont conservées pendant la
                durée exigée par la réglementation comptable et fiscale, même après la
                suppression du compte associé
              </li>
              <li>
                Un CV généré et non régénéré depuis 15 jours est automatiquement archivé
                (il reste consultable mais n'est plus mis à jour automatiquement)
              </li>
            </ul>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              7. Sécurité
            </h2>
            <p className="mt-3">
              Les mots de passe sont stockés de façon chiffrée et ne sont jamais
              accessibles en clair. Les accès à la plateforme sont protégés par un système
              de jetons d'authentification (JWT) à durée de vie limitée. Toute déconnexion
              ou réinitialisation de mot de passe invalide immédiatement les sessions
              précédemment ouvertes.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              8. Cookies
            </h2>
            <p className="mt-3">
              Jeuncy n'utilise que des cookies strictement nécessaires au fonctionnement
              du site : la mémorisation de votre préférence d'affichage (thème clair ou
              sombre) et le maintien de votre session de connexion. Aucun cookie
              publicitaire ou de mesure d'audience tiers n'est déposé sur le site.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              9. Transferts hors Union européenne
            </h2>
            <p className="mt-3">
              Certains de nos prestataires (Stripe, Resend, l'infrastructure publique
              Jitsi) peuvent être amenés à traiter des données en dehors de l'Union
              européenne. Ces prestataires s'appuient sur des mécanismes reconnus par le
              RGPD (clauses contractuelles types ou décision d'adéquation) pour encadrer
              ces transferts.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              10. Vos droits
            </h2>
            <p className="mt-3">
              Conformément au RGPD, vous disposez d'un droit d'accès, de rectification,
              d'effacement, de limitation et d'opposition au traitement de vos données,
              ainsi que d'un droit à la portabilité. Vous pouvez exercer ces droits en
              nous contactant à l'adresse{' '}
              <span className="text-foreground">contact@jeuncy.com</span>. Vous disposez
              également du droit d'introduire une réclamation auprès de la Commission
              Nationale de l'Informatique et des Libertés (CNIL), www.cnil.fr.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              11. Modification de cette politique
            </h2>
            <p className="mt-3">
              Cette politique de confidentialité peut être mise à jour pour refléter des
              évolutions du site ou de la réglementation. La date de dernière mise à jour
              en haut de cette page reflète la version en vigueur.
            </p>
          </section>
        </div>
      </section>
    </main>
  );
}
