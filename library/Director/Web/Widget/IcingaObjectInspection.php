<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Table\DbHelper;
use stdClass;

class IcingaObjectInspection extends BaseHtmlElement
{
    use DbHelper;
    use TranslationHelper;

    protected $tag = 'div';

    /** @var Db */
    protected $db;

    /** @var stdClass */
    protected $object;

    public function __construct(stdClass $object, Db $db)
    {
        $this->object = $object;
        $this->db = $db;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $attrs = $this->object->attrs;
        if (isset($attrs->source_location)) {
            $this->renderSourceLocation($attrs->source_location);
        }
        if (isset($attrs->last_check_result)) {
            $this->renderLastCheckResult($attrs->last_check_result);
        }

        $this->renderObjectAttributes($attrs);
        // $this->add(Html::tag('pre', null, PlainObjectRenderer::render($this->object)));
    }

    /**
     * @param $result
     * @throws \Icinga\Exception\IcingaException
     */
    protected function renderLastCheckResult($result)
    {
        $this->add(Html::tag('h2', null, $this->translate('Last Check Result')));
        $this->renderCheckResultDetails($result);
        if (property_exists($result, 'command')) {
            $this->renderExecutedCommand($result->command);
        }
    }

    /**
     * @param array|string $command
     *
     * @throws \Icinga\Exception\IcingaException
     */
    protected function renderExecutedCommand($command)
    {
        if (is_array($command)) {
            $command = implode(' ', array_map('escapeshellarg', $command));
        }
        $this->add([
            Html::tag('h3', null, 'Executed Command'),
            $this->formatConsole($command)
        ]);
    }

    protected function renderCheckResultDetails($result)
    {
    }

    /**
     * @param $attrs
     * @throws \Icinga\Exception\IcingaException
     */
    protected function renderObjectAttributes($attrs)
    {
        $blacklist = [
            'last_check_result',
            'source_location',
            'templates',
        ];

        $linked = [
            'check_command',
            'groups',
        ];

        $info = new NameValueTable();
        foreach ($attrs as $key => $value) {
            if (in_array($key, $blacklist)) {
                continue;
            }
            if ($key === 'groups') {
                $info->addNameValueRow($key, $this->linkGroups($value));
            } elseif (in_array($key, $linked)) {
                $info->addNameValueRow($key, $this->renderLinkedObject($key, $value));
            } else {
                $info->addNameValueRow($key, PlainObjectRenderer::render($value));
            }
        }

        $this->add([
            Html::tag('h2', null, 'Object Properties'),
            $info
        ]);
    }

    /**
     * @param $key
     * @param $objectName
     * @return Link|Link[]
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function renderLinkedObject($key, $objectName)
    {
        $keys = [
            'check_command'        => ['CheckCommand', 'CheckCommands'],
            'event_command'        => ['EventCommand', 'EventCommands'],
            'notification_command' => ['NotificationCommand', 'NotificationCommands'],
        ];
        $type = $keys[$key];

        if ($key === 'groups') {
            return $this->linkGroups($objectName);
        } else {
            $singular = $type[0];
            $plural   = $type[1];

            return Link::create($objectName, 'director/inspect/object', [
                'type'   => $singular,
                'plural' => $plural,
                'name'   => $objectName
            ]);
        }
    }

    /**
     * @param $groups
     * @return Link[]
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function linkGroups($groups)
    {
        if ($groups === null) {
            return [];
        }

        $singular = $this->object->type . 'Group';
        $plural = $singular . "s";

        $links = [];

        foreach ($groups as $name) {
            $links[] = Link::create($name, 'director/inspect/object', [
                'type'   => $singular,
                'plural' => $plural,
                'name'   => $name
            ]);
        }

        return $links;
    }

    /**
     * @param stdClass $source
     * @throws \Icinga\Exception\IcingaException
     */
    protected function renderSourceLocation(stdClass $source)
    {
        $findRelative = 'api/packages/director';
        $this->add(Html::tag('h2')->add('Source Location'));
        $pos = strpos($source->path, $findRelative);

        if (false === $pos) {
            $this->add(Html::tag('p', null, Html::sprintf(
                'The configuration for this object has not been rendered by'
                . ' Icinga Director. You can find it on line %s in %s.',
                Html::tag('strong', null, $source->first_line),
                Html::tag('strong', null, $source->path)
            )));
        } else {
            $relativePath = substr($source->path, $pos + strlen($findRelative) + 1);
            $parts = explode('/', $relativePath);
            $stageName = array_shift($parts);
            $relativePath = implode('/', $parts);
            $source->director_relative = $relativePath;
            $deployment = $this->loadDeploymentForStage($stageName);

            $this->add(Html::tag('p')->add(Html::sprintf(
                'The configuration for this object has been rendered by Icinga'
                . ' Director %s to %s',
                DateFormatter::timeAgo(strtotime($deployment->start_time)),
                $this->linkToSourceLocation($deployment, $source)
            )));
        }
    }

    protected function loadDeploymentForStage($stageName)
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            ['dl' => 'director_deployment_log'],
            ['id', 'start_time', 'config_checksum']
        )->where('stage_name = ?', $stageName)->order('id DESC')->limit(1);

        return $db->fetchRow($query);
    }

    /**
     * @param $deployment
     * @param $source
     * @return Link
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function linkToSourceLocation($deployment, $source)
    {
        $filename = $source->director_relative;

        return Link::create(
            sprintf('%s:%s', $filename, $source->first_line),
            'director/config/file',
            [
                'config_checksum'   => $this->getChecksum($deployment->config_checksum),
                'deployment_id'     => $deployment->id,
                'backTo'            => 'deployment',
                'file_path'         => $filename,
                'highlight'         => $source->first_line,
                'highlightSeverity' => 'ok'
            ]
        );
    }

    protected function formatConsole($output)
    {
        return Html::tag('pre', ['class' => 'logfile'], $output);
    }
}
