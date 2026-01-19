using System.Text;
using SixLabors.ImageSharp;
using SixLabors.ImageSharp.PixelFormats;

namespace Steganography
{
    public static class Image
    {
        private const int LsbMask = 0xFE;

        /**
         * Encode un message dans une image déjà chargée en mémoire.
         * Retourne une nouvelle image contenant le message caché.
         *
         * @param sourceImage Image source
         * @param secretMessage Message à cacher
         * @return Image encodée
         */
        public static Image<Rgba32> EncodeMessage(Image<Rgba32> sourceImage, string secretMessage)
        {
            var width = sourceImage.Width;
            var height = sourceImage.Height;

            // Crée une copie de l'image source
            var encodedImage = sourceImage.Clone();

            WriteBinaryLength(secretMessage, encodedImage);
            WriteMessage(encodedImage, ToBinaryMessage(secretMessage), height, width);

            return encodedImage;
        }

        /**
         * Écrit la longueur binaire du message dans les 32 premiers pixels de l'image.
         */
        private static void WriteBinaryLength(string secretMessage, Image<Rgba32> image)
        {
            var width = image.Width;
            var messageLength = Encoding.UTF8.GetByteCount(secretMessage);

            var binaryLength = Convert.ToString(messageLength, 2).PadLeft(32, '0');

            for (var i = 0; i < 32; i++)
            {
                var x = i % width;
                var y = i / width;

                var pixel = image[x, y];
                var bit = binaryLength[i] - '0';

                if (i % 3 == 0) pixel.R = ReplaceLSB(pixel.R, bit);
                else if (i % 3 == 1) pixel.G = ReplaceLSB(pixel.G, bit);
                else pixel.B = ReplaceLSB(pixel.B, bit);

                image[x, y] = pixel;
            }
        }

        private static byte ReplaceLSB(byte value, int bit)
        {
            // Conserver les 7 bits de poids fort, remplacer le LSB
            return (byte) ((value & LsbMask) | bit);
        }

        /**
         * Écrit un message binaire dans l'image en commençant après les 32 premiers pixels.
         */
        private static void WriteMessage(Image<Rgba32> image, string binaryMessage, int height, int width)
        {
            var bitIndex = 0;

            for (var y = 0; y < height; y++)
            {
                for (var x = 0; x < width; x++)
                {
                    if (y * width + x < 32) continue;
                    if (bitIndex >= binaryMessage.Length) return;

                    var pixel = image[x, y];

                    for (var channel = 0; channel < 3; channel++)
                    {
                        if (bitIndex >= binaryMessage.Length) break;

                        var bit = binaryMessage[bitIndex++] - '0';

                        switch (channel)
                        {
                            case 0: pixel.R = ReplaceLSB(pixel.R, bit); break;
                            case 1: pixel.G = ReplaceLSB(pixel.G, bit); break;
                            case 2: pixel.B = ReplaceLSB(pixel.B, bit); break;
                        }
                    }

                    image[x, y] = pixel;
                }
            }
        }

        /**
         * Convertit un message texte en une chaîne binaire.
         */
        private static string ToBinaryMessage(string secretMessage)
        {
            var bytes = Encoding.UTF8.GetBytes(secretMessage);
            var binary = new StringBuilder();

            foreach (var b in bytes)
            {
                binary.Append(Convert.ToString(b, 2).PadLeft(8, '0'));
            }

            return binary.ToString();
        }

        public static string DecodeMessage(Image<Rgba32> encodedImage)
        {
            throw new NotImplementedException("TODO: implement me 🎅");
        }
    }
}