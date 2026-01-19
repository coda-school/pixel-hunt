import {Jimp} from "jimp";
import {Image} from "../src/image";

async function createTestImage(width = 100, height = 100) {
    return new Jimp({width: 256, height: 256, color: 0xffffffff});
}

describe("Stéganographie LSB", () => {
    it("encode / décode retourne le message original", async () => {
        const image = await createTestImage();
        const secretMessage = "Ho! Ho! Ho! 🎅";
        const encodedImage = await Image.encodeMessage(image, secretMessage);

        expect(await Image.decodeMessage(encodedImage))
            .toBe(secretMessage);
    });
});
