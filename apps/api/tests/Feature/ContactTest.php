<?php

namespace Tests\Feature;

use App\Services\MailService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ContactTest extends TestCase
{
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Marie Dupont',
            'email' => 'marie@entreprise-test.fr',
            'organization' => 'Entreprise Test',
            'subject' => 'Découvrir la CVthèque',
            'message' => 'Bonjour, nous recrutons trois alternants cette annee.',
        ], $overrides);
    }

    private function expectMails(int $times): void
    {
        $mock = Mockery::mock(MailService::class);
        $mock->shouldReceive('sendContactMessage')->times($times);
        $this->app->instance(MailService::class, $mock);
    }

    // Les coordonnees viennent de la config, pas du frontend : changer le
    // numero ne doit pas imposer de reconstruire le bundle.
    public function test_details_endpoint_exposes_configured_contact_info(): void
    {
        Config::set('services.contact.email', 'bonjour@jeuncy.com');
        Config::set('services.contact.phone', '01 23 45 67 89');

        $this->getJson('/api/contact')
            ->assertOk()
            ->assertJsonPath('data.email', 'bonjour@jeuncy.com')
            ->assertJsonPath('data.phone', '01 23 45 67 89');
    }

    // Tant qu'aucun numero n'est fourni, l'API renvoie null et la page masque
    // le bloc — plutot qu'afficher un placeholder qui ferait perdre un appel.
    public function test_phone_is_null_when_not_configured(): void
    {
        Config::set('services.contact.phone', '');

        $this->getJson('/api/contact')->assertOk()->assertJsonPath('data.phone', null);
    }

    public function test_valid_message_is_sent(): void
    {
        $this->expectMails(1);

        $this->postJson('/api/contact', $this->valid())
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }

    public function test_invalid_email_is_refused(): void
    {
        $this->expectMails(0);

        $this->postJson('/api/contact', $this->valid(['email' => 'pas-un-email']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_too_short_message_is_refused(): void
    {
        $this->expectMails(0);

        $this->postJson('/api/contact', $this->valid(['message' => 'salut']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    // Convention du projet : une erreur de validation ressort en 400 avec le
    // code INVALID_INPUT (voir bootstrap/app.php), pas en 422 standard Laravel.
    // Champ piege : invisible pour un humain, rempli par les robots a
    // soumission automatique. S'il arrive rempli, aucun email ne part.
    public function test_honeypot_field_blocks_bots(): void
    {
        $this->expectMails(0);

        $this->postJson('/api/contact', $this->valid(['website' => 'http://spam.example']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    // Un endpoint public qui declenche un email doit etre plafonne : sans ca,
    // un robot noie bonjour@jeuncy.com et brule le quota Resend.
    public function test_rate_limit_blocks_flooding(): void
    {
        $mock = Mockery::mock(MailService::class);
        $mock->shouldReceive('sendContactMessage')->atMost()->times(5);
        $this->app->instance(MailService::class, $mock);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', $this->valid())->assertOk();
        }

        $this->postJson('/api/contact', $this->valid())->assertStatus(429);
    }
}
