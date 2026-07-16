import { PrismaClient } from '../apps/api/generated/prisma';

const prisma = new PrismaClient();

async function main() {
  // Données de démo ajoutées à l'étape 3, une fois le modèle de données défini.
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
