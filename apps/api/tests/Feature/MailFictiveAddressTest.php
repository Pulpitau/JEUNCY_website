<?php

namespace Tests\Feature;

use App\Services\MailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Les comptes de demo (@demo-match.example.com) declenchent de vrais envois
// quand l'API locale a une cle Resend : ils doivent s'arreter avant le reseau.
class MailFictiveAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.resend.key' => 're_test']);
        Http::fake();
    }

    public function test_a_reserved_address_is_never_sent(): void
    {
        app(MailService::class)->sendPasswordResetEmail('candidat.test@demo-match.example.com', 'https://jeuncy.com/x');
        app(MailService::class)->sendPasswordResetEmail('lea@example.com', 'https://jeuncy.com/x');

        Http::assertNothingSent();
    }

    // Contre-epreuve : un domaine qui finit seulement par les memes lettres
    // reste un vrai destinataire.
    public function test_a_real_address_still_goes_out(): void
    {
        app(MailService::class)->sendPasswordResetEmail('contact@myexample.com', 'https://jeuncy.com/x');

        Http::assertSentCount(1);
    }
}
