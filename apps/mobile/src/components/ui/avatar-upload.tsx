import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import type { NativeFile } from '@/lib/api/candidate-profile';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

import { Text } from './text';

export interface AvatarUploadProps {
  imageUrl: string | null;
  /** Initiales affichees sans image. */
  fallback: string;
  onUpload: (file: NativeFile) => void;
  onRemove: () => void;
  busy?: boolean;
  /** Rond pour une personne, coins arrondis pour un logo. */
  shape?: 'circle' | 'rounded';
  size?: number;
  labels?: { title: string; add: string; change: string };
}

const DEFAULT_LABELS = {
  title: 'Image',
  add: 'Ajouter une image',
  change: "Changer l'image",
};

// Image tactile : un tap propose la galerie, l'appareil photo, la suppression.
// Sert a la photo du candidat et au logo de l'organisation. Le serveur accepte
// jpeg/png/webp jusqu'a 2 Mo ; l'image est recadree en carre et compressee
// avant l'envoi pour rester sous cette limite avec une photo de telephone.
export function AvatarUpload({
  imageUrl,
  fallback,
  onUpload,
  onRemove,
  busy = false,
  shape = 'circle',
  size = 88,
  labels = DEFAULT_LABELS,
}: AvatarUploadProps) {
  const { colors } = useTheme();
  const radius = shape === 'circle' ? size / 2 : radii.lg;

  const envoyer = (asset: ImagePicker.ImagePickerAsset) =>
    onUpload({
      uri: asset.uri,
      name: asset.fileName ?? 'image.jpg',
      type: asset.mimeType ?? 'image/jpeg',
    });

  const options: ImagePicker.ImagePickerOptions = {
    mediaTypes: ['images'],
    allowsEditing: true,
    aspect: [1, 1],
    quality: 0.8,
  };

  const depuisGalerie = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Accès refusé', "Autorise l'accès aux photos dans les réglages.");

      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync(options);
    if (!result.canceled) envoyer(result.assets[0]);
  };

  const depuisAppareil = async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Accès refusé', "Autorise l'appareil photo dans les réglages.");

      return;
    }
    const result = await ImagePicker.launchCameraAsync(options);
    if (!result.canceled) envoyer(result.assets[0]);
  };

  const choisir = () => {
    Alert.alert(labels.title, undefined, [
      { text: 'Choisir dans la galerie', onPress: () => void depuisGalerie() },
      { text: 'Prendre une photo', onPress: () => void depuisAppareil() },
      ...(imageUrl
        ? [{ text: 'Supprimer', style: 'destructive' as const, onPress: onRemove }]
        : []),
      { text: 'Annuler', style: 'cancel' as const },
    ]);
  };

  return (
    <Pressable
      onPress={choisir}
      disabled={busy}
      accessibilityRole="button"
      accessibilityLabel={imageUrl ? labels.change : labels.add}
      style={{ width: size, height: size }}
    >
      {imageUrl ? (
        <Image
          source={{ uri: imageUrl }}
          style={{ width: size, height: size, borderRadius: radius }}
          contentFit={shape === 'circle' ? 'cover' : 'contain'}
        />
      ) : (
        <View
          style={[
            styles.placeholder,
            {
              width: size,
              height: size,
              borderRadius: radius,
              backgroundColor: colors.surfaceMuted,
            },
          ]}
        >
          <Text variant="title" tone="muted">
            {fallback || '?'}
          </Text>
        </View>
      )}
      <View
        style={[
          styles.badge,
          { backgroundColor: colors.accent, borderColor: colors.background },
        ]}
      >
        {busy ? (
          <ActivityIndicator size="small" color={colors.textOnAccent} />
        ) : (
          <Ionicons name="camera" size={14} color={colors.textOnAccent} />
        )}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  placeholder: { alignItems: 'center', justifyContent: 'center' },
  badge: {
    position: 'absolute',
    right: -spacing.xs,
    bottom: 0,
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
