<?php

namespace ipl\Translation;

class StaticTranslator
{
    /** @var TranslatorInterface */
    private static $translator;

    public static function get()
    {
        if (self::$translator === null) {
            static::setNoTranslator();
        }

        return static::$translator;
    }

    public static function setNoTranslator()
    {
        static::set(new NoTranslator());
    }

    /**
     * @param TranslatorInterface $translator
     */
    public static function set(TranslatorInterface $translator)
    {
        self::$translator = $translator;
    }
}
