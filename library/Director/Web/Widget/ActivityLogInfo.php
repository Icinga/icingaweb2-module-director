<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\RestoreObjectForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use ipl\Html\Container;
use ipl\Html\Html;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Url;
use ipl\Web\Widget\NameValueTable;
use ipl\Web\Widget\Tabs;

class ActivityLogInfo extends Html
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
     * @return Container
     */
    public function getPagination(Url $url)
    {
        /** @var Url $url */
        $url = $url->without('checksum')->without('show');
        $div = Container::create([
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

    public function showTab($tabName)
    {
        if ($tabName === null) {
            $tabName = $this->defaultTab;
        }

        $this->getTabs()->activate($tabName);
        $this->add($this->getInfoTable());
        if ($tabName === 'old') {
            // $title = sprintf('%s former config', $this->entry->object_name);
            $diffs = $this->getConfigDiffs($this->oldConfig(), $this->emptyConfig());
        } elseif ($tabName === 'new') {
            // $title = sprintf('%s new config', $this->entry->object_name);
            $diffs = $this->getConfigDiffs($this->emptyConfig(), $this->newConfig());
        } else {
            $diffs = $this->getConfigDiffs($this->oldConfig(), $this->newConfig());
        }

        $this->addDiffs($diffs);

        return $this;
    }

    protected function emptyConfig()
    {
        return new IcingaConfig($this->db);
    }

    protected function addDiffs($diffs)
    {
        foreach ($diffs as $file => $diff) {
            $this->add(Html::h3($file))->add($diff);
        }
    }

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
     */
    public function objectStillExists()
    {
        return IcingaObject::existsByType(
            $this->type,
            $this->objectKey(),
            $this->db
        );
    }

    protected function objectKey()
    {
        $entry = $this->entry;
        if ($entry->object_type === 'icinga_service' || $entry->object_type === 'icinga_service_set') {
            // TODO: this is not correct. Activity needs to get (multi) key support
            return ['name' => $entry->object_name];
        }

        return $entry->object_name;
    }

    public function getTabs(Url $url = null)
    {
        if ($this->tabs === null) {
            $this->tabs = $this->createTabs($url);
        }

        return $this->tabs;
    }

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
     * @param IcingaConfig $oldConfig
     * @param IcingaConfig $newConfig
     * @return ConfigDiff[]
     */
    protected function getConfigDiffs(IcingaConfig $oldConfig, IcingaConfig $newConfig)
    {
        $oldFileNames = $oldConfig->getFileNames();
        $newFileNames = $newConfig->getFileNames();

        $fileNames = array_merge($oldFileNames, $newFileNames);

        $diffs = [];
        foreach ($fileNames as $filename) {
            if (in_array($filename, $oldFileNames)) {
                $left = $oldConfig->getFile($filename)->getContent();
            } else {
                $left = '';
            }

            if (in_array($filename, $newFileNames)) {
                $right = $newConfig->getFile($filename)->getContent();
            } else {
                $right = '';
            }
            if ($left === $right) {
                continue;
            }

            $diffs[$filename] = ConfigDiff::create($left, $right);
        }

        return $diffs;
    }

    /**
     * @return IcingaObject
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
     */
    protected function newObject()
    {
        return $this->createObject(
            $this->entry->object_type,
            $this->entry->new_properties
        );
    }

    /**
     * @return IcingaConfig
     */
    protected function newConfig()
    {
        return $this->newObject()->toSingleIcingaConfig();
    }

    /**
     * @return IcingaConfig
     */
    protected function oldConfig()
    {
        return $this->oldObject()->toSingleIcingaConfig();
    }

    protected function getLinkToObject()
    {
        $entry = $this->entry;
        $name = $entry->object_name;
        return Link::create(
            $name,
            'director/' . preg_replace('/^icinga_/', '', $entry->object_type),
            ['name' => $name],
            ['data-base-target' => '_next']
        );
    }

    public function getInfoTable()
    {
        $entry = $this->entry;
        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Author') => $entry->author,
            $this->translate('Date')   => $entry->change_time,

        ]);
        if (null === $this->name) {
            $table->addNameValueRow(
                $this->translate('Action'),
                Html::sprintf(
                    '%s %s "%s"',
                    $entry->action_name,
                    $entry->object_type,
                    $this->getLinkToObject()
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
