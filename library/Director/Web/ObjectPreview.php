<?php

namespace Icinga\Module\Director\Web;

use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Request;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\ControlsAndContent;

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
            $content->add(Html::p(
                ['class' => 'error'],
                $this->translate('This object will not be deployed as it has been disabled')
            ));
        }
        if ($object->isExternal()) {
            $content->add(Html::p($this->translate((
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
                $content->add(Html::h2($filename));
            }

            $classes = array();
            if ($object->isDisabled()) {
                $classes[] = 'disabled';
            } elseif ($object->isExternal()) {
                $classes[] = 'logfile';
            }

            $content->add(Html::pre(['class' => $classes], $file->getContent()));
        }
    }
}
