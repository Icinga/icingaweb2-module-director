<?php

namespace ipl\Translation;

class WrapTranslator implements TranslatorInterface
{
    /** @var callable */
    private $callback;

    /** @var TranslatorInterface */
    private $wrapped;

    public function __construct(TranslatorInterface $wrapped, callable $callback)
    {
        $this->wrapped = $wrapped;
        $this->callback = $callback;
    }

    public function translate($string)
    {
        return call_user_func_array(
            $this->callback,
            [$this->wrapped->translate($string)]
        );
    }
}
