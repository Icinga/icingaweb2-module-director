<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Zend_Db_Adapter_Exception;

class HostServiceBlacklistForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether the service has been blacklisted or not */
    private $blackListed = false;

    protected $defaultAttributes = ['class' => 'icinga-controls'];

    /** @var IcingaServiceSet Service set to which the service belongs */
    private $set;

    public function __construct(
        protected DbConnection $db,
        protected IcingaHost $host,
        protected IcingaService $service
    ) {
        $this->addAttributes(Attributes::create(['class' => ['host-service-deactivate-form']]));
        if (! $this->hasBeenBlacklisted()) {
            $this->addAttributes(Attributes::create(['class' => ['active']]));
        } else {
            $this->addAttributes(Attributes::create(['class' => ['deactivated']]));
        }
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $blacklisted = $this->hasBeenBlacklisted();
        $this->addElement('submit', 'submit', [
            'label' => $blacklisted
                ? $this->translate('Reactivate Service')
                : $this->translate('Deactivate Service'),
            'class' => $blacklisted ? '' : 'btn-remove'
        ]);
    }

    protected function onSuccess(): void
    {
        if ($this->hasBeenBlacklisted()) {
            if ($this->removeFromBlacklist()) {
                Notification::success(sprintf(
                    $this->translate("Service '%s' on host '%s' has been reactivated"),
                    $this->service->getObjectName(),
                    $this->host->getObjectName()
                ));
            }
        } else {
            if ($this->blacklist()) {
                Notification::success(sprintf(
                    $this->translate("Service '%s' on host '%s' has been deactivated"),
                    $this->service->getObjectName(),
                    $this->host->getObjectName()
                ));
            }
        }
    }

    /**
     * Whether the service has been blacklisted or not
     *
     * @return bool
     */
    public function hasBeenBlacklisted(): bool
    {
        if ($this->service === null) {
            return false;
        }

        if ($this->blackListed === false) {
            // Safety check, branches
            $hostId = $this->host->get('id');
            $serviceId = $this->service->get('id');
            if (! $hostId || ! $serviceId) {
                return false;
            }

            $db = $this->db->getDbAdapter();
            $this->blackListed = 1 === (int) $db->fetchOne(
                    $db->select()->from('icinga_host_service_blacklist', 'COUNT(*)')
                       ->where('host_id = ?', $hostId)
                       ->where('service_id = ?', $serviceId)
                );
        }

        return $this->blackListed;
    }

    /**
     * Remove the service from blacklist for the host
     *
     * @return int
     */
    protected function removeFromBlacklist(): int
    {
        $db = $this->db->getDbAdapter();
        $where = implode(' AND ', [
            $db->quoteInto('host_id = ?', $this->host->get('id')),
            $db->quoteInto('service_id = ?', $this->service->get('id')),
        ]);

        return $db->delete('icinga_host_service_blacklist', $where);
    }

    /**
     * Blacklist the service for the host
     *
     * @return int
     *
     * @throws Zend_Db_Adapter_Exception | IcingaException
     */
    protected function blacklist(): int
    {
        $db = $this->db->getDbAdapter();
        $this->host->unsetOverriddenServiceVars($this->service->getObjectName())->store();

        return $db->insert('icinga_host_service_blacklist', [
            'host_id'    => $this->host->get('id'),
            'service_id' => $this->service->get('id')
        ]);
    }
}
