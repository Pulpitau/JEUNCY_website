<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeployEnvCheckTest extends TestCase
{
    public function test_env_check_requires_valid_deploy_token(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $this->get('/deploy/wrong-token/env-check')->assertNotFound();
    }

    public function test_env_check_is_inert_when_no_deploy_token_configured(): void
    {
        Config::set('app.deploy_token', null);

        $this->get('/deploy/anything/env-check')->assertNotFound();
    }

    public function test_env_check_reports_missing_and_present_keys_without_leaking_values(): void
    {
        Config::set('app.deploy_token', 'secret-token');

        $original = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        unset($_ENV['STRIPE_SECRET_KEY'], $_SERVER['STRIPE_SECRET_KEY']);
        putenv('STRIPE_SECRET_KEY');
        // $_ENV survit a la fin du test : sans la restauration ci-dessous,
        // tous les tests suivants du processus lisaient une cle Resend non
        // vide et MailService partait joindre api.resend.com pour de vrai,
        // annulant le garde-fou de phpunit.xml (RESEND_API_KEY vide).
        // Decouvert le 2026-09-22 en ajoutant Http::preventStrayRequests().
        $resendOriginal = $_ENV['RESEND_API_KEY'] ?? null;
        $_ENV['RESEND_API_KEY'] = 're_test_value';

        $response = $this->get('/deploy/secret-token/env-check');

        if ($original !== null) {
            $_ENV['STRIPE_SECRET_KEY'] = $original;
        }

        if ($resendOriginal !== null) {
            $_ENV['RESEND_API_KEY'] = $resendOriginal;
        } else {
            unset($_ENV['RESEND_API_KEY']);
        }

        $response->assertOk();
        $response->assertJsonPath('RESEND_API_KEY', 'ok');
        $response->assertJsonPath('STRIPE_SECRET_KEY', 'MANQUANT');
        $response->assertDontSee('re_test_value');
    }
}
