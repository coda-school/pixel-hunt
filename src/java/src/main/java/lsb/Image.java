package lsb;

import java.awt.image.BufferedImage;
import java.nio.charset.StandardCharsets;

public final class Image {
    private static final int LSB_MASK = 0xFE;

    private Image() {
        // Utility class
    }

    /**
     * Encode un message dans une image déjà chargée en mémoire.
     * Retourne une nouvelle image contenant le message caché.
     *
     * @param sourceImage   Image source
     * @param secretMessage Message à cacher
     * @return Image encodée
     */
    public static BufferedImage encodeMessage(BufferedImage sourceImage, String secretMessage) {
        int width = sourceImage.getWidth();
        int height = sourceImage.getHeight();

        BufferedImage encodedImage =
                new BufferedImage(width, height, BufferedImage.TYPE_INT_RGB);

        encodedImage.getGraphics().drawImage(sourceImage, 0, 0, null);

        writeBinaryLength(secretMessage, encodedImage);
        writeMessage(encodedImage, toBinaryMessage(secretMessage), height, width);

        return encodedImage;
    }

    /**
     * Écrit la longueur binaire du message dans les 32 premiers pixels de l'image.
     * La longueur est stockée sous forme de 32 bits (4 octets), chaque bit étant caché
     * dans le LSB (Least Significant Bit) des composantes RGB des pixels.
     *
     * @param secretMessage Message dont la longueur doit être encodée
     * @param image         Image dans laquelle écrire la longueur
     */
    private static void writeBinaryLength(String secretMessage, BufferedImage image) {
        int width = image.getWidth();
        int messageLength = secretMessage.getBytes(StandardCharsets.UTF_8).length;

        // Convertir la longueur en une chaîne binaire de 32 bits
        // Exemple : Si messageLength = 20 → "00000000000000000000000000010100"
        // Cela permet de stocker des longueurs jusqu'à 4 294 967 295 (2^32 - 1)
        String binaryLength = String.format("%32s",
                Integer.toBinaryString(messageLength)).replace(' ', '0');

        // Parcourir les 32 premiers bits de la longueur
        for (int i = 0; i < 32; i++) {

            // Calculer les coordonnées (x, y) du pixel courant
            // On parcourt l'image de gauche à droite, de haut en bas
            // Exemple : Pour width=100, i=0 → (0,0), i=100 → (0,1)
            int x = i % width;
            int y = i / width;

            // Récupérer la couleur actuelle du pixel
            int rgb = image.getRGB(x, y);

            // Extraire les composantes Rouge, Vert et Bleu
            // Chaque composante est un entier entre 0 et 255 (8 bits)
            int r = (rgb >> 16) & 0xFF; // Rouge (bits 16-23)
            int g = (rgb >> 8) & 0xFF;  // Vert  (bits 8-15)
            int b = rgb & 0xFF;         // Bleu  (bits 0-7)

            // Récupérer le bit courant de la longueur binaire
            // binaryLength.charAt(i) vaut '0' ou '1'
            int bit = binaryLength.charAt(i) - '0';

            // Modifier le LSB (bit de poids faible) de la composante RGB concernée
            // On alterne entre Rouge (R), Vert (G) et Bleu (B) :
            //      - i % 3 == 0 → Rouge
            //      - i % 3 == 1 → Vert
            //      - i % 3 == 2 → Bleu
            if (i % 3 == 0) r = replaceLSB(r, bit);
            else if (i % 3 == 1) g = replaceLSB(g, bit);
            else b = replaceLSB(b, bit);

            // Recomposer la couleur avec les composantes modifiées
            int newRgb = (r << 16) | (g << 8) | b;

            // Mettre à jour le pixel
            image.setRGB(x, y, newRgb);
        }
    }

    private static int replaceLSB(int value, int bit) {
        // La formule (value & 0xFE) | bit permet de :
        // - Conserver les 7 bits de poids fort (0xFE = 11111110)
        // - Remplacer le LSB par le bit du message
        return (value & LSB_MASK) | bit;
    }

    /**
     * Écrit un message binaire dans une image en utilisant la stéganographie LSB.
     * Chaque bit du message est caché dans le bit de poids faible (LSB)
     * des composantes RGB des pixels, en commençant après les 32 premiers pixels.
     *
     * @param encodingImage Image dans laquelle écrire le message
     * @param binaryMessage Message binaire à cacher (ex: "0100100001101111" pour "Ho")
     * @param height        Hauteur de l'image
     * @param width         Largeur de l'image
     */
    private static void writeMessage(
            BufferedImage encodingImage,
            String binaryMessage,
            int height,
            int width
    ) {
        // Index du bit courant dans binaryMessage
        int bitIndex = 0;

        // Parcourir chaque pixel de l'image, ligne par ligne (de haut en bas)
        for (int y = 0; y < height; y++) {
            for (int x = 0; x < width; x++) {

                // Sauter les 32 premiers pixels (réservés pour la longueur)
                if (y * width + x < 32) continue;

                // Arrêter si tous les bits du message ont été écrits
                if (bitIndex >= binaryMessage.length()) return;

                // 1. Récupérer la couleur actuelle du pixel
                int rgb = encodingImage.getRGB(x, y);

                // 2. Extraire les composantes RGB
                int r = (rgb >> 16) & 0xFF;
                int g = (rgb >> 8) & 0xFF;
                int b = rgb & 0xFF;

                // 3. Parcourir les 3 canaux RGB
                for (int channel = 0; channel < 3; channel++) {

                    // Arrêter si tous les bits ont été traités
                    if (bitIndex >= binaryMessage.length()) break;

                    // Bit courant du message
                    int bit = binaryMessage.charAt(bitIndex++) - '0';

                    // 4. Modifier le LSB du canal courant
                    switch (channel) {
                        case 0 -> r = replaceLSB(r, bit); // Rouge
                        case 1 -> g = replaceLSB(g, bit); // Vert
                        case 2 -> b = replaceLSB(b, bit); // Bleu
                    }
                }

                int newRgb = (r << 16) | (g << 8) | b;
                encodingImage.setRGB(x, y, newRgb);
            }
        }
    }

    /**
     * Convertit un message texte en une chaîne binaire.
     * Chaque caractère est converti en sa représentation binaire sur 8 bits,
     * puis concaténé.
     *
     * @param secretMessage Message texte à convertir
     * @return Chaîne binaire (ex: "0100100001101111")
     */
    private static String toBinaryMessage(String secretMessage) {
        byte[] bytes = secretMessage.getBytes(StandardCharsets.UTF_8);
        StringBuilder binary = new StringBuilder();

        for (byte b : bytes) {
            binary.append(String.format("%8s",
                    Integer.toBinaryString(b & 0xFF)).replace(' ', '0'));
        }
        return binary.toString();
    }

    /**
     * @throws Exception
     */
    public static String decodeMessage(BufferedImage encodedImage) throws Exception {
        throw new Exception("TODO: implement me 🎅");
    }
}