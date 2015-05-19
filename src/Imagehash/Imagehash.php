<?php namespace Verhees\Imagehash;

use Exception, Imagick;

/**
 * Class ImageHash
 *
 * @package Verhees\Imagehash
 */
class ImageHash {

    /**
     * @var
     */
    protected $filePath;

    /**
     * @var null
     */
    protected $origImageBlob = null;
    /**
     * @var null
     */
    protected $preparedImage = null;

    /**
     *
     */
    const A_HASH_IMAGE_WIDTH = 8;
    /**
     *
     */
    const A_HASH_IMAGE_HEIGHT = 8;

    /**
     *
     */
    const P_HASH_IMAGE_WIDTH = 32;
    /**
     *
     */
    const P_HASH_IMAGE_HEIGHT = 32;

    /**
     *
     */
    const D_HASH_IMAGE_WIDTH = 9;
    /**
     *
     */
    const D_HASH_IMAGE_HEIGHT = 8;

    /**
     * @var
     */
    public static $dct;

    /**
     * @param $filePath
     *
     * @throws \Exception
     */
    public function __construct($filePath)
    {
        $this->load($filePath);
    }

    /**
     * @param $path
     *
     * @throws \Exception
     */
    protected function load($path)
    {

        if(!file_exists($path))
            throw new Exception("File [{$path}] doesn't exist.");

        $this->filePath = $path;
        $this->origImageBlob = file_get_contents($path);
    }

    /**
     * @param $width
     * @param $height
     *
     * @return null|resource
     */
    protected function resize($width, $height)
    {

        if(class_exists('Imagick'))
        {

            $image = new Imagick;
            $image->readImageBlob($this->origImageBlob);
            $image->thumbnailImage($width, $height);

            $this->preparedImage = imagecreatefromstring($image->getImageBlob());

            $image->destroy();
        }
        else
        {

            $imageBlob = $this->origImageBlob;

            $imageFunc = function()
            {
                switch (exif_imagetype($this->filePath))
                {
                    case IMAGETYPE_GIF:
                        return imagecreatefromgif($this->filePath);
                    case IMAGETYPE_JPEG:
                        return imagecreatefromjpeg($this->filePath);
                    case IMAGETYPE_PNG:
                        return imagecreatefrompng($this->filePath);
                    case IMAGETYPE_BMP:
                        return imagecreatefrombmp($this->filePath);
                    case IMAGETYPE_WBMP:
                        return imagecreatefromwbmp($this->filePath);
                    case IMAGETYPE_XBM:
                        return imagecreatefromxbm($this->filePath);
                }

                return false;
            };

            if(!$image = $imageFunc($this->origImageBlob))
                throw new Exception("GD Library is required.");

            $destImage = imagecreatetruecolor($width, $height);

            imagecopyresized(
                $destImage,
                $image,
                0, 0, 0, 0,
                $width, $height,
                imagesx($image), imagesy($image)
            );

            $this->preparedImage = $destImage;

            imagedestroy($image);
        }

        unset($image);

        return $this->preparedImage;
    }

    /**
     * @return mixed
     */
    protected function cosineTransform()
    {
        if(static::$dct)
            return static::$dct;

        static::$dct = [];

        for($dctP = 0; $dctP < static::A_HASH_IMAGE_WIDTH; $dctP ++)
            for($p = 0; $p < static::P_HASH_IMAGE_HEIGHT; $p ++)
                static::$dct[$dctP][$p] = cos(
                    (
                        ( 2 * $p + 1 ) / ( static::A_HASH_IMAGE_WIDTH *
                            static::A_HASH_IMAGE_HEIGHT )
                    ) * $dctP * pi()
                );


        return static::$dct;
    }

    /**
     * @param $x
     * @param $y
     *
     * @return int
     */
    protected function greys($x, $y)
    {
        $rgb = imagecolorsforindex($this->preparedImage, imagecolorat($this->preparedImage, $x, $y));

        return intval( $rgb['red'] * 0.3 + $rgb['green'] * 0.59 + $rgb['blue'] * 0.11);
    }

    /**
     * @return string
     */
    public static function aHash($file)
    {
        $image = new ImageHash($file);
        $image->resize(static::A_HASH_IMAGE_WIDTH, static::A_HASH_IMAGE_HEIGHT);

        $sum = 0;
        $grays = [];

        for($y = 0; $y < static::A_HASH_IMAGE_HEIGHT; $y++)
            for ($x = 0; $x < static::A_HASH_IMAGE_WIDTH; $x++)
                $grays[] = $image->greys($x, $y);

        $sum = array_sum($grays);

        $avg = ( $sum / ( static::A_HASH_IMAGE_WIDTH * static::A_HASH_IMAGE_HEIGHT ) );

        foreach($grays as $i => $gray)
            $grays[$i] = ( $gray >= $avg ) ? "1" : "0";

        return implode("", $grays);
    }

    /**
     * @return string
     */
    public static function pHash($file)
    {
        $image = new ImageHash($file);
        $image->resize(static::P_HASH_IMAGE_WIDTH, static::P_HASH_IMAGE_HEIGHT);

        $grays = [];

        for($y = 0; $y < 32; $y ++)
            for($x = 0; $x < 32; $x ++)
                $grays[$y][$x] = $image->greys($x, $y);

        $dct = $image->cosineTransform();

        $dcts = [];

        for($dctY = 0; $dctY < static::A_HASH_IMAGE_HEIGHT; $dctY ++)

            for($dctX = 0; $dctX < static::A_HASH_IMAGE_WIDTH; $dctX ++)
            {

                $sum = 1;

                for ($y = 0; $y < static::P_HASH_IMAGE_HEIGHT; $y ++)
                    for($x = 0;$x < static::P_HASH_IMAGE_WIDTH;$x ++)
                    {
                        $sum += ( $dct[$dctY][$y] * $dct[$dctX][$x] * $grays[$y][$x] );
                    }

                $sum *= .25;

                if($dctY == 0 OR $dctX == 0)
                    $sum *= ( 1 / sqrt(2) );

                $dcts[] = $sum;
            }

        $avg = ( ( array_sum($dcts) ) / ( static::A_HASH_IMAGE_WIDTH * static::A_HASH_IMAGE_HEIGHT ) );

        foreach($dcts as $i => $dct)
            $dcts[$i] = (string) ( $dct >= $avg ) ? "1" : "0";

        return implode("", $dcts);
    }

    /**
     * @param \Verhees\ImageHash\ImageHash $a
     * @param \Verhees\ImageHash\ImageHash $b
     *
     * @return bool|int
     */
    public static function similarity($a, $b)
    {
        $aL = strlen($a);
        $bL = strlen($b);

        if ($aL !== $bL)
            return false;

        $dist = 0;

        for($i = 0; $i < $aL; $i ++)
            if($a{$i} !== $b{$i})
                $dist++;

        return $dist;
    }
}