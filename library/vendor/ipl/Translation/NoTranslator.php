<?php

namespace ipl\Translation;

class NoTranslator implements TranslatorInterface
{
    public function translate($string)
    {
        return $string;
    }
}
