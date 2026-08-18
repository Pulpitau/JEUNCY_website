import { useState } from 'react';
import {
  addEducation,
  addExperience,
  addLanguage,
  syncSkills,
  syncSoftware,
  type Education,
  type EducationInput,
  type Experience,
  type ExperienceInput,
  type Language,
  type LanguageInput,
  type Skill,
  type Software,
} from '@/lib/api/candidate-profile';

// Saisie anticipee des sections du profil candidat.
//
// Cote serveur, une experience ou une formation s'accroche a un profil qui
// doit deja exister (CandidateProfileService::requireProfile), et un profil
// exige un nom et un prenom (colonnes NOT NULL, cf. la migration). On ne peut
// donc pas creer une ligne vide juste pour debloquer l'interface — ca
// remplirait la CVtheque de profils anonymes vendus a des recruteurs.
//
// Plutot que d'imposer une premiere etape "enregistre tes infos, le reste
// apparaitra ensuite", on garde ici les elements saisis avant l'enregistrement
// et on les envoie tous a la creation du profil. Le candidat remplit sa page
// dans l'ordre qu'il veut, en un seul passage.
//
// Les ids negatifs distinguent le provisoire du reel : ils ne peuvent entrer
// en collision avec aucun id de base, et suffisent a React comme cle de liste.
let nextTempId = -1;

function tempId(): number {
  return nextTempId--;
}

export interface StagedProfileSections {
  experiences: Experience[];
  educations: Education[];
  languages: Language[];
  skills: Skill[];
  software: Software[];
  addExperience: (values: ExperienceInput) => Promise<void>;
  removeExperience: (id: number) => Promise<void>;
  addEducation: (values: EducationInput) => Promise<void>;
  removeEducation: (id: number) => Promise<void>;
  addLanguage: (values: LanguageInput) => Promise<void>;
  removeLanguage: (id: number) => Promise<void>;
  setSkills: (names: string[]) => Promise<void>;
  setSoftware: (names: string[]) => Promise<void>;
  hasAnything: boolean;
  flush: () => Promise<void>;
  clear: () => void;
}

export function useStagedProfileSections(): StagedProfileSections {
  const [experiences, setExperiences] = useState<Experience[]>([]);
  const [educations, setEducations] = useState<Education[]>([]);
  const [languages, setLanguages] = useState<Language[]>([]);
  const [skills, setSkills] = useState<Skill[]>([]);
  const [software, setSoftware] = useState<Software[]>([]);

  // Les sections attendent des handlers asynchrones (elles affichent un etat
  // "en cours" et attendent la resolution) : on respecte leur contrat meme
  // quand l'ajout est purement local et instantane.
  async function addStagedExperience(values: ExperienceInput) {
    setExperiences((current) => [
      ...current,
      { ...values, id: tempId(), candidate_profile_id: 0 } as Experience,
    ]);
  }

  async function addStagedEducation(values: EducationInput) {
    setEducations((current) => [
      ...current,
      { ...values, id: tempId(), candidate_profile_id: 0 } as Education,
    ]);
  }

  async function addStagedLanguage(values: LanguageInput) {
    setLanguages((current) => [
      ...current,
      { ...values, id: tempId(), candidate_profile_id: 0 } as Language,
    ]);
  }

  // Envoi sequentiel et non parallele : l'ordre de saisie du candidat est
  // l'ordre d'affichage de son CV, et une rafale de requetes concurrentes le
  // rendrait aleatoire.
  // Champs recopies un a un plutot que par rest : l'id local est provisoire et
  // candidate_profile_id vaut 0, le serveur determine les deux lui-meme. Les
  // enumerer rend aussi visible, a la lecture, ce qui part vraiment.
  async function flush() {
    for (const item of experiences) {
      await addExperience({
        title: item.title,
        company: item.company,
        location: item.location,
        start_date: item.start_date,
        end_date: item.end_date,
        description: item.description,
      });
    }
    for (const item of educations) {
      await addEducation({
        degree: item.degree,
        school: item.school,
        field_of_study: item.field_of_study,
        start_date: item.start_date,
        end_date: item.end_date,
      });
    }
    for (const item of languages) {
      await addLanguage({ name: item.name, level: item.level });
    }
    if (skills.length > 0) {
      await syncSkills(skills.map((skill) => skill.name));
    }
    if (software.length > 0) {
      await syncSoftware(software.map((item) => item.name));
    }
  }

  function clear() {
    setExperiences([]);
    setEducations([]);
    setLanguages([]);
    setSkills([]);
    setSoftware([]);
  }

  return {
    experiences,
    educations,
    languages,
    skills,
    software,
    addExperience: addStagedExperience,
    removeExperience: async (id) =>
      setExperiences((current) => current.filter((item) => item.id !== id)),
    addEducation: addStagedEducation,
    removeEducation: async (id) =>
      setEducations((current) => current.filter((item) => item.id !== id)),
    addLanguage: addStagedLanguage,
    removeLanguage: async (id) =>
      setLanguages((current) => current.filter((item) => item.id !== id)),
    setSkills: async (names) => setSkills(names.map((name) => ({ id: tempId(), name }))),
    setSoftware: async (names) =>
      setSoftware(names.map((name) => ({ id: tempId(), name }))),
    hasAnything:
      experiences.length > 0 ||
      educations.length > 0 ||
      languages.length > 0 ||
      skills.length > 0 ||
      software.length > 0,
    flush,
    clear,
  };
}
