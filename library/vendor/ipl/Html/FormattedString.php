<?php

namespace dipl\Html;

class FormattedString implements ValidHtml
{
    protected $escaped = true;

    /** @var ValidHtml[] */
    protected $arguments = [];

    /** @var ValidHtml */
    protected $string;

    /**
     * FormattedString constructor.
     * @param $string
     * @param array $arguments
     */
    public function __construct($string, array $arguments = [])
    {
        $this->string = Html::wantHtml($string);

        foreach ($arguments as $key => $val) {
            $this->arguments[$key] = Html::wantHtml($val);
        }
    }

    /**
     * @param $string
     * @return static
     */
    public static function create($string)
    {
        $args = func_get_args();
        array_shift($args);

        return new static($string, $args);
    }

    public function render()
    {
        return vsprintf(
            $this->string->render(),
            $this->arguments
        );
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return Error::render($e);
        }
    }
}
