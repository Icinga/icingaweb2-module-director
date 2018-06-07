<?php

namespace dipl\Validator;

abstract class SimpleValidator implements ValidatorInterface
{
    use MessageContainer;

    protected $settings = [];

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        } else {
            return $default;
        }
    }
}
