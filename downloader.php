<?php 
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

$file = __DIR__.'/tmp/'.$_GET['h'].'/'.$_GET['f'];

if (file_exists($file)){
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    //header('Content-type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename='.basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    ob_clean();
    flush();
    readfile($file);
    exit;
}