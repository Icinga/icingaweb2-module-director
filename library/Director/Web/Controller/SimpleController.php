<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\QuickSearch;
use ipl\Web\CompatController;

// TODO: Delete me
abstract class SimpleController extends CompatController
{
    use DirectorDb;
    use QuickSearch;
}
