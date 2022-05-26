<?php
require_once $pwd . '/libs/phparlib/Arabic.php';
$charsetC = new I18N_Arabic('CharsetC');
$charsetD = new I18N_Arabic('CharsetD');

function convertToUTF($string, $charset)
{
    // var_dump(get_declared_classes());
    // var_dump($string);
    global $charsetC;
    $charsetC->setInputCharset($charset);
    $charset = $charsetC->getOutputCharset();
    $text    = $charsetC->convert($string);
    return $text;
}

function getCharset($string)
{
    global $charsetD;
    $charset = $charsetD->getCharset($string);
    return $charset;
}
