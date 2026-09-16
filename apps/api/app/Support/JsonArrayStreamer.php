<?php

namespace App\Support;

use Generator;
use RuntimeException;

/**
 * Lit un a un les objets d'un tableau JSON, sans jamais charger le fichier.
 *
 * L'export de La bonne alternance fait plusieurs centaines de Mo : un
 * json_decode le mettrait entier en memoire, ce que l'hebergement mutualise
 * ne permet pas. Ecrit ici plutot que pris d'une bibliotheque pour ne pas
 * avoir a deployer vendor/ par FTP — chaque fichier envoye est un fichier
 * qui peut ne pas arriver (voir CLAUDE.md, lecons de deploiement).
 *
 * Accepte deux formes : un tableau a la racine (`[{...}, {...}]`) ou un
 * objet dont une cle contient le tableau (`{"jobs": [{...}], ...}`, cle
 * passee en parametre). Chaque element est decode separement ; un element
 * illisible est saute, jamais fatal. La memoire utilisee est celle du plus
 * gros objet, plus un tampon de lecture.
 */
final class JsonArrayStreamer
{
    private const CHUNK = 65536;

    /**
     * @return Generator<int, array>
     */
    public static function objects(string $path, ?string $key = null): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir {$path}");
        }

        try {
            $buffer = '';
            $started = false;   // le '[' du tableau vise a ete trouve
            $scanFrom = 0;      // premier octet du tampon pas encore balaye
            $depth = 0;         // profondeur d'accolades, 0 = entre deux objets
            $inString = false;
            $escaped = false;
            $objectStart = null; // position dans le tampon du '{' de l'objet en cours

            while (true) {
                $chunk = fread($handle, self::CHUNK);
                if ($chunk === false || $chunk === '') {
                    return; // fin de fichier (un objet tronque est abandonne)
                }
                $buffer .= $chunk;

                if (! $started) {
                    $pos = self::findArrayStart($buffer, $key);
                    if ($pos === null) {
                        // Garder une queue : la cle cherchee peut etre a
                        // cheval sur deux lectures.
                        $buffer = substr($buffer, -256);

                        continue;
                    }
                    $buffer = substr($buffer, $pos + 1);
                    $started = true;
                    $scanFrom = 0;
                }

                $length = strlen($buffer);
                for ($i = $scanFrom; $i < $length; $i++) {
                    $char = $buffer[$i];

                    // Dans une chaine, '{' et '}' n'ont aucun sens structurel.
                    if ($inString) {
                        if ($escaped) {
                            $escaped = false;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '"') {
                            $inString = false;
                        }

                        continue;
                    }

                    if ($char === '"') {
                        $inString = true;
                    } elseif ($char === '{') {
                        if ($depth === 0) {
                            $objectStart = $i;
                        }
                        $depth++;
                    } elseif ($char === '}') {
                        $depth--;
                        if ($depth === 0 && $objectStart !== null) {
                            $decoded = json_decode(substr($buffer, $objectStart, $i - $objectStart + 1), true);
                            if (is_array($decoded)) {
                                yield $decoded;
                            }
                            $objectStart = null;
                        }
                    } elseif ($char === ']' && $depth === 0) {
                        return; // fin du tableau vise
                    }
                }

                // Compacter : ne garder que l'objet en cours de lecture.
                if ($objectStart !== null) {
                    $buffer = substr($buffer, $objectStart);
                    $objectStart = 0;
                } else {
                    $buffer = '';
                }
                $scanFrom = strlen($buffer);
            }
        } finally {
            fclose($handle);
        }
    }

    // Position du '[' qui ouvre le tableau vise, ou null s'il n'est pas
    // encore dans le tampon.
    private static function findArrayStart(string $buffer, ?string $key): ?int
    {
        if ($key === null) {
            $trimmed = ltrim($buffer);

            return str_starts_with($trimmed, '[') ? strlen($buffer) - strlen($trimmed) : null;
        }

        $needle = '"'.$key.'"';
        $keyPos = strpos($buffer, $needle);
        if ($keyPos === false) {
            return null;
        }
        $bracket = strpos($buffer, '[', $keyPos + strlen($needle));

        return $bracket === false ? null : $bracket;
    }
}
