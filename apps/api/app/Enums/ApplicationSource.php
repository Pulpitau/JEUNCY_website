<?php

namespace App\Enums;

// D'ou vient un dossier de candidature (applications.source) :
//  - SITE : formulaire web ;
//  - APP : application mobile (en-tete X-Jeuncy-Client: mobile) ;
//  - MATCH : dossier envoye apres un match, quel que soit le client.
enum ApplicationSource: string
{
    case SITE = 'SITE';
    case APP = 'APP';
    case MATCH = 'MATCH';
}
