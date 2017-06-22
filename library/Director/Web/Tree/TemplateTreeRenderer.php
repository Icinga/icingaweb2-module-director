<?php

namespace Icinga\Module\Director\Web\Tree;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Resolver\TemplateTree;
use ipl\Html\BaseElement;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Web\Component\ControlsAndContent;

class TemplateTreeRenderer extends BaseElement
{
    protected $tag = 'ul';

    protected $defaultAttributes = [
        'class'            => 'tree',
        'data-base-target' => '_next',
    ];

    protected $tree;

    public function __construct(TemplateTree $tree)
    {
        $this->tree = $tree;
    }

    public static function showType($type, ControlsAndContent $controller, Db $db)
    {
        $controller->content()->add(
            new static(new TemplateTree($type, $db))
        );
    }

    public function renderContent()
    {
        $this->add(
            $this->dumpTree(
                array(
                    'name' => 'Templates',
                    'children' => $this->tree->getTree()
                )
            )
        );

        return parent::renderContent();
    }

    protected function dumpTree($tree, $level = 0)
    {
        $hasChildren = ! empty($tree['children']);
        $type = $this->tree->getType();

        $li = Html::tag('li');
        if (! $hasChildren) {
            $li->attributes()->add('class', 'collapsed');
        }

        if ($hasChildren) {
            $li->add(Html::tag('span', ['class' => 'handle']));
        }

        if ($level === 0) {
            $li->add(Html::tag('a', [
                'name'  => 'Templates',
                'class' => 'icon-globe'
            ], $tree['name']));
        } else {
            $li->add(Link::create(
                $tree['name'],
                "director/${type}template/usage",
                array('name' => $tree['name']),
                array('class' => 'icon-' .$type)
            ));
        }

        if ($hasChildren) {
            $li->add(
                $ul = Html::tag('ul')
            );
            foreach ($tree['children'] as $child) {
                $ul->add($this->dumpTree($child, $level + 1));
            }
        }

        return $li;
    }
}
