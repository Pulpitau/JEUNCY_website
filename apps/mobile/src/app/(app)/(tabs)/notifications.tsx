import { Ionicons } from '@expo/vector-icons';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/ui/empty-state';
import { Text } from '@/components/ui/text';
import {
  hrefForNotification,
  unreadCount,
  useInvalidateNotifications,
  useNotifications,
} from '@/hooks/use-notifications';
import {
  markAllNotificationsRead,
  markNotificationRead,
  type Notification,
} from '@/lib/api/notifications';
import { formatRelativeFr } from '@/lib/relative-time';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Equivalent de la cloche du site. Un tap marque la notification lue et
// ouvre l'ecran concerne quand il existe dans l'app.
export default function NotificationsScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const insets = useSafeAreaInsets();
  const notifications = useNotifications();
  const invalidate = useInvalidateNotifications();

  const markRead = useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => void invalidate(),
  });

  const markAll = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => void invalidate(),
  });

  const ouvrir = (notification: Notification) => {
    if (!notification.read) markRead.mutate(notification.id);
    const href = hrefForNotification(notification.link);
    if (href) router.push(href);
  };

  const nonLues = unreadCount(notifications.data);

  const header = (
    <View style={styles.header}>
      <View style={styles.titleRow}>
        <Text variant="hero">Notifications</Text>
        {nonLues > 0 ? (
          <Pressable
            onPress={() => markAll.mutate()}
            accessibilityRole="button"
            hitSlop={spacing.sm}
            disabled={markAll.isPending}
          >
            <Text variant="label" tone="accent">
              Tout marquer lu
            </Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );

  const empty = notifications.isPending ? (
    <ActivityIndicator color={colors.accent} style={styles.spinner} />
  ) : notifications.isError ? (
    <EmptyState
      title="Impossible de charger tes notifications"
      description={notifications.error.message}
      action={{ label: 'Réessayer', onPress: () => void notifications.refetch() }}
    />
  ) : (
    <EmptyState
      title="Rien pour l'instant"
      description="Les offres qui te correspondent et les réponses à tes candidatures arriveront ici."
    />
  );

  return (
    <FlatList
      data={notifications.data ?? []}
      keyExtractor={(n) => String(n.id)}
      renderItem={({ item }) => (
        <Pressable
          onPress={() => ouvrir(item)}
          accessibilityRole="button"
          accessibilityLabel={`${item.read ? '' : 'Non lue. '}${item.message}`}
          style={({ pressed }) => [
            styles.row,
            {
              backgroundColor: item.read ? colors.surface : colors.surfaceMuted,
              borderColor: item.read ? colors.border : colors.accent,
              opacity: pressed ? 0.85 : 1,
            },
          ]}
        >
          <View style={styles.rowText}>
            <Text variant={item.read ? 'body' : 'bodyStrong'}>{item.message}</Text>
            <Text variant="small" tone="muted">
              {formatRelativeFr(item.created_at)}
            </Text>
          </View>
          {hrefForNotification(item.link) ? (
            <Ionicons name="chevron-forward" size={18} color={colors.textMuted} />
          ) : null}
        </Pressable>
      )}
      ListHeaderComponent={header}
      ListEmptyComponent={empty}
      contentContainerStyle={[styles.list, { paddingTop: insets.top + spacing.lg }]}
      style={{ backgroundColor: colors.background }}
      refreshControl={
        <RefreshControl
          refreshing={notifications.isRefetching}
          onRefresh={() => void notifications.refetch()}
          tintColor={colors.accent}
        />
      }
    />
  );
}

const styles = StyleSheet.create({
  list: { paddingHorizontal: spacing.xl, paddingBottom: spacing.xxl, gap: spacing.sm },
  header: { marginBottom: spacing.sm },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  spinner: { paddingVertical: spacing.xxl },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radii.md,
    borderWidth: 1,
  },
  rowText: { flex: 1, gap: 2 },
});
