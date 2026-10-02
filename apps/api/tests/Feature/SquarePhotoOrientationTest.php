<?php

namespace Tests\Feature;

use App\Support\SquarePhoto;
use PHPUnit\Framework\TestCase;

/**
 * Le redressement des photos de telephone dans le CV genere.
 *
 * POURQUOI CE FICHIER EXISTE. Le 2026-10-01, le CV d'un etudiant est sorti
 * avec sa photo couchee sur le cote. Un telephone n'ecrit pas l'image dans le
 * sens ou on la voit : il l'enregistre dans le sens du capteur et ajoute une
 * etiquette EXIF « a afficher tournee ». Les navigateurs la respectent, GD —
 * qui fabrique l'image du PDF — l'ignore. D'ou une photo droite sur le site et
 * couchee dans le CV, ce qui ne se voit qu'une fois le document sous les yeux
 * d'un recruteur.
 *
 * Le test ne se contente pas de verifier la table de correspondance : il
 * fabrique un vrai JPEG avec l'etiquette dedans et regarde ou sont passees les
 * couleurs. Sans le correctif, les deux assertions de couleur echouent.
 */
class SquarePhotoOrientationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD absent.');
        }
    }

    public function test_the_rotation_table_matches_the_exif_standard(): void
    {
        // imagerotate() compte en sens ANTI-horaire. EXIF 6 veut dire « tourner
        // de 90 degres dans le sens horaire », d'ou le signe negatif. C'est
        // exactement l'erreur qui produit une photo couchee du mauvais cote.
        $this->assertSame(0, SquarePhoto::degresPour(1));
        $this->assertSame(180, SquarePhoto::degresPour(3));
        $this->assertSame(-90, SquarePhoto::degresPour(6));
        $this->assertSame(90, SquarePhoto::degresPour(8));

        $this->assertFalse(SquarePhoto::estMiroir(1));
        $this->assertFalse(SquarePhoto::estMiroir(6));
        $this->assertTrue(SquarePhoto::estMiroir(2));
        $this->assertTrue(SquarePhoto::estMiroir(7));
    }

    public function test_a_photo_without_the_tag_is_left_alone(): void
    {
        $chemin = $this->jpegMoitieHautRouge(null);

        [$gauche, $droite, $haut, $bas] = $this->zones($chemin);

        // Sans etiquette, le haut reste rouge : on ne redresse pas au hasard.
        $this->assertTrue($haut['r'] > $haut['b'] + 40, 'Le haut devrait rester rouge.');
        $this->assertTrue($bas['b'] > $bas['r'] + 40, 'Le bas devrait rester bleu.');
        unset($gauche, $droite);

        @unlink($chemin);
    }

    public function test_a_photo_tagged_rotate_90_clockwise_is_straightened(): void
    {
        if (! function_exists('exif_read_data') && ! class_exists(\Imagick::class)) {
            $this->markTestSkipped("Ni l'extension exif ni Imagick : l'orientation est illisible.");
        }

        $chemin = $this->jpegMoitieHautRouge(6);

        [$gauche, $droite] = $this->zones($chemin);

        // Orientation 6 = l'image doit etre tournee de 90 degres dans le sens
        // horaire pour etre vue droite. Le bord du haut part donc a droite.
        $this->assertTrue($droite['r'] > $droite['b'] + 40, 'Apres redressement, la droite devrait etre rouge.');
        $this->assertTrue($gauche['b'] > $gauche['r'] + 40, 'Apres redressement, la gauche devrait etre bleue.');

        @unlink($chemin);
    }

    /**
     * Moyenne des canaux sur quatre zones du carre produit.
     *
     * @return array<int, array{r: int, b: int}>
     */
    private function zones(string $chemin): array
    {
        $octets = SquarePhoto::jpegBytes($chemin, 40);
        $this->assertNotNull($octets, 'La photo aurait du etre produite.');

        $image = imagecreatefromstring($octets);
        $this->assertNotFalse($image);

        $lire = function (int $x, int $y) use ($image): array {
            $c = imagecolorat($image, $x, $y);

            return ['r' => ($c >> 16) & 0xFF, 'b' => $c & 0xFF];
        };

        $zones = [$lire(6, 20), $lire(33, 20), $lire(20, 6), $lire(20, 33)];
        imagedestroy($image);

        return $zones;
    }

    /**
     * Un JPEG carre : moitie haute rouge, moitie basse bleue, avec si demande
     * une etiquette EXIF d'orientation inseree a la main.
     */
    private function jpegMoitieHautRouge(?int $orientation): string
    {
        $image = imagecreatetruecolor(40, 40);
        imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 230, 20, 20));
        imagefilledrectangle($image, 0, 20, 39, 39, imagecolorallocate($image, 20, 20, 230));

        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        if ($orientation !== null) {
            $jpeg = substr($jpeg, 0, 2).$this->segmentExif($orientation).substr($jpeg, 2);
        }

        $chemin = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        file_put_contents($chemin, $jpeg);

        return $chemin;
    }

    /**
     * Segment APP1 minimal portant la seule etiquette Orientation (0x0112),
     * en petit-boutiste. Ecrit a la main parce que PHP ne sait pas produire
     * d'EXIF : sans ca, impossible de reproduire une photo de telephone.
     */
    private function segmentExif(int $orientation): string
    {
        $charge = "Exif\0\0"
            ."II\x2A\0\x08\0\0\0"              // en-tete TIFF, IFD0 a l'octet 8
            ."\x01\0"                            // une seule entree
            ."\x12\x01"."\x03\0"."\x01\0\0\0"   // tag 0x0112, type SHORT, 1 valeur
            .pack('v', $orientation)."\0\0"      // la valeur, sur 4 octets
            ."\0\0\0\0";                        // pas d'IFD suivant

        return "\xFF\xE1".pack('n', strlen($charge) + 2).$charge;
    }
}
