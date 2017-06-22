<?php

namespace ipl\Translation;

trait TranslationHelper
{
    /** @var TranslatorInterface */
    private static $translator;

    /**
     * @param $string
     * @param string|null $context
     * @return string
     */
    public function translate($string, $context = null)
    {
        return self::getTranslator()->translate($string);
    }

    public static function getTranslator()
    {
        return StaticTranslator::get();
    }

    public static function setNoTranslator()
    {
        StaticTranslator::set(new NoTranslator());
    }

    /**
     * @param TranslatorInterface $translator
     */
    public static function setTranslator(TranslatorInterface $translator)
    {
        StaticTranslator::set($translator);
    }
}
