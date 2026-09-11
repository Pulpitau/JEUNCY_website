import { UserRole } from '@jeuncy/shared';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { ActivityIndicator, Alert, StyleSheet, View } from 'react-native';

import { BrandHeader } from '@/components/brand-header';
import { ProfilePhoto } from '@/components/features/profile/profile-photo';
import { ThemeSwitch } from '@/components/theme-switch';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Screen } from '@/components/ui/screen';
import { ItemRow, Section, SectionEmpty } from '@/components/ui/section';
import { Text } from '@/components/ui/text';
import { useCandidateProfile, useInvalidateProfile } from '@/hooks/use-candidate-profile';
import { logout } from '@/lib/api/auth';
import {
  deleteEducation,
  deleteExperience,
  deleteLanguage,
  type CandidateProfile,
} from '@/lib/api/candidate-profile';
import { formatPeriodFr } from '@/lib/dates';
import { useAuthStore } from '@/store/auth-store';
import { useTheme } from '@/theme/theme-provider';
import { spacing } from '@/theme/typography';

export default function ProfilScreen() {
  const user = useAuthStore((state) => state.user);

  if (!user) return null;

  return user.role === UserRole.CANDIDATE ? (
    <CandidateProfileScreen />
  ) : (
    <AccountScreen email={user.email} />
  );
}

// Autres roles (entreprise, CFA, staff, admin) : pas de profil candidat, juste
// le compte. L'espace entreprise / CFA arrive en phase 3.
function AccountScreen({ email }: { email: string }) {
  return (
    <Screen>
      <BrandHeader title="Mon compte" subtitle={email} />
      <Card>
        <Text variant="sectionTitle">Espace entreprise et CFA</Text>
        <Text variant="small" tone="muted">
          La gestion des offres et des candidatures reçues arrive dans une prochaine
          version. En attendant, tout reste disponible sur jeuncy.com.
        </Text>
      </Card>
      <Footer />
    </Screen>
  );
}

function Footer() {
  return (
    <View style={styles.footer}>
      <ThemeSwitch />
      <Button label="Se déconnecter" variant="secondary" onPress={() => void logout()} />
    </View>
  );
}

function CandidateProfileScreen() {
  const router = useRouter();
  const { colors } = useTheme();
  const profile = useCandidateProfile();

  if (profile.isPending) {
    return (
      <Screen scroll={false} contentStyle={styles.centered}>
        <ActivityIndicator color={colors.accent} />
      </Screen>
    );
  }

  if (profile.isError) {
    return (
      <Screen>
        <EmptyState
          title="Impossible de charger ton profil"
          description={profile.error.message}
          action={{ label: 'Réessayer', onPress: () => void profile.refetch() }}
        />
        <Footer />
      </Screen>
    );
  }

  if (profile.data === null) {
    return (
      <Screen>
        <BrandHeader title="Ton profil" subtitle="Il te sert pour chaque candidature." />
        <Card>
          <Text variant="sectionTitle">Crée ton profil</Text>
          <Text variant="small" tone="muted">
            Ton prénom, ton nom et ta date de naissance suffisent pour commencer. Tu
            complètes le reste quand tu veux.
          </Text>
          <Button
            label="Créer mon profil"
            onPress={() => router.push('/profil/informations')}
          />
        </Card>
        <Footer />
      </Screen>
    );
  }

  return <ProfileSummary profile={profile.data} />;
}

function ProfileSummary({ profile }: { profile: CandidateProfile }) {
  const router = useRouter();
  const invalidate = useInvalidateProfile();

  // Une seule mutation generique pour les trois suppressions : meme
  // confirmation, meme rechargement, meme gestion d'erreur.
  const suppression = useMutation({
    mutationFn: (action: () => Promise<unknown>) => action(),
    onSuccess: () => void invalidate(),
    onError: () => Alert.alert('Suppression impossible', 'Réessaie dans un instant.'),
  });

  const confirmerSuppression = (quoi: string, action: () => Promise<unknown>) => {
    Alert.alert(`Supprimer ${quoi} ?`, 'Cette action est définitive.', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: () => suppression.mutate(action),
      },
    ]);
  };

  const lieu = [profile.city, profile.postal_code].filter(Boolean).join(' ');

  return (
    <Screen>
      <View style={styles.identity}>
        <ProfilePhoto
          photoUrl={profile.photo_url}
          firstName={profile.first_name}
          lastName={profile.last_name}
        />
        <View style={styles.identityText}>
          <Text variant="title">
            {profile.first_name} {profile.last_name}
          </Text>
          {profile.headline ? <Text variant="body">{profile.headline}</Text> : null}
          {lieu ? (
            <Text variant="small" tone="muted">
              {lieu}
            </Text>
          ) : null}
        </View>
      </View>
      <Button
        label="Modifier mes informations"
        variant="secondary"
        onPress={() => router.push('/profil/informations')}
      />

      {profile.bio ? (
        <Section title="À propos">
          <Text variant="body" tone="muted">
            {profile.bio}
          </Text>
        </Section>
      ) : null}

      <Section
        title="Expériences"
        action={{ label: 'Ajouter', onPress: () => router.push('/profil/experience') }}
      >
        {profile.experiences.length === 0 ? (
          <SectionEmpty>Jobs d&apos;été, stages, bénévolat : tout compte.</SectionEmpty>
        ) : (
          profile.experiences.map((exp) => (
            <ItemRow
              key={exp.id}
              title={exp.title}
              subtitle={[exp.company, exp.location].filter(Boolean).join(' · ')}
              detail={formatPeriodFr(exp.start_date, exp.end_date)}
              onPress={() =>
                router.push({
                  pathname: '/profil/experience',
                  params: { id: String(exp.id) },
                })
              }
              onDelete={() =>
                confirmerSuppression('cette expérience', () => deleteExperience(exp.id))
              }
            />
          ))
        )}
      </Section>

      <Section
        title="Formations"
        action={{ label: 'Ajouter', onPress: () => router.push('/profil/formation') }}
      >
        {profile.educations.length === 0 ? (
          <SectionEmpty>Ton diplôme en cours ou obtenu, ton établissement.</SectionEmpty>
        ) : (
          profile.educations.map((edu) => (
            <ItemRow
              key={edu.id}
              title={edu.degree}
              subtitle={[edu.school, edu.field_of_study].filter(Boolean).join(' · ')}
              detail={formatPeriodFr(edu.start_date, edu.end_date)}
              onPress={() =>
                router.push({
                  pathname: '/profil/formation',
                  params: { id: String(edu.id) },
                })
              }
              onDelete={() =>
                confirmerSuppression('cette formation', () => deleteEducation(edu.id))
              }
            />
          ))
        )}
      </Section>

      <Section
        title="Compétences"
        action={{ label: 'Modifier', onPress: () => router.push('/profil/competences') }}
      >
        {profile.skills.length === 0 ? (
          <SectionEmpty>
            Ce que tu sais faire : vente, accueil, comptabilité, soudure…
          </SectionEmpty>
        ) : (
          <View style={styles.chips}>
            {profile.skills.map((skill) => (
              <Badge key={skill.id} label={skill.name} />
            ))}
          </View>
        )}
      </Section>

      <Section
        title="Logiciels"
        action={{ label: 'Modifier', onPress: () => router.push('/profil/logiciels') }}
      >
        {profile.software.length === 0 ? (
          <SectionEmpty>Excel, Canva, Photoshop, un logiciel de caisse…</SectionEmpty>
        ) : (
          <View style={styles.chips}>
            {profile.software.map((item) => (
              <Badge key={item.id} label={item.name} />
            ))}
          </View>
        )}
      </Section>

      <Section
        title="Langues"
        action={{ label: 'Ajouter', onPress: () => router.push('/profil/langue') }}
      >
        {profile.languages.length === 0 ? (
          <SectionEmpty>Anglais, espagnol, arabe… avec ton niveau.</SectionEmpty>
        ) : (
          profile.languages.map((lang) => (
            <ItemRow
              key={lang.id}
              title={lang.name}
              subtitle={lang.level}
              onDelete={() =>
                confirmerSuppression(`« ${lang.name} »`, () => deleteLanguage(lang.id))
              }
            />
          ))
        )}
      </Section>

      <Section title="CV">
        <SectionEmpty>
          Import de ton CV, génération et téléchargement : prochaine étape.
        </SectionEmpty>
      </Section>

      <Footer />
    </Screen>
  );
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center' },
  identity: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.lg,
    marginBottom: spacing.lg,
  },
  identityText: { flex: 1, gap: spacing.xs },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  footer: { marginTop: spacing.xxl },
});
