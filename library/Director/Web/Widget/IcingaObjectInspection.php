<?php

namespace Icinga\Module\Director\Web\Widget;

use dipl\Html\BaseElement;
use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\NameValueTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Table\DbHelper;
use stdClass;

class IcingaObjectInspection extends BaseElement
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

    protected function assemble()
    {
        $attrs = $this->object->attrs;
        if (property_exists($attrs, 'source_location')) {
            $this->renderSourceLocation($attrs->source_location);
        }
        if (property_exists($attrs, 'last_check_result')) {
            $this->renderLastCheckResult($attrs->last_check_result);
        }

        $this->renderObjectAttributes($attrs);
        // $this->add(Html::pre(PlainObjectRenderer::render($this->object)));
    }

    protected function renderLastCheckResult($result)
    {
        $this->add(Html::tag('h2', null, $this->translate('Last Check Result')));
        $this->renderCheckResultDetails($result);
        if (property_exists($result, 'command') && is_array($result->command)) {
            $this->renderExecutedCommand($result->command);
        }
    }

    protected function renderExecutedCommand(array $command)
    {
        $command = implode(' ', array_map('escapeshellarg', $command));
        $this->add([
            Html::tag('h3', null, 'Executed Command'),
            $this->formatConsole($command)
        ]);
    }

    protected function renderCheckResultDetails($result)
    {
    }

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
                DateFormatter::timeAgo(strtotime($deployment->start_time, false)),
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
