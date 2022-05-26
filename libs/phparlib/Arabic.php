<?php

class I18N_Arabic
{
    private $_inputCharset  = 'utf-8';
    private $_outputCharset = 'utf-8';
    private $_useAutoload;
    private $_useException;
    private $_compatibleMode;

    private $_compatible = array('EnTransliteration' => 'Transliteration',
        'ArTransliteration'                              => 'Transliteration',
        'ArAutoSummarize'                                => 'AutoSummarize',
        'ArCharsetC'                                     => 'CharsetC',
        'ArCharsetD'                                     => 'CharsetD',
        'ArDate'                                         => 'Date',
        'ArGender'                                       => 'Gender',
        'ArGlyphs'                                       => 'Glyphs',
        'ArIdentifier'                                   => 'Identifier',
        'ArKeySwap'                                      => 'KeySwap',
        'ArNumbers'                                      => 'Numbers',
        'ArQuery'                                        => 'Query',
        'ArSoundex'                                      => 'Soundex',
        'ArStrToTime'                                    => 'StrToTime',
        'ArWordTag'                                      => 'WordTag',
        'ArCompressStr'                                  => 'CompressStr',
        'ArMktime'                                       => 'Mktime',
        'ArStemmer'                                      => 'Stemmer',
        'ArStandard'                                     => 'Standard',
        'ArNormalise'                                    => 'Normalise',
        'a4_max_chars'                                   => 'a4MaxChars',
        'a4_lines'                                       => 'a4Lines',
        'swap_ea'                                        => 'swapEa',
        'swap_ae'                                        => 'swapAe');

    /**
     * @ignore
     */
    public $myObject;

    /**
     * @ignore
     */
    public $myClass;

    /**
     * @ignore
     */
    public $myFile;

    public function __construct(
        $library, $useAutoload = false, $useException = false, $compatibleMode = true
    ) {
        $this->_useAutoload    = $useAutoload;
        $this->_useException   = $useException;
        $this->_compatibleMode = $compatibleMode;

        /* Set internal character encoding to UTF-8 */
        mb_internal_encoding("utf-8");

        if ($this->_useAutoload) {
            // It is critical to remember that as soon as spl_autoload_register() is
            // called, __autoload() functions elsewhere in the application may fail
            // to be called. This is safer initial call (PHP 5 >= 5.1.2):
            if (false === spl_autoload_functions()) {
                if (function_exists('__autoload')) {
                    spl_autoload_register('__autoload', false);
                }
            }

            spl_autoload_extensions('.php,.inc,.class');
            spl_autoload_register('I18N_Arabic::autoload', false);
        }

        if ($this->_useException) {
            set_error_handler('I18N_Arabic::myErrorHandler');
        }

        if ($library) {
            if ($this->_compatibleMode
                && array_key_exists($library, $this->_compatible)
            ) {
                $library = $this->_compatible[$library];
            }

            $this->load($library);
        }
    }

    public static function autoload($className)
    {
        include self::getClassFile($className);
    }

    public static function myErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if ($errfile == __FILE__
            || file_exists(
                dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Arabic' .
                DIRECTORY_SEPARATOR . basename($errfile)
            )
        ) {
            $msg = '<b>Arabic Class Exception:</b> ';
            $msg .= $errstr;
            $msg .= " in <b>$errfile</b>";
            $msg .= " on line <b>$errline</b><br />";

            throw new ArabicException($msg, $errno);
        }

        // If the function returns false then the normal error handler continues
        return false;
    }

    public function load($library)
    {
        if ($this->_compatibleMode
            && array_key_exists($library, $this->_compatible)
        ) {

            $library = $this->_compatible[$library];
        }

        $this->myFile  = $library;
        $this->myClass = 'I18N_Arabic_' . $library;
        $class         = 'I18N_Arabic_' . $library;

        if (!$this->_useAutoload) {
            include self::getClassFile($this->myFile);
        }

        $this->myObject   = new $class();
        $this->{$library} = &$this->myObject;
    }

    public function __call($methodName, $arguments)
    {
        if ($this->_compatibleMode
            && array_key_exists($methodName, $this->_compatible)
        ) {

            $methodName = $this->_compatible[$methodName];
        }

        // Create an instance of the ReflectionMethod class
        $method = new ReflectionMethod($this->myClass, $methodName);

        $params     = array();
        $parameters = $method->getParameters();

        foreach ($parameters as $parameter) {
            $name  = $parameter->getName();
            $value = array_shift($arguments);

            if (is_null($value) && $parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            }

            $params[$name] = $this->coreConvert(
                $value,
                $this->getInputCharset(),
                'utf-8'
            );
        }

        $value = call_user_func_array(array(&$this->myObject, $methodName), $params);

        if ($methodName == 'tagText') {
            foreach ($value as $key => $text) {
                $value[$key][0] = $this->coreConvert(
                    $text[0], 'utf-8', $this->getOutputCharset()
                );
            }
        } else {
            $value = $this->coreConvert($value, 'utf-8', $this->getOutputCharset());
        }

        return $value;
    }

    public function __destruct()
    {
        $this->_inputCharset  = null;
        $this->_outputCharset = null;
        $this->myObject       = null;
        $this->myClass        = null;
    }

    public function setInputCharset($charset)
    {
        $flag = true;

        $charset = strtolower($charset);

        if (in_array($charset, array('utf-8', 'windows-1256', 'iso-8859-6'))) {
            $this->_inputCharset = $charset;
        } else {
            $flag = false;
        }

        return $flag;
    }

    public function setOutputCharset($charset)
    {
        $flag = true;

        $charset = strtolower($charset);

        if (in_array($charset, array('utf-8', 'windows-1256', 'iso-8859-6'))) {
            $this->_outputCharset = $charset;
        } else {
            $flag = false;
        }

        return $flag;
    }

    public function getInputCharset()
    {
        return $this->_inputCharset;
    }

    public function getOutputCharset()
    {
        return $this->_outputCharset;
    }

    public function coreConvert($str, $inputCharset, $outputCharset)
    {
        if ($inputCharset != $outputCharset) {
            if ($inputCharset == 'windows-1256') {
                $inputCharset = 'cp1256';
            }

            if ($outputCharset == 'windows-1256') {
                $outputCharset = 'cp1256';
            }

            $convStr = iconv($inputCharset, "$outputCharset", $str);

            if ($convStr == '' && $str != '') {
                include self::getClassFile('CharsetC');

                $c = I18N_Arabic_CharsetC::singleton();

                if ($inputCharset == 'cp1256') {
                    $convStr = $c->win2utf($str);
                } else {
                    $convStr = $c->utf2win($str);
                }
            }
        } else {
            $convStr = $str;
        }

        return $convStr;
    }

    public function convert($str, $inputCharset = null, $outputCharset = null)
    {
        if ($inputCharset == null) {
            $inputCharset = $this->_inputCharset;
        }

        if ($outputCharset == null) {
            $outputCharset = $this->_outputCharset;
        }

        $str = $this->coreConvert($str, $inputCharset, $outputCharset);

        return $str;
    }

    protected static function getClassFile($class)
    {
        $dir  = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Arabic';
        $file = $dir . DIRECTORY_SEPARATOR . $class . '.php';

        return $file;
    }

    public static function header($mode = 'http', $conn = null)
    {
        $mode = strtolower($mode);
        $head = '';

        switch ($mode) {
            case 'http':
                header('Content-Type: text/html; charset=' . $this->_outputCharset);
                break;

            case 'html':
                $head .= '<meta http-equiv="Content-type" content="text/html; charset=';
                $head .= $this->_outputCharset . '" />';
                break;

            case 'text_email':
                $head .= 'MIME-Version: 1.0\r\nContent-type: text/plain; charset=';
                $head .= $this->_outputCharset . '\r\n';
                break;

            case 'html_email':
                $head .= 'MIME-Version: 1.0\r\nContent-type: text/html; charset=';
                $head .= $this->_outputCharset . '\r\n';
                break;

            case 'mysql':
                if ($this->_outputCharset == 'utf-8') {
                    mysql_set_charset('utf8');
                } elseif ($this->_outputCharset == 'windows-1256') {
                    mysql_set_charset('cp1256');
                }
                break;

            case 'mysqli':
                if ($this->_outputCharset == 'utf-8') {
                    $conn->set_charset('utf8');
                } elseif ($this->_outputCharset == 'windows-1256') {
                    $conn->set_charset('cp1256');
                }
                break;

            case 'pdo':
                if ($this->_outputCharset == 'utf-8') {
                    $conn->exec('SET NAMES utf8');
                } elseif ($this->_outputCharset == 'windows-1256') {
                    $conn->exec('SET NAMES cp1256');
                }
                break;
        }

        return $head;
    }

    public static function getBrowserLang()
    {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2); // ar, en, etc...

        return $lang;
    }

    public static function isForum($html)
    {
        $forum = false;

        if (strpos($html, 'vBulletin_init();') !== false) {
            $forum = true;
        }

        return $forum;
    }
}

class ArabicException extends Exception
{
    /**
     * Make sure everything is assigned properly
     *
     * @param string $message Exception message
     * @param int    $code    User defined exception code
     */
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
