<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class IcingaDeleteUsergroupForm extends CompatForm
{
    use CsrfCounterMeasure;

    public function __construct(
        protected IcingaObject $object,
        protected Db $db
    ) {
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Delete')
        ]);
    }

    protected function onSuccess(): void
    {
        $this->object->delete();
    }
}
