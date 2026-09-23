import {
  Building2,
  FileText,
  GraduationCap,
  Heart,
  LayoutDashboard,
  Search,
  Send,
  Shield,
  Sparkles,
  User,
  Video,
  type LucideIcon,
} from 'lucide-react';
import { UserRole } from '@jeuncy/shared';

export interface AccountLink {
  to: string;
  label: string;
  icon: LucideIcon;
}

// Source unique des liens "espace personnel" : consommee a la fois par le
// menu deroulant du profil (desktop, voir UserMenu) et par le panneau
// mobile de la Navbar. Sans ca, les deux listes divergeaient a chaque ajout
// de page (c'etait le cas avant : 2 blocs JSX quasi identiques a maintenir
// en parallele dans Navbar.tsx).
export function accountLinksFor(role: string): AccountLink[] {
  const common: AccountLink[] = [
    { to: '/mon-compte/confidentialite', label: 'Confidentialité', icon: Shield },
  ];

  if (role === UserRole.CANDIDATE) {
    return [
      { to: '/profile', label: 'Mon profil', icon: User },
      // Avant « Mes candidatures » : un recruteur qui attend une reponse
      // passe avant une candidature deja envoyee.
      { to: '/interesses', label: 'Ils s’intéressent à toi', icon: Sparkles },
      { to: '/mes-matchs', label: 'Mes matchs', icon: Heart },
      { to: '/mes-candidatures', label: 'Mes candidatures', icon: Send },
      ...common,
    ];
  }

  if (role === UserRole.COMPANY || role === UserRole.CFA) {
    return [
      {
        to: '/organization',
        label: role === UserRole.COMPANY ? 'Mon entreprise' : 'Mon CFA',
        icon: role === UserRole.COMPANY ? Building2 : GraduationCap,
      },
      { to: '/mes-offres', label: 'Mes offres', icon: FileText },
      { to: '/mes-matchs', label: 'Mes matchs', icon: Heart },
      { to: '/candidats', label: 'Candidats', icon: Search },
      { to: '/mes-visios', label: 'Visio démo', icon: Video },
      // « Paiements » retire du menu le 2026-09-15 : Jeuncy est gratuit pour
      // les entreprises, un onglet de paiement contredirait le message. La
      // page /mes-paiements existe toujours pour l'historique d'un compte
      // qui aurait paye avant.
      ...common,
    ];
  }

  // L'admin a acces a la CVtheque comme un client abonne (voir
  // SubscriptionService::hasPaidAccess) : c'est la fonctionnalite payante
  // phare, l'equipe doit pouvoir voir exactement ce qu'elle vend.
  // Pas de "Mes offres" ni "Mes candidatures" ici : un admin n'a ni
  // entreprise ni CFA rattache, ces pages seraient vides par construction.
  if (role === UserRole.ADMIN) {
    return [
      { to: '/admin', label: 'Administration', icon: LayoutDashboard },
      { to: '/candidats', label: 'Candidats', icon: Search },
      ...common,
    ];
  }

  // Membre de l'equipe Jeuncy : la CVtheque, et rien d'autre. Il verifie
  // que les candidats qu'il a eus au telephone ont bien termine leur
  // inscription — il n'a besoin ni de l'administration, ni des offres.
  if (role === UserRole.STAFF) {
    return [{ to: '/candidats', label: 'Candidats', icon: Search }, ...common];
  }

  return common;
}

// Libelle court du role, affiche sous l'email dans le menu profil pour que
// l'utilisateur sache immediatement "en tant que quoi" il est connecte.
export function roleLabel(role: string): string {
  switch (role) {
    case UserRole.CANDIDATE:
      return 'Candidat';
    case UserRole.COMPANY:
      return 'Entreprise';
    case UserRole.CFA:
      return 'CFA';
    case UserRole.STAFF:
      return 'Équipe Jeuncy';
    case UserRole.ADMIN:
      return 'Administrateur';
    default:
      return '';
  }
}

// Initiales pour la pastille du menu : "lea.girard@..." -> "LG",
// "rh@..." -> "RH". Purement decoratif (aria-hidden cote UserMenu).
export function initialsFromEmail(email: string): string {
  const local = email.split('@')[0] ?? '';
  const parts = local.split(/[._-]+/).filter(Boolean);

  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  return local.slice(0, 2).toUpperCase();
}
