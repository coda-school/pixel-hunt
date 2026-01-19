using SixLabors.ImageSharp;
using SixLabors.ImageSharp.PixelFormats;

namespace Steganography.Tests;

public class ImageTests
{
    private static Image<Rgba32> CreateTestImage(int width = 100, int height = 100)
    {
        var image = new Image<Rgba32>(width, height);

        for (var y = 0; y < height; y++)
        for (var x = 0; x < width; x++)
            image[x, y] = new Rgba32(255, 255, 255);

        return image;
    }

    [Fact]
    public void Encode_Decode_Return_Original_Message()
    {
        var image = CreateTestImage();
        const string secretMessage = "Un message à dissimuler...";

        var encoded = Image.EncodeMessage(image, secretMessage);

        Assert.Equal(secretMessage, Image.DecodeMessage(encoded));
    }
}