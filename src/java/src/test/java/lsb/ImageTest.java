package lsb;

import org.junit.jupiter.api.Test;

import java.awt.*;
import java.awt.image.BufferedImage;

import static lsb.Image.encodeMessage;
import static org.assertj.core.api.Assertions.assertThat;

class ImageTest {
    private BufferedImage createTestImage(int width, int height) {
        var image = new BufferedImage(
                width,
                height,
                BufferedImage.TYPE_INT_RGB
        );

        var g = image.createGraphics();
        g.setColor(Color.BLUE);
        g.fillRect(0, 0, width, height);
        g.dispose();

        return image;
    }

    @Test
    void encodes_and_decodes_a_short_message() throws Exception {
        var image = createTestImage(100, 100);
        var secretMessage = "Un message à dissimuler...";

        var encodedImage = encodeMessage(image, secretMessage);

        assertThat(Image.decodeMessage(encodedImage))
                .isEqualTo(secretMessage);
    }
}