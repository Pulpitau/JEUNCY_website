<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enveloppe toutes les reponses JSON reussies au format { success: true, data }
 * defini dans CONVENTIONS.md section 6. Les erreurs sont gerees separement par
 * bootstrap/app.php (withExceptions).
 */
class WrapApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            $response->setData(['success' => true, 'data' => $response->getData(true)]);
        }

        return $response;
    }
}
