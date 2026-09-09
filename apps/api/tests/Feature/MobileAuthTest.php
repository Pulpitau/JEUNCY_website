<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// L'application mobile ne peut pas s'appuyer sur le cookie httpOnly du web :
// un client natif range son refresh token dans le coffre du telephone
// (Keychain iOS / Keystore Android). Il se declare par l'en-tete
// X-Jeuncy-Client et recoit alors le refresh token dans le corps JSON.
//
// Ces tests gardent les deux moities de l'invariant :
//  - le NAVIGATEUR ne doit jamais recevoir de refresh token en JSON,
//  - /auth/refresh en mode mobile ne doit JAMAIS lire le cookie.
//
// La seconde est la moins evidente et la plus importante : sans elle, un
// script injecte dans le navigateur (XSS) appellerait /auth/refresh avec
// l'en-tete mobile, le navigateur joindrait automatiquement le cookie
// httpOnly, et le serveur repondrait en clair avec un refresh token de 7
// jours. La protection httpOnly du web serait annulee par une fonctionnalite
// mobile.
class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_HEADERS = ['X-Jeuncy-Client' => 'mobile'];

    private const COOKIE = 'jeuncy_refresh_token';

    private function createUser(): User
    {
        return User::create([
            'email' => 'lea@example.com',
            'password_hash' => 'Password123!',
            'role' => UserRole::CANDIDATE,
        ]);
    }

    // --- Le web ne change pas -------------------------------------------

    public function test_web_login_returns_no_refresh_token_in_the_body(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'lea@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.refreshToken');

        // Le mecanisme du navigateur reste le cookie httpOnly.
        $this->assertNotNull($response->getCookie(self::COOKIE, false));
    }

    public function test_web_register_returns_no_refresh_token_in_the_body(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'nouveau@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
        ]);

        $response->assertStatus(201)->assertJsonMissingPath('data.refreshToken');
        $this->assertNotNull($response->getCookie(self::COOKIE, false));
    }

    public function test_web_refresh_still_works_through_the_cookie(): void
    {
        $this->createUser();

        // Parcours navigateur de bout en bout : le token vient du cookie pose
        // par le login, jamais du corps.
        $login = $this->postJson('/api/auth/login', [
            'email' => 'lea@example.com',
            'password' => 'Password123!',
        ]);
        $refreshToken = $login->getCookie(self::COOKIE, false)->getValue();

        // post() et non postJson() : le harnais de test de Laravel ne joint
        // AUCUN cookie a une requete JSON. Un postJson ici passerait sans
        // cookie et ne testerait donc rien du tout — c'est exactement le piege
        // dans lequel la premiere version de ce fichier etait tombee.
        $response = $this->withUnencryptedCookie(self::COOKIE, $refreshToken)
            ->post('/api/auth/refresh', [], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.refreshToken');
        $this->assertNotEmpty($response->json('data.accessToken'));
    }

    // --- Le mobile recoit son token, sans cookie -------------------------

    public function test_mobile_login_returns_the_refresh_token_and_sets_no_cookie(): void
    {
        $this->createUser();

        $response = $this->withHeaders(self::MOBILE_HEADERS)->postJson('/api/auth/login', [
            'email' => 'lea@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.accessToken'));
        $this->assertNotEmpty($response->json('data.refreshToken'));
        // Aucun cookie : dupliquer un secret de 7 jours dans le magasin de
        // cookies du moteur reseau natif irait contre le but de la manoeuvre.
        $this->assertNull($response->getCookie(self::COOKIE, false));
    }

    public function test_mobile_register_returns_the_refresh_token_and_sets_no_cookie(): void
    {
        $response = $this->withHeaders(self::MOBILE_HEADERS)->postJson('/api/auth/register', [
            'email' => 'nouveau@example.com',
            'password' => 'Password123!',
            'role' => 'CANDIDATE',
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.refreshToken'));
        $this->assertNull($response->getCookie(self::COOKIE, false));
    }

    public function test_mobile_refresh_reads_the_body_and_rotates_the_token(): void
    {
        $this->createUser();
        $first = $this->mobileLogin();

        $response = $this->withHeaders(self::MOBILE_HEADERS)
            ->postJson('/api/auth/refresh', ['refreshToken' => $first['refreshToken']]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.accessToken'));
        $this->assertNotEmpty($response->json('data.refreshToken'));
        $this->assertNull($response->getCookie(self::COOKIE, false));
    }

    // --- La garde : le mode mobile ne lit jamais le cookie ---------------

    // Le scenario XSS exact. Un cookie parfaitement valide est present, et il
    // doit rester sans effet des lors que l'appelant se declare mobile.
    //
    // ATTENTION en modifiant ce test : il DOIT passer par post() et non
    // postJson(), car le harnais de Laravel ne joint aucun cookie a une
    // requete JSON. Avec postJson, aucun cookie n'atteint le serveur et le
    // test devient creux : il verifie qu'un cookie absent ne sert a rien.
    public function test_mobile_refresh_ignores_a_valid_cookie(): void
    {
        $this->createUser();
        $refreshToken = $this->mobileLogin()['refreshToken'];
        $this->assertNotEmpty($refreshToken);

        $response = $this->withUnencryptedCookie(self::COOKIE, $refreshToken)
            ->post('/api/auth/refresh', [], self::MOBILE_HEADERS + ['Accept' => 'application/json']);

        $response->assertStatus(400)->assertJsonPath('error.code', 'MISSING_REFRESH_TOKEN');
        // Et surtout : rien n'a fuite.
        $this->assertNull($response->json('data.refreshToken'));
        $this->assertNull($response->json('data.accessToken'));

        // Contre-epreuve, sans laquelle le test ci-dessus ne prouverait rien :
        // le MEME cookie, sur le MEME transport, fonctionne des lors qu'on ne
        // se declare plus mobile. Le refus vient donc bien de la garde, et non
        // d'un cookie qui ne serait jamais arrive jusqu'au serveur.
        $this->withUnencryptedCookie(self::COOKIE, $refreshToken)
            ->withoutHeader('X-Jeuncy-Client')
            ->post('/api/auth/refresh', [], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_mobile_refresh_refuses_an_empty_body_token(): void
    {
        $this->createUser();

        $this->withHeaders(self::MOBILE_HEADERS)
            ->postJson('/api/auth/refresh', ['refreshToken' => ''])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_REFRESH_TOKEN');
    }

    // Un refresh token invalide reste refuse en mode mobile : l'en-tete n'est
    // qu'un choix de transport, il n'affaiblit aucune verification.
    public function test_mobile_refresh_refuses_an_invalid_token(): void
    {
        $this->withHeaders(self::MOBILE_HEADERS)
            ->postJson('/api/auth/refresh', ['refreshToken' => 'pas-un-jwt'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_REFRESH_TOKEN');
    }

    // L'en-tete doit valoir exactement "mobile" : une valeur fantaisiste
    // retombe sur le comportement navigateur plutot que d'ouvrir le mode
    // mobile a tout hasard.
    public function test_an_unknown_client_header_falls_back_to_web_behaviour(): void
    {
        $this->createUser();

        $response = $this->withHeaders(['X-Jeuncy-Client' => 'autre-chose'])
            ->postJson('/api/auth/login', [
                'email' => 'lea@example.com',
                'password' => 'Password123!',
            ]);

        $response->assertOk()->assertJsonMissingPath('data.refreshToken');
        $this->assertNotNull($response->getCookie(self::COOKIE, false));
    }

    /**
     * @return array{accessToken: string, refreshToken: string}
     */
    private function mobileLogin(): array
    {
        $response = $this->withHeaders(self::MOBILE_HEADERS)->postJson('/api/auth/login', [
            'email' => 'lea@example.com',
            'password' => 'Password123!',
        ]);

        return [
            'accessToken' => $response->json('data.accessToken'),
            'refreshToken' => $response->json('data.refreshToken'),
        ];
    }
}
