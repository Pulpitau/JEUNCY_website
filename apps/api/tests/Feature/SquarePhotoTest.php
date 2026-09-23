<?php

namespace Tests\Feature;

use App\Support\SquarePhoto;
use Tests\TestCase;

/**
 * Photos de CV ecrasees, signale par des etudiants le 2026-09-23 : le gabarit
 * impose un disque de 170x170 et dompdf, qui ignore object-fit, etirait
 * l'image pour remplir la boite. La correction consiste a lui fournir une
 * image deja carree — ce que ces tests verifient sur de vraies images.
 */
class SquarePhotoTest extends TestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = sys_get_temp_dir().'/jeuncy-photos-'.uniqid();
        mkdir($this->dossier);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossier.'/*') ?: [] as $fichier) {
            @unlink($fichier);
        }
        @rmdir($this->dossier);
        parent::tearDown();
    }

    /** Fabrique une image de test avec une bande rouge en haut (repere de cadrage). */
    private function image(int $width, int $height, string $format = 'jpeg'): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 60, 200));
        imagefilledrectangle($image, 0, 0, $width, (int) ($height / 10), imagecolorallocate($image, 220, 20, 20));

        $path = $this->dossier.'/photo.'.$format;
        $format === 'png' ? imagepng($image, $path) : imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    /** @return array{0:int,1:int} */
    private function dimensions(string $bytes): array
    {
        $info = getimagesizefromstring($bytes);

        return [$info[0], $info[1]];
    }

    public function test_a_portrait_photo_comes_out_square(): void
    {
        // 600x900 : le format d'un selfie, celui qui ressortait ecrase.
        $bytes = SquarePhoto::jpegBytes($this->image(600, 900), 340);

        $this->assertNotNull($bytes);
        $this->assertSame([340, 340], $this->dimensions($bytes));
    }

    public function test_a_landscape_photo_comes_out_square(): void
    {
        $bytes = SquarePhoto::jpegBytes($this->image(1200, 500), 340);

        $this->assertSame([340, 340], $this->dimensions($bytes));
    }

    // Recadrer, ce n'est pas redimensionner : le carre doit etre pris DANS
    // l'image, sans ecraser les proportions. On le verifie sur le repere :
    // la bande rouge occupe 10 % de la hauteur d'origine ; apres un cadrage
    // correct elle occupe une part differente, jamais 10 % (ce qui signalerait
    // une simple compression verticale).
    public function test_the_image_is_cropped_not_squashed(): void
    {
        $bytes = SquarePhoto::jpegBytes($this->image(600, 900), 300);
        $result = imagecreatefromstring($bytes);

        // Le cadrage prend 600x600 a partir du haut + un tiers du surplus
        // (100px) : la bande rouge de 90px se retrouve entierement au-dessus
        // du cadre, le haut du carre doit donc etre bleu.
        $haut = imagecolorsforindex($result, imagecolorat($result, 150, 4));
        $this->assertGreaterThan($haut['red'], $haut['blue'], 'Le haut du carre devrait etre bleu, pas rouge.');
        imagedestroy($result);
    }

    public function test_a_transparent_png_does_not_turn_black(): void
    {
        $image = imagecreatetruecolor(400, 600);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $path = $this->dossier.'/transparent.png';
        imagepng($image, $path);
        imagedestroy($image);

        $result = imagecreatefromstring(SquarePhoto::jpegBytes($path, 120));
        $pixel = imagecolorsforindex($result, imagecolorat($result, 60, 60));
        imagedestroy($result);

        $this->assertSame([255, 255, 255], [$pixel['red'], $pixel['green'], $pixel['blue']]);
    }

    public function test_a_file_that_is_not_an_image_is_refused_without_crashing(): void
    {
        $path = $this->dossier.'/pas-une-image.jpeg';
        file_put_contents($path, 'ceci est du texte');

        $this->assertNull(SquarePhoto::jpegBytes($path, 340));
        $this->assertNull(SquarePhoto::jpegBytes($this->dossier.'/absente.jpg', 340));
    }
}
