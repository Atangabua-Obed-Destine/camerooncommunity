<?php
$img = imagecreatetruecolor(100, 100);
imagepng($img, 'test.png');
$res = getimagesize('test.png');
echo "getimagesize:\n";
var_dump($res);

try {
    $i = \Intervention\Image\Laravel\Facades\Image::read('test.png');
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
