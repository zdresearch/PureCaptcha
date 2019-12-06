<?php
 /**
 * OWASP PureCaptcha
 * Generates simple CAPTCHAs without any dependencies (no GD, no API).
 * Uses 6MB of memory for 500x150 CAPTCHA on PHP 7, runs in about 10ms.
 * @version 1.2
 * @author abiusx
 */
class PureCaptcha
{
    /**
     * List of chars used in CAPTCHA. Should match the ascii table below.
     * These characters are specifically selected as they are unambiguous.
     * @var string
     */
    protected $chars="2346789ABDHKLNPRTWXYZ";

    /**
     * A compressed 13*6*len(chars) table of 0s and 1s for rendering ASCII text
     * @var string
     */
    protected $ascii="eJztm0tuwzAMRK8U+ZOPc5ose4Yidy/ayEUkUGOOKle0rUWgLPrAWp6hS
	NN5TJ2bPj+m0/0xuX7+dn59eX2cXzu/9n4d/Dr+rM/vP8wlu3fSvX06v/Z+Hfz6S/YopkMxh2xy
	zCbP2eQlm7xmk7ds0kERYTRfRa7LRL12d6f/OGZAQv2fophr6j91nXr9w+uE+ofkTborKlLWv+p
	CK+m/a/ovo39IFs7/7q7YW1H/KpLSP8j/1A2tpP++6X97+leRhfRPkTj/Y7SO/oft6D8geyZHpf
	VPkWM2ec6+zguKqdd/6r8VY5aqfzi0jv7Hpv/t6h/urUr/y/m/QP2jQ+vo/7wH/Z8iUq9/ihTrH
	xVZuP9VxaTqn4Ck6h9F/lddaCX9X5r+a+sfkk3/6+r/ak//KTLYabH+4fVPkbD+cYgU6x8Vqap/
	nERS9X9a/9Rd2V79f9uT/lWkqv4XUxSl/4CE9T/codb/rjz/MjgAtmAA/gCAJNUA8weAGJNqgA9
	7ADiDE+CjGCCVx/UGgCQ0ACRFA8TnlWjXDRrA4Aj4Pw3wTueNwFTkyiNgMaaqBW4lkMEZcDPAkg
	FUZHsHTmUAg0NgFUkZICCpEihtAIqEJRAk9zAEm3PEbAC5eqpkAENT4DinxvuVbwBbPcDSdaZ7g
	KW9TfcAFHmkt0CdoTFwM8BfT4C0ASjyUAYwNAfelgGonAp7gJlcNkBM8j1A3LGI5KFKIEODYIsG
	CGIW7gHaCXA3YABDk2Aqv6neBBJ3unoJRJGiAVQptb4BOLTSL8EMTYJT5PIJQJGqnwKLZOHHoCq
	SehUoIEUDqKwDH4PCmPgxKEZrGOD5BRevL54=";

    function __construct()
    {
        $this->ascii=unserialize(gzuncompress(base64_decode(
        	preg_replace('/\s+/', '', $this->ascii))));
    }

    /**
     * Generates random text for use in captcha
     */
    protected function randomText($length=4)
    {
        $res="";
        for ($i=0;$i<$length;++$i)
            $res.=$this->chars[rand(0,strlen($this->chars)-1)];
        return $res;
    }

    /**
     * Displays a bitmap string on the browser screen
     */
    protected function displayBitmap($bitmap)
    {
        header("Content-Type: image/bmp");
        echo $this->bitmap2bmp($bitmap);
    }

    /**
     * Return width and height of bitmap
     * @return array
     */
    private function bitmapDimensions($bitmap)
    {
    	return [count($bitmap[0]), count($bitmap)];
    }

    /**
     * Generates a monochrome BMP file from a bitmap
     * @param  array $bitmap 2D array with every element being either 1 or 0
     * @return string
     */
    protected function bitmap2bmp($bitmap)
    {
        list($width, $height)=$this->bitmapDimensions($bitmap);
        $bytemap=$this->bitmap2bytemap($bitmap);

        $rowSize=floor(($width+31)/32)*4;
        $size=$rowSize*$height + 62; // 62 metadata size
        # bitmap header
        $data= "BM"; // header
        $data.= (pack('V',$size)); // bitmap size, 4B unsigned LE
        $data.= "RRRR";
        $data.= (pack('V',14+40+8)); // bitmap data start offset,
        // 4 bytes unsigned little endian, 14 forced, 40 header, 8 colors

        # info header
        $data.= pack('V',40); // bitmap header size (min 40), 4B unsigned LE
        $data.= pack('V',$width); // bitmap width, 4 bytes signed integer
        $data.= pack('V',$height); // bitmap height, 4 bytes signed integer
        $data.= pack('v',1); // number of colored plains, 2 bytes
        $data.= pack('v',1); // color depth, 2 bytes
        $data.= pack('V',0); // compression algorithm, 4 bytes (0=none, RGB)
        $data.= pack('V',0); // size of raw data, 0 is fine for no compression
        $data.= pack('V',11808); // horizontal resolution (dpi), 4 bytes
        $data.= pack('V',11808); // vertical resolution (dpi), 4 bytes
        $data.= pack('V',0); // number of colors in pallette (0 = all), 4 bytes
        $data.= pack('V',0); // number of important colors (0 = all), 4 bytes

        # color palette
        $data.= (pack('V',0x00FFFFFF)); // first color, white
        $data.= (pack('V',0)); // second color, black

        for ($j=$height-1;$j>=0;--$j)
            for ($i=0;$i<$rowSize/4;++$i)
                for ($k=0;$k<4;++$k)
                    if (isset($bytemap[$j][$i*4+$k]))
                        $data.= pack('C',$bytemap[$j][$i*4+$k]);
                    else
                        $data.= pack('C',0);
        return $data;
    }

    /**
     * Converts a bitmap to a bytemap, necessary for conversion to BMP.
     */
    private function bitmap2bytemap($bitmap)
    {
        list($width, $height)=$this->bitmapDimensions($bitmap);
        $bytemap=[];
        for ($j=0;$j<$height;++$j)
            for ($i=0;$i<$width/8;++$i)
            {
                $bitstring="";
                for ($k=0;$k<8;++$k)
                    if (isset($bitmap[$j][$i*8+$k]))
                        $bitstring.=$bitmap[$j][$i*8+$k];
                    else
                        $bitstring.="0";
                $bytemap[$j][]=bindec($bitstring);
            }
        return $bytemap;
    }

    /**
     * Rotates a bitmap, returning new dimensions with the bitmap
     */
    protected function rotateBitmap($bitmap, $degree)
    {
        $c=cos(deg2rad($degree));
        $s=sin(deg2rad($degree));

        list($width, $height)=$this->bitmapDimensions($bitmap);
        $newHeight=round(abs($width*$s) + abs($height*$c));
        $newWidth=round(abs($width*$c) + abs($height*$s))+1;
        $x0 = $width/2 - $c*$newWidth/2 - $s*$newHeight/2;
        $y0 = $height/2 - $c*$newHeight/2 + $s*$newWidth/2;
        $result=array_fill(0, $newHeight, array_fill(0, $newWidth, 0));
        for ($j=0;$j<$newHeight;++$j)
            for ($i=1;$i<$newWidth;++$i)
            {
                $y=(int)(-$s*$i+$c*$j+$y0);
                $x=(int)($c*$i+$s*$j+$x0);
                if (isset($bitmap[$y][$x]))
                    $result[$j][$i]=$bitmap[$y][$x];
            }
        return $result;
    }

    /**
     * Scale a bitmap based on scale factors
     */
    protected function scaleBy($bitmap, $scaleX, $scaleY)
    {
        list($width, $height)=$this->bitmapDimensions($bitmap);
        return $this->scaleTo($bitmap, $width*$scaleX, $height*$scaleY);
    }

    /**
     * Scale a bitmap to new width and height
     */
    protected function scaleTo($bitmap, $newWidth, $newHeight)
    {
        list($width, $height)=$this->bitmapDimensions($bitmap);
        $scaleX=$newWidth/$width;
        $scaleY=$newHeight/$height;
        $result=array_fill(0, $newHeight, array_fill(0, $newWidth, 0));
        for ($j=0;$j<$newHeight;++$j)
            for ($i=0;$i<$newWidth;++$i)
                $result[$j][$i]=@$bitmap[(int)($j/$scaleY)]
                [(int)($i/$scaleX)];
        return $result;
    }

    /**
     * Merge multiple bitmaps into one
     */
    protected function mergeBitmaps(array $bitmap, $spacing = 0)
    {
    	if (empty($bitmap))
    		return [];
    	$res=$bitmap[0];
    	for ($i=1;$i<count($bitmap);++$i)
    		for ($y=0;$y<count($bitmap[$i]);++$y)
    		{
    			for ($_=0;$_<$spacing;++$_)
	    			$res[$y][]=0; // spacing
    			for ($x=0;$x<count($bitmap[$i][0]);++$x)
    				$res[$y][]=$bitmap[$i][$y][$x];
    		}
    	return $res;
    }

    /**
     * Adds random noise to a bitmap
     */
    protected function distort($bitmap, $noisePercentage)
    {
        for ($j=0;$j<count($bitmap);++$j)
            for ($i=0;$i<count($bitmap[0]);++$i)
                if (isset($bitmap[$j][$i]) && rand()%100<$noisePercentage)
                    $bitmap[$j][$i]=1;
        return $bitmap;
    }

    /**
     * Converts a string to a bitmap, rotating each character
     */
    protected function textBitmap($text, $rotationDegrees = [0,0])
    {
        $bitmap=[];
    	for ($i=0;$i<strlen($text);++$i)
    	{
    		$bitmap[$i]=$this->ascii[strpos($this->chars, $text[$i])];
        	$degree=rand($rotationDegrees[0],$rotationDegrees[1]);
        	if (rand()%100<50)
            	$degree=-$degree;
            $bitmap[$i]=$this->scaleBy($bitmap[$i],5,5); // More clear letters.
			$bitmap[$i]=$this->rotateBitmap($bitmap[$i], $degree);
    		$bitmap[$i]=$this->scaleTo($bitmap[$i], 60, 65); // Uniform sizes.
    	}
    	return $this->mergeBitmaps($bitmap);
    }

    /**
     * Display captcha on the output, return its text
     */
    public function show($length = [4,5], $width = 500, $height = 150,
    	$rotate = [1,35], $distort = [50,60])
    {
        $text=$this->randomText(rand($length[0],$length[1]));
    	$bitmap=$this->textBitmap($text, $rotate);
    	$bitmap=$this->scaleTo($bitmap, $width, $height);
        $bitmap=$this->distort($bitmap, rand($distort[0],$distort[1]));
        $this->displayBitmap($bitmap);
        return $text;
    }
}
