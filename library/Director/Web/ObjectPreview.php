<?php

namespace Icinga\Module\Director\Web;

use gipfl\Web\Widget\Hint;
use ipl\Html\Text;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Request;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\ControlsAndContent;

class ObjectPreview
{
    use TranslationHelper;

    /** @var IcingaObject */
    protected $object;

    /** @var Request */
    protected $request;

    public function __construct(IcingaObject $object, Request $request)
    {
        $this->object = $object;
        $this->request = $request;
    }

    /**
     * @param ControlsAndContent $cc
     * @throws \Icinga\Exception\NotFoundError
     */
    public function renderTo(ControlsAndContent $cc)
    {
        $object = $this->object;
        $url = $this->request->getUrl();
        $params = $url->getParams();
        $cc->addTitle(
            $this->translate('Config preview: %s'),
            $object->getObjectName()
        );

        if ($params->shift('resolved')) {
            $object = $object::fromPlainObject(
                $object->toPlainObject(true),
                $object->getConnection()
            );

            $cc->actions()->add(Link::create(
                $this->translate('Show normal'),
                $url->without('resolved'),
                null,
                ['class' => 'icon-resize-small state-warning']
            ));
        } else {
            try {
                if ($object->supportsImports() && $object->imports()->count() > 0) {
                    $cc->actions()->add(Link::create(
                        $this->translate('Show resolved'),
                        $url->with('resolved', true),
                        null,
                        ['class' => 'icon-resize-full']
                    ));
                }
            } catch (NestingError $e) {
                // No resolve link with nesting errors
            }
        }

        $content = $cc->content();
        if ($object->isDisabled()) {
            $content->add(Hint::error(
                $this->translate('This object will not be deployed as it has been disabled')
            ));
        }
        if ($object->isExternal()) {
            $content->add(Html::tag('p', null, $this->translate((
                'This is an external object. It has been imported from Icinga 2 through the'
                . ' Core API and cannot be managed with the Icinga Director. It is however'
                . ' perfectly valid to create objects using this or referring to this object.'
                . ' You might also want to define related Fields to make work based on this'
                . ' object more enjoyable.'
            ))));
        }
        $config = $object->toSingleIcingaConfig();

        foreach ($config->getFiles() as $filename => $file) {
            if (! $object->isExternal()) {
                $content->add(Html::tag('h2', null, $filename));
            }

            $classes = array();
            if ($object->isDisabled()) {
                $classes[] = 'disabled';
            } elseif ($object->isExternal()) {
                $classes[] = 'logfile';
            }

            $type = $object->getShortTableName();

            $plain = Html::wantHtml($file->getContent())->render();
            $plain = preg_replace_callback(
                '/^(\s+import\s+\&quot\;)(.+)(\&quot\;)/m',
                [$this, 'linkImport'],
                $plain
            );

            if ($type !== 'command') {
                $plain = preg_replace_callback(
                    '/^(\s+(?:check_|event_)?command\s+=\s+\&quot\;)(.+)(\&quot\;)/m',
                    [$this, 'linkCommand'],
                    $plain
                );
            }

            $plain = preg_replace_callback(
                '/^(\s+host_name\s+=\s+\&quot\;)(.+)(\&quot\;)/m',
                [$this, 'linkHost'],
                $plain
            );
            $text = Text::create($plain)->setEscaped();

            $content->add(Html::tag('pre', ['class' => $classes], $text));
        }
    }

    /**
     * @api internal
     * @param $match
     * @return string
     */
    public function linkImport($match)
    {
        $blacklist = [
            'plugin-notification-command',
            'plugin-check-command',
        ];
        if (in_array($match[2], $blacklist)) {
            return $match[1] . $match[2] . $match[3];
        }

        return $match[1] . Link::create(
            $match[2],
            sprintf('director/' . $this->object->getShortTableName()),
            ['name' => $match[2]]
        )->render() . $match[3];
    }

    /**
     * @api internal
     * @param $match
     * @return string
     */
    public function linkCommand($match)
    {
        return $match[1] . Link::create(
            $match[2],
            sprintf('director/command'),
            ['name' => $match[2]]
        )->render() . $match[3];
    }

    /**
     * @api internal
     * @param $match
     * @return string
     */
    public function linkHost($match)
    {
        return $match[1] . Link::create(
            $match[2],
            sprintf('director/host'),
            ['name' => $match[2]]
        )->render() . $match[3];
    }
}
