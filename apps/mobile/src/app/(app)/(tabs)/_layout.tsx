import { Ionicons } from '@expo/vector-icons';
import { UserRole } from '@jeuncy/shared';
import { Tabs } from 'expo-router';
import type { ColorValue } from 'react-native';

import { unreadCount, useNotifications } from '@/hooks/use-notifications';
import { isOrganizationRole } from '@/lib/api/organization';
import { useAuthStore } from '@/store/auth-store';
import { useTheme } from '@/theme/theme-provider';
import { fonts } from '@/theme/typography';

type IconName = keyof typeof Ionicons.glyphMap;

function tabIcon(active: IconName, inactive: IconName) {
  function TabIcon({
    color,
    focused,
    size,
  }: {
    color: ColorValue;
    focused: boolean;
    size: number;
  }) {
    return <Ionicons name={focused ? active : inactive} color={color} size={size} />;
  }

  return TabIcon;
}

// Barre d'onglets de l'espace connecte. Les onglets propres au candidat sont
// masques (href: null) pour les autres roles plutot que rendus inaccessibles
// apres coup : un onglet qu'on ne peut pas utiliser ne doit pas exister.
// L'espace entreprise / CFA arrive en phase 3 avec ses propres onglets.
export default function TabsLayout() {
  const { colors } = useTheme();
  const role = useAuthStore((state) => state.user?.role);
  const isCandidate = role === UserRole.CANDIDATE;
  const isOrganization = isOrganizationRole(role);
  // Le badge de l'onglet suit le meme compteur que l'ecran : une seule
  // requete, partagee par TanStack Query.
  const nonLues = unreadCount(useNotifications().data);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.accent,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarStyle: { backgroundColor: colors.surface, borderTopColor: colors.border },
        tabBarLabelStyle: { fontFamily: fonts.bodyMedium, fontSize: 11 },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Découvrir',
          tabBarIcon: tabIcon('compass', 'compass-outline'),
          href: isOrganization ? null : undefined,
        }}
      />
      <Tabs.Screen
        name="mes-offres"
        options={{
          title: 'Mes offres',
          tabBarIcon: tabIcon('briefcase', 'briefcase-outline'),
          href: isOrganization ? undefined : null,
        }}
      />
      <Tabs.Screen
        name="candidatures"
        options={{
          title: 'Candidatures',
          tabBarIcon: tabIcon('paper-plane', 'paper-plane-outline'),
          href: isCandidate || isOrganization ? undefined : null,
        }}
      />
      <Tabs.Screen
        name="cvtheque"
        options={{
          title: 'CVthèque',
          tabBarIcon: tabIcon('people', 'people-outline'),
          href: isOrganization ? undefined : null,
        }}
      />
      <Tabs.Screen
        name="notifications"
        options={{
          title: 'Notifications',
          tabBarIcon: tabIcon('notifications', 'notifications-outline'),
          tabBarBadge: nonLues > 0 ? nonLues : undefined,
          tabBarBadgeStyle: {
            backgroundColor: colors.accent,
            color: colors.textOnAccent,
          },
        }}
      />
      <Tabs.Screen
        name="profil"
        options={{
          title: isCandidate ? 'Profil' : 'Compte',
          tabBarIcon: tabIcon('person', 'person-outline'),
        }}
      />
    </Tabs>
  );
}
