<?php

// Point d'entree pour le cron OVH : le formulaire de planification OVH
// n'accepte ni espace ni ":" dans le champ "Commande a executer" (validation
// cote OVH), donc impossible d'y taper directement "artisan schedule:run".
// Ce script fait exactement ce que fait `artisan` en ligne de commande —
// demarre le noyau Laravel et lance schedule:run — sans passer par
// exec()/shell_exec() (souvent desactives sur l'hebergement mutualise).
// OVH n'a qu'a pointer sur ce fichier (voir CLAUDE.md section 11, "Phase 6").
require __DIR__.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$input = new Symfony\Component\Console\Input\ArgvInput(['artisan', 'schedule:run']);
$status = $kernel->handle($input, new Symfony\Component\Console\Output\ConsoleOutput());
$kernel->terminate($input, $status);

exit($status);
