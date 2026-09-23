import { ApiError } from '@/lib/api/client';

// Saisie des competences et des logiciels.
//
// Ce que les etudiants ont vecu (retours du 2026-09-23) : le champ affiche
// « Ex : React, Vente, Relation client… », donc ils tapent leurs competences
// SEPAREES PAR DES VIRGULES, en une fois. L'ancien code en faisait une seule
// competence — « Communication, Travail en equipe, Organisation, Gestion du
// stress » — que le serveur refusait (50 caracteres maximum), et l'interface
// n'affichait rien du tout. Pour eux, l'onglet « n'enregistrait plus ».
//
// Deux corrections, donc : decouper ce que le placeholder promet, et ne plus
// jamais avaler une erreur en silence (voir messageFromError).

// Doit rester aligne sur SyncSkillsRequest / SyncSoftwareRequest cote API
// ('names.*' => max:50).
export const TAG_MAX_LENGTH = 50;

/**
 * Decoupe une saisie libre en etiquettes : virgules et points-virgules,
 * espaces superflus retires, vides ignores, doublons internes ecartes.
 */
export function splitTags(input: string): string[] {
  const seen = new Set<string>();

  return input
    .split(/[,;]/)
    .map((part) => part.trim().replace(/\s+/g, ' '))
    .filter((part) => {
      if (part === '') {
        return false;
      }

      const key = part.toLowerCase();
      if (seen.has(key)) {
        return false;
      }
      seen.add(key);

      return true;
    });
}

/** Etiquette trop longue pour le serveur, ou null si tout passe. */
export function tooLongTag(tags: string[]): string | null {
  return tags.find((tag) => tag.length > TAG_MAX_LENGTH) ?? null;
}

/**
 * Message affichable pour une erreur d'enregistrement. Le message du serveur
 * quand il y en a un (il est ecrit pour l'utilisateur), sinon une phrase
 * neutre — jamais un silence.
 */
export function messageFromError(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  return "Impossible d'enregistrer pour le moment. Réessaie dans un instant.";
}
