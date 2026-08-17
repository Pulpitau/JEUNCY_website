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
        $_ENV['RESEND_API_KEY'] = 're_test_value';

        $response = $this->get('/deploy/secret-token/env-check');

        if ($original !== null) {
            $_ENV['STRIPE_SECRET_KEY'] = $original;
        }

        $response->assertOk();
        $response->assertJsonPath('RESEND_API_KEY', 'ok');
        $response->assertJsonPath('STRIPE_SECRET_KEY', 'MANQUANT');
        $response->assertDontSee('re_test_value');
    }
}
