# apps/mobile — application iOS et Android Jeuncy

Client natif de la plateforme Jeuncy (React Native + Expo). Il consomme la
**meme API Laravel que le site** : aucune logique metier ici, uniquement
l'interface. Le cadrage complet est dans `MOBILE.md` a la racine du depot.

## Lancer l'application

```bash
pnpm --filter mobile start
```

Scanner le QR code affiche avec l'appareil photo de l'iPhone (application
**Expo Go** installee). Le PC et le telephone doivent etre sur le meme Wi-Fi ;
sinon, ajouter `--tunnel` pour passer par internet.

Par defaut l'application vise `https://api.jeuncy.com/api`. Voir `.env.example`
pour viser un serveur local.

## Deux points de conception a connaitre

**Le refresh token ne vit pas dans un cookie.** Sur le web il est dans un
cookie httpOnly ; ici il est dans le coffre du telephone (Keychain iOS,
Keystore Android, via `lib/secure-store.ts`). L'application se declare aupres
de l'API par l'en-tete `X-Jeuncy-Client: mobile`, ce qui commande le mode
correspondant cote serveur (`AuthController`).

En mode mobile, `/auth/refresh` lit le jeton **dans le corps de la requete et
ignore tout cookie**. Ce n'est pas un detail d'implementation : sans cette
separation, un script injecte dans le navigateur pourrait appeler la route avec
cet en-tete, le navigateur joindrait le cookie automatiquement, et le serveur
renverrait le jeton long en clair — la protection httpOnly du site serait
annulee par une fonctionnalite mobile. Garde couverte par
`apps/api/tests/Feature/MobileAuthTest.php`.

**Les couleurs et les polices sont dupliquees, pas partagees.** `src/theme/`
reprend a la main les valeurs de `CLAUDE.md` section 2, que le web porte dans
`tailwind.config.ts`. Il n'existe aucune generation commune : toute evolution
de la charte doit etre reportee des deux cotes.

## Ce qui n'est pas encore la

Ordre de travail defini dans `MOBILE.md` section 7. La phase 0 (ce socle)
couvre navigation, theme, client API et authentification par email. Google
OAuth, les notifications push et les achats integres demandent un
*development build* — ils ne fonctionnent pas dans Expo Go — et donc le compte
Apple Developer.
