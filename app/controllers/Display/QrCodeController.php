<?php
/**

 */

namespace SzentirasHu\Controllers\Display;


use Endroid\QrCode\QrCode;
use Imagine;
use Imagine\Gd\Font;
use Imagine\Image\Box;
use Imagine\Image\Color;
use Imagine\Image\Point;
use Request;
use Response;
use View;

class QrCodeController extends \BaseController{

    public function index($url)
    {
        $qrCode = new QrCode();
        $qrCode->setText($url);
        $size = Request::get('size', 150);
        $qrCode->setErrorCorrection(QrCode::LEVEL_HIGH);
        $qrCode->setSize($size);
        $qrCode->setPadding(5);
        // qrCode library renders to output buffer
        ob_start();
        $qrCode->render();
        $image = Imagine::load(ob_get_contents());
        ob_end_clean();
        $font = new Font(base_path()."/resources/fonts/DejaVuSans.ttf", 9, new Color(0));
        $logoText = 'szentiras.hu';
        $margin = 10;
        $textBox = $font->box($logoText)->increase($margin);
        $logo = Imagine::create($textBox, new Color('ffffff'));
        $logo->draw()->text($logoText, $font, new Point($margin/2, $margin/2));
        $image->paste($logo, new Point(($size-$textBox->getWidth())/2,($size-$textBox->getHeight())/2));

        return Response::make($image, 200, array('content-type' => 'image/png'));
    }

    public function dialog($url)
    {
        return View::make('textDisplay.qrDialog')->with([ 'url' => $url]);
    }

}