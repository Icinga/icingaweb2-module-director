<?php

namespace Icinga\Module\Director\Forms\Validator;

use ipl\I18n\Translation;
use ipl\Validator\BaseValidator;
use ipl\Web\FormElement\TermInput\Term;
use LogicException;

class DatalistEntryValidator extends BaseValidator
{
    use Translation;

    private array $datalistEntries;

    public function setDatalistEntries(array $datalistEntries)
    {
        $this->datalistEntries = $datalistEntries;

        return $this;
    }

    public function isValid($terms)
    {
        if ($this->datalistEntries === null) {
            throw new LogicException(
                'Missing datalist entries. Cannot validate terms.'
            );
        }

        if (! is_array($terms)) {
            $terms = [$terms];
        }

        $isValid = true;

        foreach ($terms as $term) {
            /** @var Term $term */
            $searchValue = $term->getSearchValue();
            if (! array_key_exists($searchValue, $this->datalistEntries)) {
                $term->setMessage($this->translate('Value is not in the datalist.'));

                $isValid = false;
            } else {
                $term->setLabel($this->datalistEntries[$searchValue]);
            }
        }

        return $isValid;
    }
}
