<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Web\Form\DirectorForm;

class BasketCreateSnapshotForm extends DirectorForm
{
    /** @var Basket */
    private $basket;

    public function setBasket(Basket $basket)
    {
        $this->basket = $basket;

        return $this;
    }

    public function setup()
    {
        $this->setSubmitLabel($this->translate('Create Snapshot'));
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        /** @var \Icinga\Module\Director\Db $connection */
        $connection = $this->basket->getConnection();
        $snapshot = BasketSnapshot::createForBasket($this->basket, $connection);
        $snapshot->store();
        parent::onSuccess();
    }
}
