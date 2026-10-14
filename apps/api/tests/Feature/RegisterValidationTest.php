<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Le domaine d'anonymisation est reserve aux comptes supprimes
// (AccountService::deleteAccount). Rien ne l'interdisait a l'inscription :
// RegisterRequest ne validait que le format RFC, et .invalid n'est reserve
// que pour la resolution DNS — ce qui n'empeche aucun formulaire de
// l'accepter.
//
// Deux consequences, toutes deux constatees : un compte reel a cette adresse
// devenait invisible de la moderation tant que l'etat de suppression etait
// deduit de l'email, et l'adresse d'anonymisation etant previsible, la
// pre-enregistrer bloquait la suppression RGPD d'un compte precis.
//
// L'etat vit desormais dans deleted_account_at, mais cette garde reste : une
// adresse @jeuncy.invalid n'a aucune raison legitime d'exister a l'inscription.
class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_refuses_the_reserved_deletion_domain(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'compte-supprime-42'.User::DELETED_EMAIL_DOMAIN,
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
        ]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->assertSame(0, User::count());
    }

    // La garde vise le domaine, pas une adresse precise : un prefixe
    // quelconque doit etre refuse aussi.
    public function test_registration_refuses_any_address_on_that_domain(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'spam'.User::DELETED_EMAIL_DOMAIN,
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
        ])->assertStatus(400);

        $this->assertSame(0, User::count());
    }

    // Contrepartie : une adresse normale doit continuer de passer. Une garde
    // trop large casserait toutes les inscriptions.
    public function test_registration_still_accepts_a_normal_address(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'lea.girard@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
            'age_confirmed' => true,
        ])->assertCreated();

        $this->assertSame(1, User::count());
    }

    // --- Age minimum (16 ans) ---
    //
    // La case « j'ai 16 ans ou plus » est facultative cote serveur : le client
    // mobile ne l'envoie pas encore, et l'exiger casserait son inscription. Si
    // elle est envoyee, elle doit etre vraie. La verification reelle est la
    // date de naissance, imposee >= 16 ans a la creation du profil.

    public function test_a_declared_age_below_16_is_refused(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'lea.girard@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
            'age_confirmed' => false,
        ])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertSame(0, User::count());
    }

    public function test_a_confirmed_age_is_accepted(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'lea.girard@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
            'age_confirmed' => true,
        ])->assertCreated();
    }

    // Le web et le mobile envoient desormais tous les deux la case (audit de
    // securite du 2026-10-01, section B3) : son absence pour un CANDIDATE
    // doit maintenant bloquer, pour que la preuve de consentement ne
    // depende pas d'un appel direct a l'API qui s'en dispenserait.
    public function test_registration_without_the_age_field_is_refused_for_a_candidate(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'lea.girard@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
        ])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertSame(0, User::count());
    }

    // Sans objet pour une entreprise ou un CFA : omettre la case ne doit pas
    // bloquer leur inscription (voir aussi InscriptionCfaFermeeTest).
    public function test_registration_without_the_age_field_still_works_for_a_company(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'rh@nexatech.example.com',
            'password' => 'Password123!',
            'role' => 'COMPANY',
        ])->assertCreated();
    }
}
