// Mise en forme du numero de contact pour l'affichage.
//
// Existe suite a une panne de production (2026-08-18) : pour afficher le
// numero par paires, la valeur avait ete saisie avec des espaces dans le .env
// du serveur (CONTACT_PHONE=09 87 11 33 49). phpdotenv refuse un espace dans
// une valeur non entouree de guillemets et leve une exception AVANT le
// demarrage de Laravel — toute l'API est tombee, pas seulement la page
// Contact. Le .env ne contient donc plus qu'une suite de chiffres, et la
// presentation se fait ici.
//
// Regle : on ne reformate que ce qu'on reconnait avec certitude. Tout numero
// hors des deux formats francais attendus est rendu tel quel plutot que
// decoupe au hasard — un numero mal espace reste lisible, un numero mal
// decoupe induit en erreur.
export function formatPhoneForDisplay(phone: string): string {
  const digits = phone.replace(/[^\d+]/g, '');

  // National a 10 chiffres : 0987113349 -> 09 87 11 33 49
  if (/^0\d{9}$/.test(digits)) {
    return digits.match(/\d{2}/g)!.join(' ');
  }

  // International francais : +33987113349 -> +33 9 87 11 33 49
  if (/^\+33\d{9}$/.test(digits)) {
    const national = digits.slice(3);
    return `+33 ${national[0]} ${national.slice(1).match(/\d{2}/g)!.join(' ')}`;
  }

  return phone;
}

// Le lien tel: ne doit contenir que des chiffres (et un eventuel +) : les
// espaces d'affichage n'ont rien a y faire.
export function phoneToTelHref(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, '')}`;
}
