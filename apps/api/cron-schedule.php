<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Point d'entree pour le cron OVH : le formulaire de planification OVH
// n'accepte ni espace ni ":" dans le champ "Commande a executer" (validation
// cote OVH), donc impossible d'y taper directement "artisan schedule:run".
// Ce script fait exactement ce que fait `artisan` en ligne de commande —
// demarre le noyau Laravel et lance schedule:run — sans passer par
// exec()/shell_exec() (souvent desactives sur l'hebergement mutualise).
// OVH n'a qu'a pointer sur ce fichier (voir CLAUDE.md section 11, "Phase 6").
require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

$input = new ArgvInput(['artisan', 'schedule:run']);
$status = $kernel->handle($input, new ConsoleOutput);
$kernel->terminate($input, $status);

exit($status);
