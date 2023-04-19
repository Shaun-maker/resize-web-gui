<?php
include_once "functions.php";
include_once "file_error_code.php";

/* Check the value of upload_max_file and post_max_size. If it's too low, send
warning message */
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
$max_file_uploads = ini_get('max_file_uploads');
print_log($_FILES);
print_log($_POST);

if (isset($_POST["rename"]) && !empty($_POST["rename"])) 
{
    $rename = $_POST["rename"];
}

$files = $_FILES["image"];
$formats = $_POST["format"];
$sizes = $_POST["size"];
$filenames = $files["name"];
$quality = intval($_POST["quality"]);

/* we only want the filename without the extension */
foreach ($filenames as &$filename) 
{
    $filename = pathinfo($filename, PATHINFO_FILENAME);
}

function resizeImg($image, int $size, string $filename)
{
    $cloneImage = $image->clone();
    $cloneImage->resizeImage($size, 0, imagick::FILTER_LANCZOS, 0.5);
    print_log("Resize $filename to $size");
    return $cloneImage;
}

function convertImg($image, string $quality, string $format, string $fileName)
{
    /* $image->setCompressionQuality($quality); */
    $image->setImageFormat($format);
    print_log("Convert $fileName to $format");
    return $image;
}

if (!extension_loaded('imagick')) 
{
    print_log("Error : imagick extension is not loaded.");
    die();
}

/* if resize_images folder already exists, delete it to start with empty directory */
if (file_exists("./resize_images")) 
{
    deleteFiles("./resize_images");
}

/* all new folder hierarchy with new image start at root directory */
createDir("resize_images", base_path("/"));
$resizeImagesFolder = base_path("/resize_images");

/* if we have only one image, don't zip folder, convert image and send it
directly to the client */
if (count($files["name"]) === 1) 
{
    
    if ($files["error"][0] !== 0) 
    {
        print_log($phpFileUploadErrors[$files["error"][$i]]);
        die();
    }
    $image = new Imagick($files["tmp_name"]);
    $image->setImageCompressionQuality($quality);
    $image->setCompressionQuality($quality);

    if (isset($rename) && !empty($rename)) {
        $filename = $rename;
    }
    else {
        $filename = $filenames[0];
    }

    $resizedImg = resizeImg($image, $sizes[0], $filename);
    $convertedImg = convertImg($resizedImg, $quality, $formats[0], $filename);
    $newFullImageName = $filename . "-" . $sizes[0] . "." . $formats[0];

    $imagePath = base_path("/resize_images/" . $newFullImageName);
    $convertedImg->writeImage($imagePath);

    $image->destroy();

    /* empty PHP buffer, and receive only name of new image */
    ob_clean();
    header('Content-type: application/json');
    echo $newFullImageName;
} 
else 
{

    /* create zip archive for download after resize/convert */
    $zip = new ZipArchive();
    $zipFilename = "resize_images.zip";
    $zipPath = $resizeImagesFolder . "/" . $zipFilename;

    if (!$zip->open($zipPath, ZipArchive::CREATE)) {
        print_log("Impossible to create zip archives '$zipFilename'");
    }

    /* For each images, do */
    for ($i = 0; $i < count($files["name"]); $i++) {
        if ($files["error"][$i] !== 0) 
        {
            print_log($phpFileUploadErrors[$files["error"][$i]]);
            die();
        }
        $filenameFolder = $filenames[$i] . "-" . $i;
        createDir($filenameFolder, $resizeImagesFolder . "/");

        /* for each format, do */
        for ($k = 0; $k < count($formats); $k++)
        {
            createDir($formats[$k], "./resize_images/$filenameFolder/");
            $formatFolder = "./resize_images/" . $filenameFolder . "/" . $formats[$k] . "/";

            /* for each size, do */
            for ($j = 0; $j < count($sizes); $j++) 
            {

                $image = new Imagick($files["tmp_name"][$i]);
                $image->setImageCompressionQuality($quality);
                $image->setCompressionQuality($quality);

                if (isset($rename) && !empty($rename)) {
                    $filename = $rename;
                }
                else {
                    $filename = $filenames[$i];
                }

                $newImageName = $filename . "-" . $sizes[$j] . "." . $formats[$k];

                $resizedImg = resizeImg($image, $sizes[$j], $filename);
                $convertedImg = convertImg($resizedImg, $quality, $formats[$k], $filename);
                $convertedImg->writeImage($formatFolder . $newImageName);

                if(!$zip->addFile($formatFolder . $newImageName)) 
                {
                    print_log("Impossible to zip file" . $newImageName);
                }

                $image->destroy();
            }
        }
    }
    $zip->close();

    ob_clean();
    header('Content-type: application/json');
    echo $zipFilename;
}
