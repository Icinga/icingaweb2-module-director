<?php

namespace Icinga\Module\Director\Web\Form\Element;

use gipfl\IcingaWeb2\Link;
use ipl\Html\Html;

/**
 * Used by the
 */
class InstanceSummary extends FormElement
{
    public $helper = 'formSimpleNote';

    /**
     * Always ignore this element
     * @codingStandardsIgnoreStart
     *
     * @var boolean
     */
    protected $_ignore = true;
    // @codingStandardsIgnoreEnd

    private $instances;

    /** @var array will be set via options */
    protected $linkParams;

    public function setValue($value)
    {
        $this->instances = $value;
        return $this;
    }

    public function getValue()
    {
        return Html::tag('span', [
            Html::tag('italic', 'empty'),
            ' ',
            Link::create('Manage Instances', 'director/data/dictionary', $this->linkParams, [
                'data-base-target' => '_next',
                'class' => 'icon-forward'
            ])
        ]);
    }

    public function isValid($value, $context = null)
    {
        return true;
    }
}
