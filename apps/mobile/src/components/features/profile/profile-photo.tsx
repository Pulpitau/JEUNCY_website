import { Ionicons } from '@expo/vector-icons';
import { useMutation } from '@tanstack/react-query';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui/text';
import { useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { removeProfilePhoto, uploadProfilePhoto } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

export interface ProfilePhotoProps {
  photoUrl: string | null;
  firstName: string;
  lastName: string;
}

const SIZE = 88;

// Photo de profil : un tap propose la galerie ou l'appareil photo. Le
// serveur accepte jpeg/png/webp jusqu'a 2 Mo (UploadProfilePhotoRequest) ;
// l'image est recadree en carre et compressee avant l'envoi pour rester
// sous cette limite avec une photo de telephone moderne (souvent 4 a 8 Mo).
export function ProfilePhoto({ photoUrl, firstName, lastName }: ProfilePhotoProps) {
  const { colors } = useTheme();
  const invalidate = useInvalidateProfile();

  const upload = useMutation({
    mutationFn: uploadProfilePhoto,
    onSuccess: () => void invalidate(),
    onError: (error) =>
      Alert.alert(
        'Photo non enregistrée',
        error instanceof ApiError ? error.message : 'Réessaie.',
      ),
  });

  const remove = useMutation({
    mutationFn: removeProfilePhoto,
    onSuccess: () => void invalidate(),
  });

  const envoyer = (asset: ImagePicker.ImagePickerAsset) => {
    upload.mutate({
      uri: asset.uri,
      name: asset.fileName ?? 'photo.jpg',
      type: asset.mimeType ?? 'image/jpeg',
    });
  };

  const options: ImagePicker.ImagePickerOptions = {
    mediaTypes: ['images'],
    allowsEditing: true,
    aspect: [1, 1],
    quality: 0.8,
  };

  const depuisGalerie = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert(
        'Accès refusé',
        "Autorise l'accès aux photos dans les réglages pour en choisir une.",
      );

      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync(options);
    if (!result.canceled) envoyer(result.assets[0]);
  };

  const depuisAppareil = async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Alert.alert(
        'Accès refusé',
        "Autorise l'appareil photo dans les réglages pour prendre une photo.",
      );

      return;
    }
    const result = await ImagePicker.launchCameraAsync(options);
    if (!result.canceled) envoyer(result.assets[0]);
  };

  const choisir = () => {
    Alert.alert('Photo de profil', undefined, [
      { text: 'Choisir dans la galerie', onPress: () => void depuisGalerie() },
      { text: 'Prendre une photo', onPress: () => void depuisAppareil() },
      ...(photoUrl
        ? [
            {
              text: 'Supprimer la photo',
              style: 'destructive' as const,
              onPress: () => remove.mutate(),
            },
          ]
        : []),
      { text: 'Annuler', style: 'cancel' as const },
    ]);
  };

  const initiales = `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
  const busy = upload.isPending || remove.isPending;

  return (
    <Pressable
      onPress={choisir}
      disabled={busy}
      accessibilityRole="button"
      accessibilityLabel={
        photoUrl ? 'Changer la photo de profil' : 'Ajouter une photo de profil'
      }
      style={styles.wrapper}
    >
      {photoUrl ? (
        <Image source={{ uri: photoUrl }} style={styles.photo} contentFit="cover" />
      ) : (
        <View
          style={[
            styles.photo,
            styles.placeholder,
            { backgroundColor: colors.surfaceMuted },
          ]}
        >
          <Text variant="title" tone="muted">
            {initiales || '?'}
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
  wrapper: { width: SIZE, height: SIZE },
  photo: { width: SIZE, height: SIZE, borderRadius: SIZE / 2 },
  placeholder: { alignItems: 'center', justifyContent: 'center' },
  badge: {
    position: 'absolute',
    right: 0,
    bottom: 0,
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: -spacing.xs,
  },
});
