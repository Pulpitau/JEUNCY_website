import {
  Building2,
  CreditCard,
  FileText,
  GraduationCap,
  LayoutDashboard,
  Send,
  Shield,
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
      { to: '/mes-visios', label: 'Visio démo', icon: Video },
      { to: '/mes-paiements', label: 'Paiements', icon: CreditCard },
      ...common,
    ];
  }

  if (role === UserRole.ADMIN) {
    return [{ to: '/admin', label: 'Administration', icon: LayoutDashboard }, ...common];
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
