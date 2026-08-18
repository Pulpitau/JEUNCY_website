import { Link } from 'react-router-dom';
import { ContactEmail } from '@/components/ContactEmail';

const LAST_UPDATED = '4 août 2026';

export function LegalNotice() {
  return (
    <main>
      <section className="mx-auto max-w-3xl px-4 py-16">
        <h1 className="font-poppins text-3xl font-bold tracking-tight text-foreground md:text-4xl">
          Mentions légales
        </h1>
        <p className="mt-2 font-inter text-sm text-muted-foreground">
          Dernière mise à jour : {LAST_UPDATED}
        </p>

        <div className="mt-10 space-y-10 font-inter text-sm leading-relaxed text-muted-foreground">
          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              1. Éditeur du site
            </h2>
            <p className="mt-3">
              Le site Jeuncy (jeuncy.com) est édité par :{' '}
              <span className="text-foreground">SAS Jeuncy</span>, Société par actions
              simplifiée au capital de <span className="text-foreground">1 000 €</span>,
              immatriculée au Registre du Commerce et des Sociétés de{' '}
              <span className="text-foreground">Paris</span> sous le numéro{' '}
              <span className="text-foreground">108 252 347</span>, dont le siège social
              est situé{' '}
              <span className="text-foreground">47 rue Vivienne, 75002 Paris</span>.
            </p>
            <p className="mt-3">
              Numéro de TVA intracommunautaire :{' '}
              <span className="text-foreground">FR83 108 252 347</span>
              <br />
              Directeur de la publication :{' '}
              <span className="text-foreground">Patrice Nicolas Juillard</span>, Directeur
              général
              <br />
              Contact : <ContactEmail />
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              2. Hébergement
            </h2>
            <p className="mt-3">
              Le site est hébergé par OVH SAS, 2 rue Kellermann, 59100 Roubaix, France
              (ovhcloud.com).
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              3. Propriété intellectuelle
            </h2>
            <p className="mt-3">
              L'ensemble des éléments composant le site Jeuncy (textes, logos, charte
              graphique, iconographie, code source, base de données) est protégé par le
              droit de la propriété intellectuelle. Toute reproduction, représentation,
              modification ou exploitation, totale ou partielle, sans autorisation écrite
              préalable, est interdite et susceptible de constituer une contrefaçon.
            </p>
            <p className="mt-3">
              Le nom « Jeuncy » et le logo associé sont des éléments distinctifs de
              l'éditeur et ne peuvent être utilisés sans autorisation.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              4. Données personnelles
            </h2>
            <p className="mt-3">
              Le traitement des données personnelles collectées sur le site est détaillé
              dans notre{' '}
              <Link to="/confidentialite" className="text-primary hover:underline">
                politique de confidentialité
              </Link>
              , conforme au Règlement Général sur la Protection des Données (RGPD) et à la
              loi « Informatique et Libertés ».
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              5. Cookies
            </h2>
            <p className="mt-3">
              Le site utilise uniquement des cookies techniques nécessaires à son
              fonctionnement (préférence de thème clair/sombre, maintien de la session de
              connexion). Aucun cookie de mesure d'audience ou de publicité n'est déposé.
              Plus de détails dans la{' '}
              <Link to="/confidentialite" className="text-primary hover:underline">
                politique de confidentialité
              </Link>
              .
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              6. Limitation de responsabilité
            </h2>
            <p className="mt-3">
              L'éditeur s'efforce d'assurer l'exactitude et la mise à jour des
              informations diffusées sur le site, mais ne peut garantir l'absence d'erreur
              ou d'interruption du service. L'éditeur ne saurait être tenu responsable des
              dommages directs ou indirects résultant de l'accès ou de l'utilisation du
              site, y compris l'inaccessibilité, les pertes de données ou la présence de
              virus.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              7. Droit applicable
            </h2>
            <p className="mt-3">
              Les présentes mentions légales sont soumises au droit français. Tout litige
              relatif à leur interprétation ou à leur exécution relève de la compétence
              exclusive des tribunaux français.
            </p>
          </section>

          <section>
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              8. Contact
            </h2>
            <p className="mt-3">
              Pour toute question relative au site ou à ces mentions légales :{' '}
              <ContactEmail />
            </p>
          </section>
        </div>
      </section>
    </main>
  );
}
