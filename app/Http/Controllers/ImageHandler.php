<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use GDText\Box;
use GDText\Color;


class ImageHandler extends Controller
{
    /*******************************************************
     * Supply an image through http request, the image is returned branded with logo and optional text
     *
     * Recognized options:
     * =====================
     * width    <width>        
     * Desired width in pixels of resulting image as a positive integer (max 16384)
     *
     * height   <height>       
     * Desired height in pixels of resulting image as a positive integer (max 16384)
     * 
     * If width and height are empty, original image size will be used. 
     * Both width and height need to be either set or empty.
     * 
     * logocolor    white | black   
     * Color of logo. Defaults to black if value invalid or not set.
     * 
     * logoposition top-left | top-right | bottom-left | bottom-right 
     * Position of logo. Defaults to bottom right if calue invalid or not set.
     *
     * text <text>         
     * Optional text to add to center of image
     *
     * textcolor    white | black   
     * Color of text. Defaults to black if value invalid or not set.
     *
     * textsize     large | medium | small  
     * Size of text. Defaults to medium if value invalid or not set.
     *
     * darken       true | false    
     * Darken image. Defaults to false.
     ******************************************************************************************/


    public function processImage(Request $request) {
        $secret_token = '12345';

        if ($request->bearerToken() !== $secret_token) {
            return response()->json(['error' => 'API token missing or incorrect'], 401);
        }

        $imageinfo = $request->image;
        
        if ($imageinfo === null) {
            return response()->json(['error' => 'No file sent'], 400);
        }
        if (!exif_imagetype($imageinfo->path())) {
            return response()->json(['error' => 'Not a valid image file'], 415);
        }

        $image = Image::make($imageinfo->path());

        if (empty($request->width) xor empty($request->height)) {
            return response()->json(['error' => 'width and height need to both be set, or both empty'], 400);
        }


        if (!empty($request->width) && !empty($request->height)) {
            $filteropts = array('options' => array('min_range' => 1, 'max_range' => 16384));
            
            if (filter_var($request->width, FILTER_VALIDATE_INT, $filteropts) && 
                filter_var($request->width, FILTER_VALIDATE_INT, $filteropts)) {
                $image->fit($request->width, $request->height);
            } else {
                return response()->json(['error' => 'width and height need to be positive integers of reasonable size'], 400);
            }
        }

        if ($request->darken === 'true') {
            $image->insert(Image::canvas($image->width(), $image->height(), 'rgba(20,20,20,0.5)'));
        }

        if (!empty($request->text)) {
            $tcol = ($request->textcolor === 'white') ? [255, 255, 255] : [0, 0, 0];
            switch ($request->textsize) {
                case 'small':
                    $tsize = 20;
                    break;
                case 'large':
                    $tsize = 40;
                    break;
                default:
                    $tsize = 30;
                    break;
            }
            $im = $image->getCore();
            $box = new Box($im);
            $box->setFontFace(storage_path('app') . '/' . 'GT-Cinetype-Light.ttf'); 
            $box->setFontSize($tsize);
            $box->setFontColor(new Color($tcol[0], $tcol[1], $tcol[2])); 
            $box->setBox(0.1 * $image->width(), 0, 0.8 * $image->width(), $image->height());
            $box->setTextAlign('center', 'center');
            $box->draw($request->text);
        }
        if ($request->logocolor === "white") {
            $logo = Image::make(Storage::Disk('local')->get('lundqvist_logotyp_vit.png'));
        } else {
            $logo = Image::make(Storage::Disk('local')->get('lundqvist_logotyp_svart.png'));
        }

        switch ($request->logoposition) {
            case 'top-left':
                $logoposition = 'top-left';
                break;
            case 'top-right':
                $logoposition = 'top-right';
                break;
            case 'bottom-left':
                $logoposition = 'bottom-left';
                break;
            default:
                $logoposition = 'bottom-right';
                break;
        }
        
        $logo->widen($image->width()/2);

        $image->insert($logo, $logoposition);

        $newname = pathinfo($imageinfo->getClientOriginalName(), PATHINFO_FILENAME)
                            . '-logo' . '.' .
                            pathinfo($imageinfo->getClientOriginalName(), PATHINFO_EXTENSION);
        $image->save(storage_path('app/public') . '/' . $newname);


        return response()->json(['url' => asset("storage") . "/" . $newname]);
    }
}
