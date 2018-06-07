<?php

namespace dipl\Validator;

interface ValidatorInterface
{
    /**
     * // TODO: @throws \RuntimeException
     * @param mixed $value
     * @return bool
     */
    public function isValid($value);

    /**
     * @return array
     */
    public function getMessages();
}
