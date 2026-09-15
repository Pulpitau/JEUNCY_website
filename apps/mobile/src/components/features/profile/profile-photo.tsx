import { useMutation } from '@tanstack/react-query';
import { Alert } from 'react-native';

import { AvatarUpload } from '@/components/ui/avatar-upload';
import { useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { removeProfilePhoto, uploadProfilePhoto } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';

export interface ProfilePhotoProps {
  photoUrl: string | null;
  firstName: string;
  lastName: string;
}

// Photo de profil du candidat : AvatarUpload branche sur l'API du profil.
export function ProfilePhoto({ photoUrl, firstName, lastName }: ProfilePhotoProps) {
  const invalidate = useInvalidateProfile();

  const upload = useMutation({
    mutationFn: uploadProfilePhoto,
    onSuccess: () => void invalidate(),
    onError: (error, file) => {
      // L'erreur native (error.cause) reste dans les logs Metro : c'est elle
      // qui a permis de trouver la cause reelle le 2026-09-15 (voir
      // toFormDataPart dans lib/api/candidate-profile.ts), la ou le message
      // affiche a l'utilisateur ne disait que « connexion impossible ».
      const native = error instanceof ApiError && error.cause ? String(error.cause) : '';
      console.log(`[photo] echec upload de ${file.uri} : ${error.message} ${native}`);
      Alert.alert(
        'Photo non enregistrée',
        error instanceof ApiError ? error.message : 'Réessaie.',
      );
    },
  });

  const remove = useMutation({
    mutationFn: removeProfilePhoto,
    onSuccess: () => void invalidate(),
  });

  return (
    <AvatarUpload
      imageUrl={photoUrl}
      fallback={`${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase()}
      onUpload={(file) => upload.mutate(file)}
      onRemove={() => remove.mutate()}
      busy={upload.isPending || remove.isPending}
      shape="circle"
      labels={{
        title: 'Photo de profil',
        add: 'Ajouter une photo de profil',
        change: 'Changer la photo de profil',
      }}
    />
  );
}
