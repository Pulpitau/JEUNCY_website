<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception metier generique, portant un code d'erreur stable (utilise par le
 * frontend) et un statut HTTP. Voir CONVENTIONS.md section 6 pour le format
 * de reponse attendu.
 */
class ApiException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 400,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
