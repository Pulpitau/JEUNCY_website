import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { act, cleanup, renderHook } from '@testing-library/react';

import { useStagedProfileSections } from './use-staged-profile-sections';

// Les experiences et formations saisies avant l'enregistrement du profil sont
// ce qu'il y a de plus long a retaper : elles doivent survivre au demontage de
// la page au meme titre que le formulaire d'identite.
describe('useStagedProfileSections — persistance locale', () => {
  beforeEach(() => window.sessionStorage.clear());
  afterEach(() => {
    cleanup();
    window.sessionStorage.clear();
  });

  it('retrouve une experience saisie avant enregistrement', async () => {
    const premier = renderHook(() => useStagedProfileSections('7'));

    await act(async () => {
      await premier.result.current.addExperience({
        title: 'Vendeuse',
        company: 'Boulangerie du port',
        location: 'Perpignan',
        start_date: '2025-06-01',
        end_date: null,
        description: null,
      });
    });

    premier.unmount();

    const second = renderHook(() => useStagedProfileSections('7'));

    expect(second.result.current.experiences).toHaveLength(1);
    expect(second.result.current.experiences[0].title).toBe('Vendeuse');
    expect(second.result.current.hasAnything).toBe(true);
  });

  it('ne melange pas les brouillons de deux comptes', async () => {
    const lea = renderHook(() => useStagedProfileSections('7'));
    await act(async () => {
      await lea.result.current.setSkills(['Vente']);
    });
    lea.unmount();

    const malik = renderHook(() => useStagedProfileSections('8'));

    expect(malik.result.current.skills).toEqual([]);
    expect(malik.result.current.hasAnything).toBe(false);
  });

  it('oublie tout apres l’envoi au serveur', async () => {
    const vue = renderHook(() => useStagedProfileSections('7'));
    await act(async () => {
      await vue.result.current.setSoftware(['Excel']);
    });
    expect(window.sessionStorage.getItem('jeuncy.profil-brouillon.7')).not.toBeNull();

    act(() => vue.result.current.clear());

    expect(window.sessionStorage.getItem('jeuncy.profil-brouillon.7')).toBeNull();
  });
});
