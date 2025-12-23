<?php

use Steganography\Image;

function createTestImage(int $width = 100, int $height = 100): GdImage
{
    $image = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    return $image;
}

it('encodes and decodes a short message', function () {
    $image = createTestImage();
    $secretMessage = "Un message à dissimuler...";

    $encodedImage = Image::encodeMessage($image, $secretMessage);
    $decodedMessage = Image::decodeMessage($encodedImage);

    expect($decodedMessage)->toBe($secretMessage);
});