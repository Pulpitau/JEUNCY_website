import { PrismaClient } from '../apps/api/generated/prisma';
import * as bcrypt from 'bcrypt';

const prisma = new PrismaClient();

// Mot de passe de demo commun a tous les comptes seedes (dev uniquement).
const DEMO_PASSWORD = 'Password123!';

async function main() {
  const passwordHash = await bcrypt.hash(DEMO_PASSWORD, 10);

  // ---------------------------------------------------------------------
  // Candidats
  // ---------------------------------------------------------------------

  const [reactSkill, nodeSkill, salesSkill, serviceSkill] = await Promise.all([
    prisma.skill.upsert({
      where: { name: 'React' },
      update: {},
      create: { name: 'React' },
    }),
    prisma.skill.upsert({
      where: { name: 'Node.js' },
      update: {},
      create: { name: 'Node.js' },
    }),
    prisma.skill.upsert({
      where: { name: 'Vente' },
      update: {},
      create: { name: 'Vente' },
    }),
    prisma.skill.upsert({
      where: { name: 'Relation client' },
      update: {},
      create: { name: 'Relation client' },
    }),
  ]);

  const lea = await prisma.user.create({
    data: {
      email: 'lea.girard@example.com',
      passwordHash,
      role: 'CANDIDATE',
      candidateProfile: {
        create: {
          firstName: 'Léa',
          lastName: 'Girard',
          phone: '0612345678',
          city: 'Nantes',
          postalCode: '44000',
          bio: "Étudiante en BTS NDRC, à la recherche d'une alternance en développement commercial.",
          educations: {
            create: [
              {
                degree: 'BTS Négociation et Digitalisation de la Relation Client',
                school: 'Lycée Livet',
                startDate: new Date('2024-09-01'),
              },
            ],
          },
          experiences: {
            create: [
              {
                title: 'Vendeuse saisonnière',
                company: 'Decathlon Nantes',
                location: 'Nantes',
                startDate: new Date('2023-06-01'),
                endDate: new Date('2023-08-31'),
                description: 'Accueil client, encaissement, mise en rayon.',
              },
            ],
          },
          skills: {
            create: [{ skillId: salesSkill.id }, { skillId: serviceSkill.id }],
          },
        },
      },
    },
    include: { candidateProfile: true },
  });

  const malik = await prisma.user.create({
    data: {
      email: 'malik.benali@example.com',
      passwordHash,
      role: 'CANDIDATE',
      candidateProfile: {
        create: {
          firstName: 'Malik',
          lastName: 'Benali',
          phone: '0698765432',
          city: 'Rennes',
          postalCode: '35000',
          bio: 'Développeur en formation, en alternance dans le développement web.',
          educations: {
            create: [
              {
                degree: 'Bachelor Développeur Web Full-Stack',
                school: 'CFA Sup Alternance',
                startDate: new Date('2024-09-01'),
              },
            ],
          },
          skills: {
            create: [{ skillId: reactSkill.id }, { skillId: nodeSkill.id }],
          },
        },
      },
    },
    include: { candidateProfile: true },
  });

  // ---------------------------------------------------------------------
  // Entreprises
  // ---------------------------------------------------------------------

  const nexatech = await prisma.user.create({
    data: {
      email: 'rh@nexatech.example.com',
      passwordHash,
      role: 'COMPANY',
      company: {
        create: {
          name: 'NexaTech',
          siret: '12345678900011',
          description:
            'Agence de développement web basée à Rennes, spécialisée dans les applications métier.',
          website: 'https://nexatech.example.com',
          city: 'Rennes',
          postalCode: '35000',
        },
      },
    },
    include: { company: true },
  });

  const cafeDesLices = await prisma.user.create({
    data: {
      email: 'contact@cafedeslices.example.com',
      passwordHash,
      role: 'COMPANY',
      company: {
        create: {
          name: 'Café des Lices',
          siret: '98765432100022',
          description: 'Café-restaurant au coeur de Rennes, terrasse et cuisine maison.',
          city: 'Rennes',
          postalCode: '35000',
        },
      },
    },
    include: { company: true },
  });

  // ---------------------------------------------------------------------
  // CFA
  // ---------------------------------------------------------------------

  const cfaSupAlternance = await prisma.user.create({
    data: {
      email: 'contact@cfa-sup-alternance.example.com',
      passwordHash,
      role: 'CFA',
      cfaOrganization: {
        create: {
          name: 'CFA Sup Alternance',
          description:
            'Centre de formation dédié aux métiers du numérique et du commerce.',
          website: 'https://cfa-sup-alternance.example.com',
          city: 'Rennes',
          postalCode: '35000',
        },
      },
    },
    include: { cfaOrganization: true },
  });

  // ---------------------------------------------------------------------
  // Offres
  // ---------------------------------------------------------------------

  const offreDev = await prisma.jobOffer.create({
    data: {
      companyId: nexatech.company!.id,
      title: 'Développeur web full-stack en alternance',
      description:
        'Rejoins notre équipe pour développer des applications React/NestJS pour nos clients. Tutorat assuré par un développeur senior.',
      contractType: 'ALTERNANCE',
      status: 'PUBLISHED',
      paymentStatus: 'SUCCEEDED',
      city: 'Rennes',
      publishedAt: new Date(),
    },
  });

  const offreServeur = await prisma.jobOffer.create({
    data: {
      companyId: cafeDesLices.company!.id,
      title: 'Serveur / serveuse saisonnier(ère)',
      description:
        'Service en salle et en terrasse pour la saison estivale. Équipe jeune et dynamique.',
      contractType: 'SAISONNIER',
      status: 'PUBLISHED',
      paymentStatus: 'SUCCEEDED',
      city: 'Rennes',
      publishedAt: new Date(),
    },
  });

  await prisma.jobOffer.create({
    data: {
      cfaOrganizationId: cfaSupAlternance.cfaOrganization!.id,
      title: 'Bénévole journée portes ouvertes',
      description:
        'Accueil des visiteurs et présentation des filières lors de notre journée portes ouvertes.',
      contractType: 'BENEVOLAT',
      status: 'DRAFT',
      paymentStatus: 'PENDING',
      city: 'Rennes',
    },
  });

  // ---------------------------------------------------------------------
  // Candidatures
  // ---------------------------------------------------------------------

  await prisma.application.create({
    data: {
      candidateProfileId: malik.candidateProfile!.id,
      jobOfferId: offreDev.id,
      status: 'INTERVIEW',
      coverLetter:
        'Motivé pour rejoindre NexaTech et progresser en développement full-stack.',
    },
  });

  await prisma.application.create({
    data: {
      candidateProfileId: lea.candidateProfile!.id,
      jobOfferId: offreServeur.id,
      status: 'SENT',
    },
  });

  // ---------------------------------------------------------------------
  // Paiement (publication de l'offre NexaTech)
  // ---------------------------------------------------------------------

  await prisma.payment.create({
    data: {
      userId: nexatech.id,
      jobOfferId: offreDev.id,
      amountCents: 4900,
      status: 'SUCCEEDED',
      stripePaymentIntentId: 'pi_demo_seed_001',
    },
  });

  // ---------------------------------------------------------------------
  // Notification
  // ---------------------------------------------------------------------

  await prisma.notification.create({
    data: {
      userId: nexatech.id,
      type: 'NEW_APPLICATION',
      message:
        'Malik Benali a postulé à votre offre "Développeur web full-stack en alternance".',
      link: `/applications/${offreDev.id}`,
    },
  });

  // ---------------------------------------------------------------------
  // Salle de visio (démo commerciale)
  // ---------------------------------------------------------------------

  await prisma.videoRoom.create({
    data: {
      hostId: nexatech.id,
      participantId: malik.id,
      jitsiRoomName: crypto.randomUUID(),
      status: 'SCHEDULED',
      scheduledAt: new Date(Date.now() + 1000 * 60 * 60 * 24),
    },
  });

  console.log('Seed terminé.');
  console.log(`Comptes de démo (mot de passe commun : ${DEMO_PASSWORD}) :`);
  console.log('- lea.girard@example.com (candidate)');
  console.log('- malik.benali@example.com (candidate)');
  console.log('- rh@nexatech.example.com (company)');
  console.log('- contact@cafedeslices.example.com (company)');
  console.log('- contact@cfa-sup-alternance.example.com (cfa)');
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
