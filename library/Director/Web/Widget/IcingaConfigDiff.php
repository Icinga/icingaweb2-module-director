<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\ValidHtml;

class IcingaConfigDiff extends HtmlDocument
{
    public function __construct(IcingaConfig $left, IcingaConfig $right)
    {
        foreach (static::getDiffs($left, $right) as $filename => $diff) {
            $this->add([
                Html::tag('h3', $filename),
                $diff
            ]);
        }
    }

    /**
     * @param IcingaConfig $oldConfig
     * @param IcingaConfig $newConfig
     * @return ValidHtml[]
     */
    public static function getDiffs(IcingaConfig $oldConfig, IcingaConfig $newConfig)
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

            $diffs[$filename] = new SideBySideDiff(new PhpDiff($left, $right));
        }

        return $diffs;
    }
}
