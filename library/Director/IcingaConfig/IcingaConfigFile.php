<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Objects\IcingaObject;

class IcingaConfigFile
{
    public static $table = 'director_generated_file';
    public static $keyName = 'checksum';

    protected $content;

    public function prepend($content)
    {
        $this->content = $content . $this->content;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getHexChecksum()
    {
        return sha1($this->content);
    }

    public function getChecksum()
    {
        return sha1($this->content, true);
    }

    public function addObjects($objects)
    {
        foreach ($objects as $object) {
            $this->addObject($object);
        }

        return $this;
    }

    public function addObject(IcingaObject $object)
    {
        $this->content .= $object->toConfigString();
    }
}
