<?php

namespace dipl\Compat;

use Icinga\Util\Translator as WebTranslator;
use dipl\Translation\TranslatorInterface;

class Translator implements TranslatorInterface
{
    /** @var string */
    private $domain;

    public function __construct($domain)
    {
        $this->domain = $domain;
    }

    public function translate($string)
    {
        return WebTranslator::translate($string, $this->domain);
    }
}
