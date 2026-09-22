<?php

require __DIR__.'/api/auth.php';
require __DIR__.'/api/account.php';
require __DIR__.'/api/candidate-profile.php';
require __DIR__.'/api/company.php';
require __DIR__.'/api/cfa-organization.php';
require __DIR__.'/api/job-offers.php';
require __DIR__.'/api/cvtheque.php';
require __DIR__.'/api/contact.php';
require __DIR__.'/api/payments.php';
require __DIR__.'/api/subscriptions.php';
require __DIR__.'/api/applications.php';
require __DIR__.'/api/notifications.php';
require __DIR__.'/api/video-rooms.php';
require __DIR__.'/api/admin.php';
// Modele match (lot 1). Charges apres job-offers.php : discover/candidates
// se lit sur une offre, mais aucune de ces routes ne partage de prefixe avec
// les precedentes, donc l'ordre n'a pas d'effet de capture ici.
require __DIR__.'/api/discover.php';
require __DIR__.'/api/interests.php';
require __DIR__.'/api/matches.php';
require __DIR__.'/api/external-interests.php';
require __DIR__.'/api/blocks-reports.php';
