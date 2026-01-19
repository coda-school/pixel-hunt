import {intToRGBA, Jimp, rgbaToInt} from "jimp";

export class Image {
    // Masque pour effacer le LSB (bit de poids faible) d'une composante RGB
    private static readonly LSB_MASK = 0xfe;

    /**
     * Encode un message dans une image déjà chargée en mémoire.
     * Retourne une nouvelle image contenant le message caché.
     *
     * @param image Image source (Jimp)
     * @param secretMessage Message texte à cacher
     * @returns Nouvelle image contenant le message caché
     */
    static async encodeMessage(image: InstanceType<typeof Jimp>, secretMessage: string): Promise<InstanceType<typeof Jimp>> {
        // Crée une copie de l'image source pour ne pas modifier l'originale
        const encoded = image.clone();

        // On écrit d'abord la longueur du message dans les 32 premiers pixels
        await Image.writeBinaryLength(secretMessage, encoded);

        // On écrit ensuite le message en binaire dans les pixels suivants
        await Image.writeMessage(encoded, Image.toBinaryMessage(secretMessage));

        return encoded;
    }

    /**
     * Décodage volontairement non implémenté pour le moment
     *
     * @param encodedImage Image encodée
     */
    static async decodeMessage(encodedImage: InstanceType<typeof Jimp>): Promise<string> {
        throw new Error("TODO: implement me 🎅");
    }

    /**
     * Écrit la longueur binaire du message dans les 32 premiers pixels de l'image.
     * La longueur est stockée sur 32 bits (4 octets), chaque bit étant caché
     * dans le LSB des composantes RGB des pixels.
     *
     * @param secretMessage Message dont la longueur doit être encodée
     * @param image Image dans laquelle écrire la longueur
     */
    private static async writeBinaryLength(secretMessage: string, image: InstanceType<typeof Jimp>) {
        const width = image.width;
        // On calcule le nombre d'octets du message en UTF-8
        const messageLength = Buffer.byteLength(secretMessage, "utf8");

        // Convertit la longueur en binaire sur 32 bits
        const binaryLength = messageLength.toString(2).padStart(32, "0");

        // Parcours des 32 premiers bits
        for (let i = 0; i < 32; i++) {
            const x = i % width; // colonne du pixel
            const y = Math.floor(i / width); // ligne du pixel

            // Récupère la couleur actuelle du pixel
            const pixel = intToRGBA(image.getPixelColor(x, y));
            const bit = parseInt(binaryLength[i], 10);

            // On alterne les canaux RGB : R, G, B
            if (i % 3 === 0) pixel.r = Image.replaceLSB(pixel.r, bit);
            else if (i % 3 === 1) pixel.g = Image.replaceLSB(pixel.g, bit);
            else pixel.b = Image.replaceLSB(pixel.b, bit);

            // On remet le pixel modifié dans l'image
            image.setPixelColor(rgbaToInt(pixel.r, pixel.g, pixel.b, pixel.a), x, y);
        }
    }

    /**
     * Remplace le LSB (bit de poids faible) d'une composante RGB par le bit donné
     *
     * @param value Valeur de la composante (0-255)
     * @param bit Bit à insérer (0 ou 1)
     * @returns Nouvelle valeur de la composante avec le LSB remplacé
     */
    private static replaceLSB(value: number, bit: number): number {
        return (value & Image.LSB_MASK) | bit;
    }

    /**
     * Écrit un message binaire dans l'image en commençant après les 32 premiers pixels
     *
     * @param image Image dans laquelle écrire le message
     * @param binaryMessage Message binaire à cacher (ex: "0100100001101111" pour "Ho")
     */
    private static async writeMessage(image: InstanceType<typeof Jimp>, binaryMessage: string) {
        const width = image.width;
        const height = image.height;
        let bitIndex = 0;

        // Parcours de tous les pixels ligne par ligne
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                // On saute les 32 premiers pixels réservés pour la longueur
                if (y * width + x < 32) continue;

                // Arrête si tous les bits ont été écrits
                if (bitIndex >= binaryMessage.length) return;

                const pixel = intToRGBA(image.getPixelColor(x, y));

                // Parcours des canaux RGB pour insérer les bits
                for (let channel = 0; channel < 3; channel++) {
                    if (bitIndex >= binaryMessage.length) break;
                    const bit = parseInt(binaryMessage[bitIndex++], 10);

                    switch (channel) {
                        case 0:
                            pixel.r = Image.replaceLSB(pixel.r, bit);
                            break;
                        case 1:
                            pixel.g = Image.replaceLSB(pixel.g, bit);
                            break;
                        case 2:
                            pixel.b = Image.replaceLSB(pixel.b, bit);
                            break;
                    }
                }

                // Mise à jour du pixel dans l'image
                image.setPixelColor(rgbaToInt(pixel.r, pixel.g, pixel.b, pixel.a), x, y);
            }
        }
    }

    /**
     * Convertit un message texte en une chaîne binaire
     *
     * @param secretMessage Message texte
     * @returns Message binaire (chaîne de "0" et "1")
     */
    private static toBinaryMessage(secretMessage: string): string {
        const buffer = Buffer.from(secretMessage, "utf8");
        return Array.from(buffer)
            .map(b => b.toString(2).padStart(8, "0"))
            .join("");
    }
}