<?php


namespace Icinga\Module\Director\Objects;

use Icinga\Application\Config;

class IcingaObjectLiveModificationAvailability
{
    /* @var boolean */
    protected $result;

    /* @var string */
    protected $errorMessage = '';

    public static function isEnabled()
    {
        $config = Config::module('director');
        $liveModificationEnabled = $config->get('liveModification', 'enabled');

        return $liveModificationEnabled === '1';
    }

    /**
     * @return bool
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param bool $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }
}
