import { useEffect } from 'react';

const SITE_NAME = 'Jeuncy';
const CANONICAL_ORIGIN = 'https://jeuncy.com';

function setMeta(selector: string, attribute: string, value: string) {
  let element = document.head.querySelector<HTMLMetaElement>(selector);
  if (!element) {
    element = document.createElement('meta');
    element.setAttribute(attribute, selector.replace(/^meta\[[^=]+="|"\]$/g, ''));
    document.head.appendChild(element);
  }
  element.setAttribute('content', value);
}

// Titre, description et URL canonique propres a chaque page.
//
// Sur une application monopage, tout est servi par le meme index.html : sans
// ce hook, Google voit le meme titre et la meme description sur /offres,
// /entreprises et /contact. Deux pages indiscernables se concurrencent dans
// l'index, et aucune ne ressort — c'est l'une des raisons pour lesquelles le
// site etait introuvable.
//
// Le titre est ecrit cote client, donc lu par les moteurs qui executent le
// JavaScript (Google le fait). Les reseaux sociaux, eux, ne l'executent pas :
// ils s'en tiennent aux balises Open Graph de index.html, volontairement
// generiques pour cette raison.
export function usePageMetadata(title: string, description?: string, path?: string) {
  useEffect(() => {
    document.title = `${title} — ${SITE_NAME}`;

    if (description) {
      setMeta('meta[name="description"]', 'name', description);
    }

    const canonical = document.head.querySelector<HTMLLinkElement>(
      'link[rel="canonical"]',
    );
    if (canonical) {
      canonical.href = `${CANONICAL_ORIGIN}${path ?? window.location.pathname}`;
    }
  }, [title, description, path]);
}
