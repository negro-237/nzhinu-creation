<?php

// Re-encode generated catalog assets for the web; originals remain in Codex's generated_images directory.
$root = realpath(__DIR__.'/../public/images/demo');
if (! $root || ! function_exists('imagewebp')) {
    throw new RuntimeException('Demo asset directory and GD WebP support are required.');
}
foreach (glob($root.'/*.png') as $source) {
    $image = imagecreatefrompng($source);
    $destination = substr($source, 0, -4).'.webp';
    if (file_exists($destination) || ! imagewebp($image, $destination, 82)) {
        throw new RuntimeException('Refusing overwrite or unable to encode '.$destination);
    }
    imagedestroy($image);
    if (! getimagesize($destination)) {
        throw new RuntimeException('Invalid output '.$destination);
    }
    // Source paths come exclusively from the verified project asset directory above.
    unlink($source);
    echo basename($destination).' '.filesize($destination)." bytes\n";
}
