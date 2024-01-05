<?php
class getImageLink
{
    // for viewing or showing document
    public static function getImage($path)
    {
        // $full_path = $_SERVER['DOCUMENT_ROOT'].'/RMCDMC/writable/uploads/'.$path;
        $storage_path = storage_path("app/public/$path");
        $public_path = public_path("$path");
        $image = false;
        if(file_exists($storage_path) && !$image)
        {
            $image = $storage_path;
        }
        if(file_exists($public_path) && !$image)
        {
            $image = $public_path;
        }
        if(!$image)
        {
            die("File Not Found!.....");
        }

        $getInfo = getimagesize($image);
        $explod_path = explode('.', $image);
        $exp = end($explod_path);
        
        if(file_exists($image) && !isset($getInfo['mime']))
        $getInfo['mime']='application/pdf';
        
        if($getInfo['mime']=='application/pdf')
        {
            header('Content-type: '. $getInfo['mime']);
            header('Content-Length: ' . filesize($image));
            header('Cache-Control: no-cache');
            header('Content-Transfer-Encoding: binary'); 
            header('Accept-Ranges: bytes');
        }
        else
        {
            header('Content-type: '. $getInfo['mime']);
            header('Content-Length: ' . filesize($image));
            header('Cache-Control: no-cache');
        }
        header('Content-type: ' . $getInfo['mime']);
        header('Access-Control-Allow-Origin', '*');
        header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization');
        header('Content-Length: ' . filesize($image));
        header('Cache-Control: no-cache');
        ob_clean();
        flush();
        return readfile($image);
    }
}

getImageLink::getImage($_GET["path"]);