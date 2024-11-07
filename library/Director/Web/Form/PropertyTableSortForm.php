<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Web\Session;
use ipl\Html\Form;
use ipl\Html\ValidHtml;
use ipl\Web\Common\CsrfCounterMeasure;

class PropertyTableSortForm extends Form
{
    use CsrfCounterMeasure;

    protected $method = 'POST';

    /** @var string Name of the form */
    private $name;

    /** @var ValidHtml Property table to sort */
    private $table;

    public function __construct(string $name, ValidHtml $table)
    {
        $this->name = $name;
        $this->table = $table;
    }

    protected function assemble()
    {
        $this->addElement('hidden', '__FORM_NAME', ['value' => $this->name]);
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addHtml($this->table);
    }
}
