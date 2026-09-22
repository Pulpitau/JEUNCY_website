import { Ionicons } from '@expo/vector-icons';
import {
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
  type ReactNode,
  type Ref,
} from 'react';
import { Pressable, StyleSheet, useWindowDimensions, View } from 'react-native';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, {
  Easing,
  Extrapolation,
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
  type SharedValue,
} from 'react-native-reanimated';
import { scheduleOnRN, scheduleOnUI } from 'react-native-worklets';

import { Text } from '@/components/ui/text';
import { useTheme } from '@/theme/theme-provider';
import { radii, spacing } from '@/theme/typography';

// Pile de cartes generique : glisser a droite = « Ca m'interesse », a gauche
// = « Passer », tap = ouvrir. Les boutons ronds du bas refont les memes
// gestes pour qui ne peut pas (ou ne veut pas) glisser — VoiceOver, usage a
// une main, geste rate.
//
// Architecture : seule la carte de tete porte le geste et ses valeurs
// animees ; elle est montee avec sa propre cle, donc chaque nouvelle carte de
// tete repart de valeurs neuves. Les cartes de derriere se contentent d'un
// facteur d'echelle qui suit la progression du geste (elles « montent »
// pendant que la carte de tete s'en va). Quand le parent retire la carte
// sortie de `cards`, la suivante devient la tete : elle etait deja a
// l'echelle 1 grace a la progression, la transition est invisible.
//
// Passerelle worklet -> JS : `scheduleOnRN` de react-native-worklets, et non
// `runOnJS` de reanimated qui est marque @deprecated dans cette version
// (4.5.1, workletFunctions.d.ts). Dans l'autre sens, `scheduleOnUI` porte
// les boutons vers la fonction `flyOut` marquee 'worklet'.
//
// Valeurs partagees lues et ecrites par `get()` / `set()`, jamais par
// `.value` : avec le compilateur React (reactCompiler: true), une
// affectation `sv.value = x` est vue comme la mutation d'une prop ou d'un
// resultat de hook et refusee par react-hooks/immutability ; l'appel de
// methode, lui, passe — c'est l'API que Reanimated recommande pour ce cas.

export type SwipeDirection = 'left' | 'right';

export interface SwipeDeckProps<T> {
  /** Cartes restantes, la premiere est celle de tete. */
  cards: readonly T[];
  keyOf: (card: T) => string;
  renderCard: (card: T) => ReactNode;
  /** Appele une fois la carte sortie de l'ecran ; le parent la retire alors de `cards`. */
  onSwipe: (card: T, direction: SwipeDirection) => void;
  onOpen: (card: T) => void;
  /** Annule le dernier geste ; absent = rien a annuler, bouton grise. */
  onUndo?: () => void;
  labels?: { left: string; right: string };
}

const DEFAULT_LABELS = { left: 'PASSER', right: "ÇA M'INTÉRESSE" };

/** Nombre de cartes rendues : la tete et deux suivantes. */
const VISIBLE_CARDS = 3;
/** Fraction de la largeur au-dela de laquelle le geste est valide. */
const DISTANCE_RATIO = 0.35;
/** Vitesse (px/s) au-dela de laquelle un geste court suffit — une pichenette. */
const VELOCITY_THRESHOLD = 900;
const EXIT_DURATION_MS = 280;
/** Echelle perdue par rang dans la pile (la 2e carte est a 0.95, la 3e a 0.90). */
const SCALE_STEP = 0.05;
/** Decalage vertical par rang, pour que les cartes de derriere depassent en bas. */
const OFFSET_STEP = 10;
const SPRING = { damping: 18, stiffness: 180, mass: 0.8 };

export function SwipeDeck<T>({
  cards,
  keyOf,
  renderCard,
  onSwipe,
  onOpen,
  onUndo,
  labels = DEFAULT_LABELS,
}: SwipeDeckProps<T>) {
  const { colors } = useTheme();
  const { width: windowWidth } = useWindowDimensions();
  const [areaHeight, setAreaHeight] = useState(480);
  const frontRef = useRef<FrontCardHandle>(null);
  // Derniere carte sortie : si elle revient en tete (annulation), elle entre
  // depuis le cote par lequel elle est partie au lieu d'apparaitre d'un coup.
  const [lastSwiped, setLastSwiped] = useState<{
    key: string;
    direction: SwipeDirection;
  } | null>(null);

  // Progression du geste de tete, 0 (au repos) a 1 (seuil atteint). Partagee
  // avec les cartes de derriere pour qu'elles montent d'un rang en meme temps.
  const progress = useSharedValue(0);

  const visible = cards.slice(0, VISIBLE_CARDS);
  const front = visible[0];
  const frontKey = front === undefined ? null : keyOf(front);
  const enterFrom =
    lastSwiped && lastSwiped.key === frontKey ? lastSwiped.direction : null;

  const handleSwipe = (card: T, direction: SwipeDirection) => {
    setLastSwiped({ key: keyOf(card), direction });
    onSwipe(card, direction);
  };

  return (
    <View style={styles.deck}>
      <View
        style={styles.area}
        onLayout={(event) => setAreaHeight(event.nativeEvent.layout.height)}
      >
        {/* Rendu du fond vers la tete : la derniere carte du tableau est
            au-dessus des autres. */}
        {visible
          .map((card, index) => ({ card, index, key: keyOf(card) }))
          .reverse()
          .map(({ card, index, key }) =>
            index === 0 ? (
              <FrontCard
                key={key}
                ref={frontRef}
                width={windowWidth}
                progress={progress}
                enterFrom={enterFrom}
                labels={labels}
                onSwipe={(direction) => handleSwipe(card, direction)}
                onOpen={() => onOpen(card)}
              >
                {renderCard(card)}
              </FrontCard>
            ) : (
              <BackCard
                key={key}
                index={index}
                progress={progress}
                areaHeight={areaHeight}
              >
                {renderCard(card)}
              </BackCard>
            ),
          )}
      </View>

      <View style={styles.actions}>
        <RoundButton
          icon="close"
          label="Passer"
          size={60}
          color={colors.textMuted}
          background={colors.surface}
          border={colors.border}
          disabled={front === undefined}
          onPress={() => frontRef.current?.fly('left')}
        />
        <RoundButton
          icon="arrow-undo"
          label="Annuler le dernier geste"
          size={46}
          color={colors.accentWarm}
          background={colors.surface}
          border={colors.border}
          disabled={onUndo === undefined}
          onPress={() => onUndo?.()}
        />
        <RoundButton
          icon="heart"
          label="Ça m'intéresse"
          size={60}
          color={colors.textOnAccent}
          background={colors.accent}
          border={colors.accent}
          disabled={front === undefined}
          onPress={() => frontRef.current?.fly('right')}
        />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Carte de tete : geste, rotation, tampons, sortie animee.
// ---------------------------------------------------------------------------

interface FrontCardHandle {
  /** Sortie animee declenchee par un bouton, identique a celle du geste. */
  fly: (direction: SwipeDirection) => void;
}

interface FrontCardProps {
  ref: Ref<FrontCardHandle>;
  width: number;
  progress: SharedValue<number>;
  /** Cote d'entree (carte remise dans la pile) ; null = apparait en place. */
  enterFrom: SwipeDirection | null;
  labels: { left: string; right: string };
  onSwipe: (direction: SwipeDirection) => void;
  onOpen: () => void;
  children: ReactNode;
}

function FrontCard({
  ref,
  width,
  progress,
  enterFrom,
  labels,
  onSwipe,
  onOpen,
  children,
}: FrontCardProps) {
  const { colors } = useTheme();
  const threshold = width * DISTANCE_RATIO;
  const exitDistance = width * 1.5;

  // Fige au montage : `enterFrom` peut changer pendant que cette carte est
  // encore montee (le parent note la carte sortie avant de la retirer), et
  // il ne faut surtout pas qu'une carte en train de partir revienne.
  const [initialEnterFrom] = useState(enterFrom);

  const translateX = useSharedValue(
    initialEnterFrom === 'left'
      ? -exitDistance
      : initialEnterFrom === 'right'
        ? exitDistance
        : 0,
  );
  const translateY = useSharedValue(0);

  // Au montage d'une nouvelle carte de tete, la progression revient a 0 : la
  // carte precedente l'avait laissee a 1 en sortant, et les cartes de derriere
  // s'y rapportent. Animee plutot que posee, pour que la pile se tasse en
  // douceur d'un rang. Carte annulee : elle revient en plus depuis le cote
  // par lequel elle etait sortie. Dependances stables, l'effet ne tourne
  // qu'au montage.
  useEffect(() => {
    if (initialEnterFrom) translateX.set(withSpring(0, SPRING));
    progress.set(withSpring(0, SPRING));
  }, [initialEnterFrom, translateX, progress]);

  const settle = () => {
    'worklet';
    translateX.set(withSpring(0, SPRING));
    translateY.set(withSpring(0, SPRING));
    progress.set(withSpring(0, SPRING));
  };

  const flyOut = (direction: SwipeDirection) => {
    'worklet';
    const sign = direction === 'right' ? 1 : -1;
    progress.set(withTiming(1, { duration: EXIT_DURATION_MS * 0.7 }));
    translateX.set(
      withTiming(
        sign * exitDistance,
        { duration: EXIT_DURATION_MS, easing: Easing.out(Easing.quad) },
        (finished) => {
          // Le parent retire la carte : tout ce qui touche a React se fait
          // sur le thread JS, d'ou scheduleOnRN.
          if (finished) scheduleOnRN(onSwipe, direction);
        },
      ),
    );
  };

  useImperativeHandle(ref, () => ({
    fly: (direction) => scheduleOnUI(flyOut, direction),
  }));

  const pan = Gesture.Pan()
    // Un doigt qui bouge de moins de 8 px n'est pas un glissement : ca
    // laisse le tap gagner, et evite qu'un effleurement deplace la carte.
    .activeOffsetX([-8, 8])
    .onUpdate((event) => {
      translateX.set(event.translationX);
      translateY.set(event.translationY);
      progress.set(Math.min(1, Math.abs(event.translationX) / threshold));
    })
    .onEnd((event, success) => {
      // Geste interrompu par le systeme (appel entrant, bandeau de
      // notification, geste parent qui reprend la main) : onEnd arrive avec
      // success=false et la derniere translation, parfois au-dela du seuil.
      // Ce n'est pas une decision du candidat, la carte revient en place.
      if (!success) {
        settle();

        return;
      }
      // Seuil de distance OU de vitesse : une pichenette rapide vaut un
      // long glissement. Sinon, retour elastique en place.
      const farEnough = Math.abs(event.translationX) > threshold;
      const fastEnough = Math.abs(event.velocityX) > VELOCITY_THRESHOLD;

      if (farEnough) {
        flyOut(event.translationX > 0 ? 'right' : 'left');
      } else if (fastEnough) {
        flyOut(event.velocityX > 0 ? 'right' : 'left');
      } else {
        settle();
      }
    });

  const tap = Gesture.Tap().onEnd((_event, success) => {
    if (success) scheduleOnRN(onOpen);
  });

  const gesture = Gesture.Race(pan, tap);

  const cardStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: translateX.get() },
      { translateY: translateY.get() },
      {
        rotate: `${interpolate(translateX.get(), [-width, 0, width], [-12, 0, 12])}deg`,
      },
    ],
  }));

  // Les tampons apparaissent progressivement : pleinement lisibles au seuil.
  const rightStampStyle = useAnimatedStyle(() => ({
    opacity: interpolate(translateX.get(), [0, threshold], [0, 1], Extrapolation.CLAMP),
  }));
  const leftStampStyle = useAnimatedStyle(() => ({
    opacity: interpolate(translateX.get(), [-threshold, 0], [1, 0], Extrapolation.CLAMP),
  }));

  return (
    <GestureDetector gesture={gesture}>
      <Animated.View
        style={[styles.card, cardStyle]}
        accessible
        accessibilityRole="button"
        accessibilityHint="Ouvre la fiche. Glisse à droite si ça t'intéresse, à gauche pour passer."
        accessibilityActions={[{ name: 'activate', label: 'Ouvrir la fiche' }]}
        onAccessibilityAction={(event) => {
          if (event.nativeEvent.actionName === 'activate') onOpen();
        }}
      >
        {children}
        {/* Tampons : invisibles au repos (opacite 0), mais VoiceOver lirait
            quand meme leur texte a la suite de la carte, d'ou le masquage. */}
        <Animated.View
          pointerEvents="none"
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
          style={[
            styles.stamp,
            styles.stampRight,
            { borderColor: colors.accent },
            rightStampStyle,
          ]}
        >
          <Text variant="button" style={[styles.stampText, { color: colors.accent }]}>
            {labels.right}
          </Text>
        </Animated.View>
        <Animated.View
          pointerEvents="none"
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
          style={[
            styles.stamp,
            styles.stampLeft,
            { borderColor: colors.textMuted },
            leftStampStyle,
          ]}
        >
          <Text variant="button" style={[styles.stampText, { color: colors.textMuted }]}>
            {labels.left}
          </Text>
        </Animated.View>
      </Animated.View>
    </GestureDetector>
  );
}

// ---------------------------------------------------------------------------
// Cartes de derriere : reduites, decalees vers le bas, elles montent d'un
// rang a mesure que la carte de tete s'eloigne.
// ---------------------------------------------------------------------------

interface BackCardProps {
  /** Rang dans la pile : 1 = juste derriere la tete. */
  index: number;
  progress: SharedValue<number>;
  areaHeight: number;
  children: ReactNode;
}

function BackCard({ index, progress, areaHeight, children }: BackCardProps) {
  const style = useAnimatedStyle(() => {
    const rank = index - progress.get();
    const scale = 1 - SCALE_STEP * rank;
    // La reduction se fait autour du centre : on redescend la carte de la
    // moitie de la hauteur perdue, plus un decalage fixe, pour qu'elle
    // depasse sous la carte de tete.
    const translateY = rank * OFFSET_STEP + ((1 - scale) * areaHeight) / 2;

    return { transform: [{ translateY }, { scale }] };
  });

  // Ni touches ni lecteur d'ecran : `accessible={false}` ne masque pas les
  // textes enfants a VoiceOver / TalkBack, qui liraient les cartes de
  // derriere comme si elles etaient en tete. D'ou les deux proprietes
  // supplementaires (iOS, puis Android).
  return (
    <Animated.View
      style={[styles.card, style]}
      pointerEvents="none"
      accessible={false}
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    >
      {children}
    </Animated.View>
  );
}

// ---------------------------------------------------------------------------
// Boutons ronds
// ---------------------------------------------------------------------------

interface RoundButtonProps {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  size: number;
  color: string;
  background: string;
  border: string;
  disabled: boolean;
  onPress: () => void;
}

function RoundButton({
  icon,
  label,
  size,
  color,
  background,
  border,
  disabled,
  onPress,
}: RoundButtonProps) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled }}
      hitSlop={spacing.sm}
      style={({ pressed }) => [
        styles.round,
        {
          width: size,
          height: size,
          borderRadius: size / 2,
          backgroundColor: background,
          borderColor: border,
          opacity: disabled ? 0.35 : pressed ? 0.7 : 1,
        },
      ]}
    >
      <Ionicons name={icon} size={size * 0.48} color={color} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  deck: { flex: 1 },
  area: { flex: 1 },
  card: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    borderRadius: radii.lg,
  },
  stamp: {
    position: 'absolute',
    top: spacing.xl,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
    borderWidth: 3,
    borderRadius: radii.sm,
  },
  stampRight: {
    left: spacing.lg,
    transform: [{ rotate: '-14deg' }],
  },
  stampLeft: {
    right: spacing.lg,
    transform: [{ rotate: '14deg' }],
  },
  stampText: {
    letterSpacing: 1,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.xl,
    paddingTop: spacing.lg,
  },
  round: {
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
});
