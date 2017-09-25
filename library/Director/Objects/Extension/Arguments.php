<?php

namespace Icinga\Module\Director\Objects\Extension;

use Icinga\Module\Director\Objects\IcingaArguments;
use Icinga\Module\Director\Objects\IcingaObject;

trait Arguments
{
    private $arguments;

    public function arguments()
    {
        /** @var IcingaObject $this */
        if ($this->arguments === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->arguments = IcingaArguments::loadForStoredObject($this);
            } else {
                $this->arguments = new IcingaArguments($this);
            }
        }

        return $this->arguments;
    }

    public function gotArguments()
    {
        return null !== $this->arguments;
    }

    public function unsetArguments()
    {
        unset($this->arguments);
    }

    /**
     * @return string
     */
    protected function renderArguments()
    {
        return $this->arguments()->toConfigString();
    }

    /**
     * @param $value
     * @return $this
     */
    protected function setArguments($value)
    {
        $this->arguments()->setArguments($value);
        return $this;
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return $this->arguments()->toPlainObject();
    }
}
