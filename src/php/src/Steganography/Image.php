<?php

declare(strict_types=1);

namespace Steganography;

use Exception;
use GdImage;
use function imagecolorat;

final class Image
{
    private const MASK_LSB_CLEAR = 0xFE;
    private const MASK_8BITS = 0xFF;
    private const PIXELS_TO_FIND_LENGTH = 32;

    /**
     * Encode un message dans une image GD déjà chargée en mémoire.
     * retourne une nouvelle image GD contenant le message caché.
     *
     * @param GdImage $sourceImage Ressource GD de l'image source
     * @param string $secretMessage Message à cacher
     * @return resource
     */
    static function encodeMessage(GdImage $sourceImage, string $secretMessage): GdImage
    {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $encodedImage = imagecreatetruecolor($width, $height);
        imagecopy($encodedImage, $sourceImage, 0, 0, 0, 0, $width, $height);

        self::writeBinaryLength($secretMessage, $encodedImage);
        self::writeMessage($encodedImage, self::toBinaryMessage($secretMessage), $height, $width);

        return $encodedImage;
    }

    /**
     * Écrit la longueur binaire du message dans les 32 premiers pixels de l'image.
     * La longueur est stockée sous forme de 32 bits (4 octets), chaque bit étant caché
     * dans le LSB (Least Significant Bit) des composantes RGB des pixels.
     *
     * @param string $secretMessage Message dont la longueur doit être encodée
     * @param GdImage $image Ressource GD de l'image où écrire la longueur
     * @return void
     */
    private static function writeBinaryLength(string $secretMessage, GdImage $image): void
    {
        $width = imagesx($image);
        $messageLength = strlen($secretMessage);

        // Convertir la longueur en une chaîne binaire de 32 bits
        // Exemple : Si $messageLength = 20 → "00000000000000000000000000010100"
        // Cela permet de stocker des longueurs jusqu'à 4 294 967 295 (2^32 - 1)
        $binaryLength = str_pad(decbin($messageLength), self::PIXELS_TO_FIND_LENGTH, '0', STR_PAD_LEFT);

        // Parcourir les 32 premiers bits de la longueur
        for ($i = 0; $i < self::PIXELS_TO_FIND_LENGTH; $i++) {
            // Calculer les coordonnées (x, y) du pixel courant
            // On parcourt l'image de gauche à droite, de haut en bas
            // Exemple : Pour $width=100, $i=0 → (0,0), $i=100 → (0,1), etc.
            $x = $i % $width;
            $y = (int)($i / $width);

            // Récupérer la couleur actuelle du pixel
            $rgb = imagecolorat($image, $x, $y);

            // Extraire les composantes Rouge, Vert et Bleu
            // Chaque composante est un entier entre 0 et 255 (8 bits)
            $r = ($rgb >> 16) & self::MASK_8BITS;  // Décalage pour isoler le rouge (bits 16-23)
            $g = ($rgb >> 8) & self::MASK_8BITS;   // Décalage pour isoler le vert (bits 8-15)
            $b = $rgb & self::MASK_8BITS;          // Masque pour isoler le bleu (bits 0-7)

            // Récupérer le bit courant de la longueur binaire
            // $binaryLength[$i] vaut '0' ou '1'
            $bit = $binaryLength[$i];

            // Modifier le LSB (bit de poids faible) de la composante RGB concernée
            // On alterne entre Rouge (R), Vert (G) et Bleu (B) :
            //      - $i % 3 == 0 → On modifie le Rouge
            //      - $i % 3 == 1 → On modifie le Vert
            //      - $i % 3 == 2 → On modifie le Bleu

            // La formule ($r & 0xFE) | $bit permet de :
            //      - Conserver les 7 bits de poids fort de la composante (0xFE = 11111110 en binaire)
            //      - Remplacer le LSB (bit de poids faible) par le bit du message ($bit)
            if ($i % 3 == 0) $r = self::replaceLSB($r, $bit);
            else if ($i % 3 == 1) $g = self::replaceLSB($g, $bit);
            else $b = self::replaceLSB($b, $bit);

            // Créer une nouvelle couleur avec les composantes modifiées
            $newColor = imagecolorallocate($image, $r, $g, $b);

            // Mettre à jour le pixel avec la nouvelle couleur
            imagesetpixel($image, $x, $y, $newColor);
        }
    }

    private static function replaceLSB(mixed $r, string $bit): int
    {
        //    La formule ($r & 0xFE) | $bit permet de :
        //    - Conserver les 7 bits de poids fort (0xFE = 11111110 en binaire)
        //    - Remplacer le LSB par le bit du message ($bit)
        return ($r & self::MASK_LSB_CLEAR) | $bit;
    }

    /**
     * Écrit un message binaire dans une image GD en utilisant la stéganographie LSB.
     * Chaque bit du message est caché dans le bit de poids faible (LSB) des composantes RGB des pixels,
     * en commençant après les 32 premiers pixels (réservés pour la longueur du message).
     *
     * @param GdImage $encodingImage Ressource GD de l'image dans laquelle écrire le message
     * @param string $binaryMessage Message binaire à cacher (ex: "0100100001101111" pour "Ho")
     * @param int $height Hauteur de l'image en pixels
     * @param int $width Largeur de l'image en pixels
     * @return void
     */
    public static function writeMessage(GdImage $encodingImage, string $binaryMessage, int $height, int $width): void
    {
        // Index du bit courant dans $binaryMessage
        $bitIndex = 0;

        // Parcourir chaque pixel de l'image, ligne par ligne (de haut en bas)
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Sauter les 32 premiers pixels (réservés pour stocker la longueur du message)
                if ($y * $width + $x < self::PIXELS_TO_FIND_LENGTH) continue;

                // Arrêter si tous les bits du message ont été écrits
                if ($bitIndex >= strlen($binaryMessage)) break;

                // 1. Récupérer la couleur actuelle du pixel (x, y)
                $rgb = imagecolorat($encodingImage, $x, $y);

                // 2. Extraire les composantes Rouge, Vert et Bleu
                //    Chaque composante est un entier entre 0 et 255 (8 bits)
                $r = ($rgb >> 16) & self::MASK_8BITS;  // Composante Rouge (bits 16-23)
                $g = ($rgb >> 8) & self::MASK_8BITS;   // Composante Verte (bits 8-15)
                $b = $rgb & self::MASK_8BITS;          // Composante Bleu (bits 0-7)

                // 3. Parcourir les 3 canaux RGB (Rouge, Vert, Bleu) du pixel
                for ($channel = 0; $channel < 3; $channel++) {
                    // Arrêter si tous les bits ont été traités
                    if ($bitIndex >= strlen($binaryMessage)) break;

                    // Récupérer le bit courant du message binaire
                    $bit = $binaryMessage[$bitIndex++];

                    // 4. Modifier le LSB (bit de poids faible) du canal courant
                    switch ($channel) {
                        case 0: // Rouge
                            $r = self::replaceLSB($r, $bit);
                            break;
                        case 1: // Vert
                            $g = self::replaceLSB($g, $bit);
                            break;
                        case 2: // Bleu
                            $b = self::replaceLSB($b, $bit);
                            break;
                    }
                }

                $newColor = imagecolorallocate($encodingImage, $r, $g, $b);
                imagesetpixel($encodingImage, $x, $y, $newColor);
            }
        }
    }

    /**
     * Convertit un message texte en une chaîne binaire.
     * Chaque caractère du message est converti en sa représentation binaire sur 8 bits,
     * puis tous les bits sont concaténés pour former une longue chaîne binaire.
     *
     * @param string $secretMessage Message texte à convertir en binaire
     * @return string Chaîne binaire représentant le message (ex: "0100100001101111" pour "Ho")
     */
    private static function toBinaryMessage(string $secretMessage): string
    {
        $messageBytes = str_split($secretMessage);
        $binaryMessage = '';

        foreach ($messageBytes as $char) {
            $binaryMessage .= self::toBinary($char);
        }
        return $binaryMessage;
    }

    private static function toBinary(string $char): string
    {
        return str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }


    /**
     * @throws Exception
     */
    public static function decodeMessage(GdImage $encodedImage): GdImage
    {
        throw new Exception('TODO: implement me 🎅');
    }
}