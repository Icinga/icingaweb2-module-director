<?php

namespace Icinga\Module\Director\Test\Web;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class TestDirectorObjectForm extends DirectorObjectForm
{
    protected function getActionFromRequest()
    {
        $this->setAction('director/test/url');
        return $this;
    }

    public function regenerateCsrfToken()
    {
        return $this;
    }
}
