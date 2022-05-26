<?php

class I18N_Arabic_CharsetD
{
    /**
     * Loads initialize values
     *
     * @ignore
     */
    public function __construct()
    {
    }

    public function guess($string)
    {
        // The most frequent Arabic letters are Alef, Lam, and Yeh
        $charset['windows-1256'] = substr_count($string, chr(199));
        $charset['windows-1256'] += substr_count($string, chr(225));
        $charset['windows-1256'] += substr_count($string, chr(237));

        $charset['iso-8859-6'] = substr_count($string, chr(199));
        $charset['iso-8859-6'] += substr_count($string, chr(228));
        $charset['iso-8859-6'] += substr_count($string, chr(234));

        $charset['utf-8'] = substr_count($string, chr(216) . chr(167));
        $charset['utf-8'] += substr_count($string, chr(217) . chr(132));
        $charset['utf-8'] += substr_count($string, chr(217) . chr(138));

        $total = $charset['windows-1256'] +
            $charset['iso-8859-6'] +
            $charset['utf-8'];

        if ($total < 1) {
            return $charset;
        }

        $charset['windows-1256'] = round($charset['windows-1256'] * 100 / $total);
        $charset['iso-8859-6']   = round($charset['iso-8859-6'] * 100 / $total);
        $charset['utf-8']        = round($charset['utf-8'] * 100 / $total);

        return $charset;
    }

    public function getCharset($string)
    {
        if (preg_match('/<meta .* charset=([^\"]+)".*>/sim', $string, $matches)) {
            $value = $matches[1];
        } else {
            $charset = $this->guess($string);
            arsort($charset);
            $value = key($charset);
        }

        return $value;
    }
}
