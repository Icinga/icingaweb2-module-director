<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Json\JsonString;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use Icinga\Date\DateFormatter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\RestoreObjectForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\IcingaWeb2\Widget\Tabs;

class ActivityLogInfo extends HtmlDocument
{
    use TranslationHelper;

    protected $defaultTab;

    /** @var Db */
    protected $db;

    /** @var string */
    protected $type;

    /** @var string */
    protected $typeName;

    /** @var string */
    protected $name;

    protected $entry;

    protected $oldProperties;

    protected $newProperties;

    protected $oldObject;

    /** @var Tabs */
    protected $tabs;

    /** @var int */
    protected $id;

    public function __construct(Db $db, $type = null, $name = null)
    {
        $this->db = $db;
        if ($type !== null) {
            $this->setType($type);
        }
        $this->name = $name;
    }

    public function setType($type)
    {
        $this->type = $type;
        $this->typeName = $this->translate(
            ucfirst(preg_replace('/^icinga_/', '', $type)) // really?
        );

        return $this;
    }

    /**
     * @param Url $url
     * @return HtmlElement
     * @throws \Icinga\Exception\IcingaException
     */
    public function getPagination(Url $url)
    {
        /** @var Url $url */
        $url = $url->without('checksum')->without('show');
        $div = Html::tag('div', [
            'class' => 'pagination-control',
            'style' => 'float: right; width: 5em'
        ]);

        $ul = Html::tag('ul', ['class' => 'nav tab-nav']);
        $li = Html::tag('li', ['class' => 'nav-item']);
        $ul->add($li);
        $neighbors = $this->getNeighbors();
        $iconLeft = new Icon('angle-double-left');
        $iconRight = new Icon('angle-double-right');
        if ($neighbors->prev) {
            $li->add(new Link($iconLeft, $url->with('id', $neighbors->prev)));
        } else {
            $li->add(Html::tag('span', ['class' => 'disabled'], $iconLeft));
        }

        $li = Html::tag('li', ['class' => 'nav-item']);
        $ul->add($li);
        if ($neighbors->next) {
            $li->add(new Link($iconRight, $url->with('id', $neighbors->next)));
        } else {
            $li->add(Html::tag('span', ['class' => 'disabled'], $iconRight));
        }

        return $div->add($ul);
    }

    /**
     * @param $tabName
     * @return $this
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\IcingaException
     */
    public function showTab($tabName)
    {
        if ($tabName === null) {
            $tabName = $this->defaultTab;
        }

        $this->getTabs()->activate($tabName);
        $this->add($this->getInfoTable());
        if ($tabName === 'old') {
            // $title = sprintf('%s former config', $this->entry->object_name);
            $diffs = IcingaConfigDiff::getDiffs($this->oldConfig(), $this->emptyConfig());
        } elseif ($tabName === 'new') {
            // $title = sprintf('%s new config', $this->entry->object_name);
            $diffs = IcingaConfigDiff::getDiffs($this->emptyConfig(), $this->newConfig());
        } else {
            $diffs = IcingaConfigDiff::getDiffs($this->oldConfig(), $this->newConfig());
        }

        $this->addDiffs($diffs);

        return $this;
    }

    protected function emptyConfig()
    {
        return new IcingaConfig($this->db);
    }

    /**
     * @param $diffs
     * @throws \Icinga\Exception\IcingaException
     */
    protected function addDiffs($diffs)
    {
        foreach ($diffs as $file => $diff) {
            $this->add(Html::tag('h3', null, $file))->add($diff);
        }
    }

    /**
     * @return RestoreObjectForm
     * @throws \Icinga\Exception\IcingaException
     */
    protected function getRestoreForm()
    {
        return RestoreObjectForm::load()
            ->setDb($this->db)
            ->setObject($this->oldObject())
            ->handleRequest();
    }

    public function setChecksum($checksum)
    {
        if ($checksum !== null) {
            $this->entry = $this->db->fetchActivityLogEntry($checksum);
            $this->id = (int) $this->entry->id;
        }

        return $this;
    }

    public function setId($id)
    {
        if ($id !== null) {
            $this->entry = $this->db->fetchActivityLogEntryById($id);
            $this->id = (int) $id;
        }

        return $this;
    }

    public function getNeighbors()
    {
        return $this->db->getActivitylogNeighbors(
            $this->id,
            $this->type,
            $this->name
        );
    }

    public function getCurrentObject()
    {
        return IcingaObject::loadByType(
            $this->type,
            $this->name,
            $this->db
        );
    }

    /**
     * @return bool
     * @deprecated No longer used?
     */
    public function objectStillExists()
    {
        return IcingaObject::existsByType(
            $this->type,
            $this->objectKey(),
            $this->db
        );
    }

    protected function oldProperties()
    {
        if ($this->oldProperties === null) {
            if (property_exists($this->entry, 'old_properties')) {
                $this->oldProperties = JsonString::decodeOptional($this->entry->old_properties);
            }
            if ($this->oldProperties === null) {
                $this->oldProperties = new \stdClass;
            }
        }

        return $this->oldProperties;
    }

    protected function newProperties()
    {
        if ($this->newProperties === null) {
            if (property_exists($this->entry, 'new_properties')) {
                $this->newProperties = JsonString::decodeOptional($this->entry->new_properties);
            }
            if ($this->newProperties === null) {
                $this->newProperties = new \stdClass;
            }
        }

        return $this->newProperties;
    }

    protected function getEntryProperty($key)
    {
        $entry = $this->entry;

        if (property_exists($entry, $key)) {
            return $entry->{$key};
        } elseif (property_exists($this->newProperties(), $key)) {
            return $this->newProperties->{$key};
        } elseif (property_exists($this->oldProperties(), $key)) {
            return $this->oldProperties->{$key};
        } else {
            return null;
        }
    }

    protected function objectLinkParams()
    {
        $entry = $this->entry;

        $params = ['name' => $entry->object_name];

        if ($entry->object_type === 'icinga_service') {
            if (($set = $this->getEntryProperty('service_set')) !== null) {
                $params['set'] = $set;
                return $params;
            } elseif (($host = $this->getEntryProperty('host')) !== null) {
                $params['host'] = $host;
                return $params;
            } else {
                return $params;
            }
        } elseif ($entry->object_type === 'icinga_service_set') {
            return $params;
        } else {
            return $params;
        }
    }

    protected function getActionExtraHtml()
    {
        $entry = $this->entry;

        $info = '';
        $host = null;

        if ($entry->object_type === 'icinga_service') {
            if (($set = $this->getEntryProperty('service_set')) !== null) {
                $info = Html::sprintf(
                    '%s "%s"',
                    $this->translate('on service set'),
                    Link::create(
                        $set,
                        'director/serviceset',
                        ['name' => $set],
                        ['data-base-target' => '_next']
                    )
                );
            } else {
                $host = $this->getEntryProperty('host');
            }
        } elseif ($entry->object_type === 'icinga_service_set') {
            $host = $this->getEntryProperty('host');
        }

        if ($host !== null) {
            $info = Html::sprintf(
                '%s "%s"',
                $this->translate('on host'),
                Link::create(
                    $host,
                    'director/host',
                    ['name' => $host],
                    ['data-base-target' => '_next']
                )
            );
        }

        return $info;
    }

    /**
     * @return array
     * @deprecated No longer used?
     */
    protected function objectKey()
    {
        $entry = $this->entry;
        if ($entry->object_type === 'icinga_service' || $entry->object_type === 'icinga_service_set') {
            // TODO: this is not correct. Activity needs to get (multi) key support
            return ['name' => $entry->object_name];
        }

        return $entry->object_name;
    }

    /**
     * @param Url|null $url
     * @return Tabs
     * @throws ProgrammingError
     */
    public function getTabs(Url $url = null)
    {
        if ($this->tabs === null) {
            $this->tabs = $this->createTabs($url);
        }

        return $this->tabs;
    }

    /**
     * @param Url $url
     * @return Tabs
     * @throws ProgrammingError
     */
    public function createTabs(Url $url)
    {
        $entry = $this->entry;
        $tabs = new Tabs();
        if ($entry->action_name === 'modify') {
            $tabs->add('diff', [
                'label' => $this->translate('Diff'),
                'url'   => $url->without('show')->with('id', $entry->id)
            ]);

            $this->defaultTab = 'diff';
        }

        if (in_array($entry->action_name, ['create', 'modify'])) {
            $tabs->add('new', [
                'label' => $this->translate('New object'),
                'url'   => $url->with(['id' => $entry->id, 'show' => 'new'])
            ]);

            if ($this->defaultTab === null) {
                $this->defaultTab = 'new';
            }
        }

        if (in_array($entry->action_name, ['delete', 'modify'])) {
            $tabs->add('old', [
                'label' => $this->translate('Former object'),
                'url'   => $url->with(['id' => $entry->id, 'show' => 'old'])
            ]);

            if ($this->defaultTab === null) {
                $this->defaultTab = 'old';
            }
        }

        return $tabs;
    }

    /**
     * @return IcingaObject
     * @throws \Icinga\Exception\IcingaException
     */
    protected function oldObject()
    {
        if ($this->oldObject === null) {
            $this->oldObject = $this->createObject(
                $this->entry->object_type,
                $this->entry->old_properties
            );
        }

        return $this->oldObject;
    }

    /**
     * @return IcingaObject
     * @throws \Icinga\Exception\IcingaException
     */
    protected function newObject()
    {
        return $this->createObject(
            $this->entry->object_type,
            $this->entry->new_properties
        );
    }

    protected function objectToConfig(IcingaObject $object)
    {
        if ($object instanceof IcingaService) {
            return $this->previewService($object);
        } else {
            return $object->toSingleIcingaConfig();
        }
    }

    protected function previewService(IcingaService $service)
    {
        if (($set = $service->get('service_set')) !== null) {
            // simulate rendering of service in set
            $set = IcingaServiceSet::load($set, $this->db);

            $service->set('service_set_id', null);
            if (($assign = $set->get('assign_filter')) !== null) {
                $service->set('object_type', 'apply');
                $service->set('assign_filter', $assign);
            }
        }

        return $service->toSingleIcingaConfig();
    }

    /**
     * @return IcingaConfig
     * @throws \Icinga\Exception\IcingaException
     */
    protected function newConfig()
    {
        return $this->objectToConfig($this->newObject());
    }

    /**
     * @return IcingaConfig
     * @throws \Icinga\Exception\IcingaException
     */
    protected function oldConfig()
    {
        return $this->objectToConfig($this->oldObject());
    }

    protected function getLinkToObject()
    {
        // TODO: This logic is redundant and should be centralized
        $entry = $this->entry;
        $name = $entry->object_name;
        $controller = preg_replace('/^icinga_/', '', $entry->object_type);

        if ($controller === 'service_set') {
            $controller = 'serviceset';
        } elseif ($controller === 'scheduled_downtime') {
            $controller = 'scheduled-downtime';
        }

        return Link::create(
            $name,
            'director/' . $controller,
            $this->objectLinkParams(),
            ['data-base-target' => '_next']
        );
    }

    /**
     * @return NameValueTable
     * @throws \Icinga\Exception\IcingaException
     */
    public function getInfoTable()
    {
        $entry = $this->entry;
        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Author') => $entry->author,
            $this->translate('Date')   => DateFormatter::formatDateTime(
                $entry->change_time_ts
            ),

        ]);
        if (null === $this->name) {
            $table->addNameValueRow(
                $this->translate('Action'),
                Html::sprintf(
                    '%s %s "%s" %s',
                    $entry->action_name,
                    $entry->object_type,
                    $this->getLinkToObject(),
                    $this->getActionExtraHtml()
                )
            );
        } else {
            $table->addNameValueRow(
                $this->translate('Action'),
                $entry->action_name
            );
        }

        if ($this->hasBeenEnabled()) {
            $table->addNameValueRow(
                $this->translate('Rendering'),
                $this->translate('This object has been enabled')
            );
        } elseif ($this->hasBeenDisabled()) {
            $table->addNameValueRow(
                $this->translate('Rendering'),
                $this->translate('This object has been disabled')
            );
        }

        $table->addNameValueRow(
            $this->translate('Checksum'),
            $entry->checksum
        );
        if ($this->entry->old_properties) {
            $table->addNameValueRow(
                $this->translate('Actions'),
                $this->getRestoreForm()
            );
        }

        return $table;
    }

    public function hasBeenEnabled()
    {
        return false;
    }

    public function hasBeenDisabled()
    {
        return false;
    }

    /**
     * @return string
     * @throws ProgrammingError
     */
    public function getTitle()
    {
        switch ($this->entry->action_name) {
            case 'create':
                $msg = $this->translate('%s "%s" has been created');
                break;
            case 'delete':
                $msg = $this->translate('%s "%s" has been deleted');
                break;
            case 'modify':
                $msg = $this->translate('%s "%s" has been modified');
                break;
            default:
                throw new ProgrammingError(
                    'Unable to deal with "%s" activity',
                    $this->entry->action_name
                );
        }

        return sprintf($msg, $this->typeName, $this->entry->object_name);
    }

    /**
     * @param $type
     * @param $props
     * @return IcingaObject
     * @throws \Icinga\Exception\IcingaException
     */
    protected function createObject($type, $props)
    {
        $props = json_decode($props);
        $newProps = ['object_name' => $props->object_name];
        if (property_exists($props, 'object_type')) {
            $newProps['object_type'] = $props->object_type;
        }

        return IcingaObject::createByType(
            $type,
            $newProps,
            $this->db
        )->setProperties((array) $props);
    }
}
