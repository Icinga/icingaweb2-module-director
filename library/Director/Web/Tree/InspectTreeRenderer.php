<?php

namespace Icinga\Module\Director\Web\Tree;

use Icinga\Module\Director\Objects\IcingaEndpoint;
use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Translation\TranslationHelper;

class InspectTreeRenderer extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'ul';

    protected $defaultAttributes = [
        'class'            => 'tree',
        'data-base-target' => '_next',
    ];

    protected $tree;

    /** @var IcingaEndpoint */
    protected $endpoint;

    public function __construct(IcingaEndpoint $endpoint)
    {
        $this->endpoint = $endpoint;
    }

    protected function getNodes()
    {
        $rootNodes = array();
        $types = $this->endpoint->api()->getTypes();
        foreach ($types as $name => $type) {
            if (property_exists($type, 'base')) {
                $base = $type->base;
                if (! property_exists($types[$base], 'children')) {
                    $types[$base]->children = array();
                }

                $types[$base]->children[$name] = $type;
            } else {
                $rootNodes[$name] = $type;
            }
        }

        return $rootNodes;
    }

    public function assemble()
    {
        $this->add($this->renderNodes($this->getNodes()));
    }

    protected function renderNodes($nodes, $showLinks = false, $level = 0)
    {
        $result = [];
        foreach ($nodes as $child) {
            $result[] = $this->renderNode($child, $showLinks, $level + 1);
        }

        if ($level === 0) {
            return $result;
        } else {
            return Html::tag('ul', null, $result);
        }
    }

    protected function renderNode($node, $forceLinks = false, $level = 0)
    {
        $name = $node->name;
        $showLinks = $forceLinks || $name === 'ConfigObject';
        $hasChildren = property_exists($node, 'children');
        $li = Html::tag('li');
        if (! $hasChildren) {
            $li->getAttributes()->add('class', 'collapsed');
        }

        if ($hasChildren) {
            $li->add(Html::tag('span', ['class' => 'handle']));
        }

        $class = $node->abstract ? 'icon-sitemap' : 'icon-doc-text';
        $li->add(Link::create($name, 'director/inspect/type', [
            'endpoint' => $this->endpoint->getObjectName(),
            'type'     => $name
        ], ['class' => $class]));

        if ($hasChildren) {
            $li->add($this->renderNodes($node->children, $showLinks, $level + 1));
        }

        return $li;
    }
}
