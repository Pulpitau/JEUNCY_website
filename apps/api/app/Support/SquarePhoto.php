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

        // Redresse AVANT de recadrer : une rotation echange largeur et hauteur,
        // donc un recadrage calcule sur les dimensions d'origine viserait a
        // cote. Voir orientation() pour pourquoi c'est necessaire.
        $source = self::redresse($source, self::orientation($path, $info[2]));
        $width = imagesx($source);
        $height = imagesy($source);

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

    /**
     * Degres de rotation a appliquer avec imagerotate(), qui compte en sens
     * ANTI-horaire, pour une valeur d'orientation EXIF donnee.
     *
     * Publique parce que testable : c'est ici que se niche l'erreur de signe,
     * et une photo couchee ne se remarque qu'une fois le PDF sous les yeux
     * d'un recruteur.
     */
    public static function degresPour(int $orientation): int
    {
        return match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,  // EXIF 6 = « tourner de 90 degres dans le sens horaire »
            7, 8 => 90,
            default => 0,
        };
    }

    /**
     * Les orientations EXIF miroir (rares : elles viennent de certaines
     * camera frontales). Un miroir ne se corrige pas par une rotation.
     */
    public static function estMiroir(int $orientation): bool
    {
        return in_array($orientation, [2, 4, 5, 7], true);
    }

    /**
     * Orientation EXIF declaree par le fichier, 1 si aucune.
     *
     * POURQUOI. Un telephone n'ecrit pas l'image dans le sens ou on la voit :
     * il l'enregistre dans le sens du capteur et ajoute une etiquette « a
     * afficher tournee de 90 degres ». Les navigateurs respectent cette
     * etiquette, GD l'ignore — d'ou une photo droite sur le site et couchee
     * dans le CV genere (signale le 2026-10-01).
     *
     * Deux chemins parce que les deux hebergements different : l'extension
     * exif est presente en local, Imagick l'est sur le mutualise OVH. En
     * l'absence des deux, on ne redresse rien plutot que de deviner.
     */
    private static function orientation(string $path, int $imageType): int
    {
        // L'etiquette n'existe que dans les JPEG (et TIFF) : inutile d'aller
        // la chercher ailleurs.
        if ($imageType !== IMAGETYPE_JPEG) {
            return 1;
        }

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);

            return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
        }

        if (class_exists(\Imagick::class)) {
            try {
                $orientation = (new \Imagick($path))->getImageOrientation();

                return $orientation > 0 ? $orientation : 1;
            } catch (\Throwable) {
                // Une photo illisible par Imagick reste lisible par GD : on
                // continue sans redresser plutot que d'echouer la generation.
                return 1;
            }
        }

        return 1;
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private static function redresse($source, int $orientation)
    {
        $degres = self::degresPour($orientation);

        if ($degres !== 0) {
            $tourne = @imagerotate($source, $degres, 0);
            if ($tourne !== false) {
                imagedestroy($source);
                $source = $tourne;
            }
        }

        if (self::estMiroir($orientation) && function_exists('imageflip')) {
            imageflip($source, IMG_FLIP_HORIZONTAL);
        }

        return $source;
    }
}
