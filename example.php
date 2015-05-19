<?php
require_once 'vendor/autoload.php';

use Verhees\Imagehash\Imagehash;

$compare = Verhees\Imagehash\Imagehash::similarity(
    Imagehash::pHash(__DIR__ . "/demo/Jellyfish.jpg"),
    Imagehash::pHash(__DIR__ . "/demo/Jellyfish.jpg")
);

if($compare == 0)
    echo "Image is identical.";
else if($compare < 10)
    echo "Images are similar, if not the same.";
else
    echo "Images are not the same.";
