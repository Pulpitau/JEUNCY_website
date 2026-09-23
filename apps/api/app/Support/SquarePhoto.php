<?php

namespace App\Support;

/**
 * Recadre une photo au carre, centree sur le sujet, et la renvoie en JPEG.
 *
 * Pourquoi cote serveur et pas en CSS : le gabarit du CV affiche la photo
 * dans un disque (.avatar-img, 170x170), mais dompdf ignore `object-fit` et
 * ETIRE l'image pour remplir la boite. Une photo de portrait — c'est-a-dire
 * a peu pres toutes celles que prennent les candidats — ressortait ecrasee
 * (signale par des etudiants le 2026-09-23). Le seul moyen fiable est de
 * fournir a dompdf une image deja carree.
 */
class SquarePhoto
{
    /**
     * @param  int  $size  cote du carre produit, en pixels
     * @return string|null octets JPEG, ou null si GD manque ou si l'image est illisible
     */
    public static function jpegBytes(string $path, int $size): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1) {
            return null;
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $source) {
            return null;
        }

        // Centre horizontalement, mais cale plus haut verticalement (un tiers
        // du surplus au-dessus, deux tiers en dessous) : sur une photo en
        // pied ou en buste, le visage est au-dessus du milieu de l'image.
        $side = min($width, $height);
        $x = (int) (($width - $side) / 2);
        $y = (int) (($height - $side) / 3);

        $canvas = imagecreatetruecolor($size, $size);
        // Fond blanc pose avant la copie : un PNG transparent virerait au noir
        // en JPEG.
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $size, $size, $side, $side);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }
}
