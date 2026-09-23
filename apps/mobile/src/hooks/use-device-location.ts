import { useMutation, useQueryClient } from '@tanstack/react-query';
import * as Location from 'expo-location';

import { CANDIDATE_PROFILE_KEY } from '@/hooks/use-candidate-profile';
import { DISCOVER_KEY } from '@/hooks/use-discover-deck';
import { clearLocation, updateLocation } from '@/lib/api/candidate-profile';

// « Autour de moi » : la position du telephone, pour la pile du candidat.
//
// CE QU'ELLE NE FAIT PAS. Elle ne sert jamais au deck employeur : celui-ci
// lit `candidate_profiles.latitude/longitude` (la commune declaree), jamais
// `device_latitude/device_longitude`. Ce n'est pas une regle d'usage qu'on
// pourrait oublier, c'est deux paires de colonnes distinctes — la decision
// « aucune distance cote employeur » est garantie par le schema. Le texte de
// consentement peut donc dire « les recruteurs ne la voient jamais » sans
// mentir.
//
// PRECISION BASSE ET ARRONDI. On demande `Accuracy.Low` (~1 km) : la pile
// n'a besoin que de savoir dans quelle commune on se trouve, et une position
// au metre pres serait une donnee bien plus sensible pour rien. L'app
// arrondit ensuite a deux decimales avant l'envoi, et le serveur arrondit a
// nouveau de son cote — deux barrieres plutot qu'une, parce que celle du
// client ne protege personne s'il est modifie.

/** ~1,1 km en latitude. Assez pour une commune, trop grossier pour une adresse. */
function arrondir(valeur: number): number {
  return Math.round(valeur * 100) / 100;
}

export type LocationOutcome =
  { ok: true } | { ok: false; reason: 'DENIED' | 'UNAVAILABLE'; message: string };

export function useDeviceLocation() {
  const queryClient = useQueryClient();

  const invalider = () => {
    // Le profil porte `location_source`, la pile depend de la position :
    // les deux sont perimes des que l'une des deux bouge.
    void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
    void queryClient.invalidateQueries({ queryKey: DISCOVER_KEY });
  };

  const enable = useMutation<LocationOutcome>({
    mutationFn: async () => {
      // L'invite systeme n'est demandee qu'ICI, apres l'ecran d'explication
      // de Jeuncy : iOS ne la montre qu'une fois par installation, et la
      // gacher sur un ecran qui n'a rien explique, c'est la perdre.
      const { status } = await Location.requestForegroundPermissionsAsync();

      if (status !== Location.PermissionStatus.GRANTED) {
        return {
          ok: false,
          reason: 'DENIED',
          message:
            'Sans autorisation, on continue avec la commune de ton profil. Tu peux changer d’avis dans les réglages de ton téléphone.',
        };
      }

      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Low,
      });

      await updateLocation(
        arrondir(position.coords.latitude),
        arrondir(position.coords.longitude),
      );

      return { ok: true };
    },
    onSuccess: (resultat) => {
      if (resultat.ok) invalider();
    },
  });

  const disable = useMutation({
    mutationFn: clearLocation,
    onSuccess: invalider,
  });

  return { enable, disable };
}
