<?php

namespace Icinga\Module\Director\Web\Form\Validate;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatalistEntry;
use Zend_Validate_Abstract;

class IsDataListEntry extends Zend_Validate_Abstract
{
    public const INVALID = 'intInvalid';

    /** @var Db */
    private $db;

    /** @var int */
    private $dataListId;

    public function __construct($dataListId, Db $db)
    {
        $this->db = $db;
        $this->dataListId = (int) $dataListId;
    }

    public function isValid($value)
    {
        if (is_array($value)) {
            foreach ($value as $name) {
                if (! $this->isListEntry($name)) {
                    $this->_error(self::INVALID, $value);

                    return false;
                }
            }

            return true;
        }

        if ($this->isListEntry($value)) {
            return true;
        } else {
            $this->_error(self::INVALID, $value);

            return false;
        }
    }

    protected function isListEntry($name)
    {
        return DirectorDatalistEntry::exists([
            'list_id' => $this->dataListId,
            'entry_name' => $name,
        ], $this->db);
    }
}
