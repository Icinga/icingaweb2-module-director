<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Util;

class IcingaConfigFile
{
    public static $table = 'director_generated_file';

    public static $keyName = 'checksum';

    protected $content;

    protected $checksum;

    protected $cntObject = 0;

    protected $cntTemplate = 0;

    public function prepend($content)
    {
        $this->content = $content . $this->content;
        $this->checksum = null;
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;
        $this->checksum = null;
        return $this;
    }

    public function getObjectCount()
    {
        return $this->cntObject;
    }

    public function getTemplateCount()
    {
        return $this->cntTemplate;
    }

    public function getSize()
    {
        return strlen($this->content);
    }

    public function setObjectCount($cnt)
    {
        $this->cntObject = $cnt;
        return $this;
    }

    public function setTemplateCount($cnt)
    {
        $this->cntTemplate = $cnt;
        return $this;
    }

    public function getHexChecksum()
    {
        return Util::binary2hex($this->getChecksum());
    }

    public function getChecksum()
    {
        if ($this->checksum === null) {
            $this->checksum = sha1($this->content, true);
        }

        return $this->checksum;
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
        $this->checksum = null;

        if ($object->hasProperty('object_type')) {
            $type = $object->object_type;

            switch ($type) {
                case 'object':
                    $this->cntObject++;
                    break;
                case 'template':
                    $this->cntTemplate++;
                    break;
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getContent();
    }
}
