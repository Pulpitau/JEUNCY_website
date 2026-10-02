<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La limite de poids de la photo de profil.
 *
 * POURQUOI CE FICHIER EXISTE. Le 2026-10-01, un candidat n'a pas pu deposer sa
 * photo : elle pesait plus de 2 Mo, ce que toute photo de telephone recente
 * depasse. La limite a ete portee a 12 Mo. Les tests de CandidateProfileService
 * appellent le service directement et ne traversent donc JAMAIS la Form
 * Request : aucun d'eux n'aurait vu la limite changer, ni ne verrait qu'elle
 * revient a 2 Mo. Ces tests-ci passent par la route HTTP, qui est le seul
 * chemin ou UploadProfilePhotoRequest s'execute.
 *
 * La limite serveur n'est pas une contrainte technique : l'hebergement accepte
 * 128 Mo par envoi (upload_max_filesize). C'est un choix produit, et c'est
 * pourquoi il merite d'etre tenu par un test plutot que par la memoire.
 */
class ProfilePhotoSizeLimitTest extends TestCase
{
    use RefreshDatabase;

    private function candidat(): CandidateProfile
    {
        return CandidateProfile::factory()->adult()->create();
    }

    public function test_a_photo_of_five_megabytes_is_accepted(): void
    {
        Storage::fake('public');
        $profile = $this->candidat();

        // 5 Mo : au-dessus de l'ancienne limite, sous la nouvelle. C'est le
        // poids typique d'une photo prise avec un telephone recent, donc le
        // cas exact qui etait refuse.
        $this->actingAs($profile->user, 'api')
            ->post('/api/candidate-profile/photo', [
                'photo' => UploadedFile::fake()->create('portrait.jpg', 5 * 1024, 'image/jpeg'),
            ])
            ->assertOk();

        $this->assertNotNull($profile->fresh()->photo_url);
    }

    public function test_a_photo_above_twelve_megabytes_is_still_refused(): void
    {
        Storage::fake('public');
        $profile = $this->candidat();

        // La contre-epreuve : relever la limite ne doit pas revenir a la
        // supprimer. Un fichier sans plafond remplit le disque mutualise.
        $this->actingAs($profile->user, 'api')
            ->postJson('/api/candidate-profile/photo', [
                'photo' => UploadedFile::fake()->create('enorme.jpg', 13 * 1024, 'image/jpeg'),
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertNull($profile->fresh()->photo_url);
    }

    public function test_a_pdf_renamed_as_an_image_is_refused(): void
    {
        Storage::fake('public');
        $profile = $this->candidat();

        // Le poids n'est pas le seul garde-fou : le type reste verifie.
        $this->actingAs($profile->user, 'api')
            ->postJson('/api/candidate-profile/photo', [
                'photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertNull($profile->fresh()->photo_url);
    }
}
