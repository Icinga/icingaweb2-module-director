<?php

namespace Icinga\Module\Director;

use ipl\Html\Html;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use gipfl\IcingaWeb2\Link;
use ipl\Html\ValidHtml;

class StartupLogRenderer implements ValidHtml
{
    /** @var DirectorDeploymentLog */
    protected $deployment;

    public function __construct(DirectorDeploymentLog $deployment)
    {
        $this->deployment = $deployment;
    }

    public function render()
    {
        $deployment = $this->deployment;
        $log = Html::escape($deployment->get('startup_log'));
        $lines = array();
        $severity = 'information';
        $sevPattern = '/^(debug|notice|information|warning|critical)\/(\w+)/';
        $settings = new Settings($this->deployment->getConnection());
        $package = $settings->get('icinga_package_name');
        $pathPattern = '~(/[\w/]+/api/packages/' . $package . '/[^/]+/)';
        $filePatternHint = $pathPattern . '([^:]+\.conf)(: (\d+))~';
        $filePatternDetail = $pathPattern . '([^:]+\.conf)(\((\d+)\))~';
        $markPattern = null;
        // len [stage] + 1
        $markReplace = '        ^';

        /** @var string[] $logLines */
        $logLines = preg_split('/\n/', $log);
        foreach ($logLines as $line) {
            if (preg_match('/^\[([\d\s\:\+\-]+)\]\s/', $line, $m)) {
                $time = $m[1];
                // TODO: we might use new DateTime($time) and show a special "timeAgo"
                //       format - but for now this should suffice.
                $line = substr($line, strpos($line, ']') + 2);
            } else {
                $time = null;
            }

            if (preg_match($sevPattern, $line, $m)) {
                $severity = $m[1];
                $line = preg_replace(
                    $sevPattern,
                    '<span class="loglevel \1">\1</span>/<span class="application">\2</span>',
                    $line
                );
            }

            if ($markPattern !== null) {
                $line = preg_replace($markPattern, $markReplace, $line);
            }
            $line = preg_replace('/([\^]{2,})/', '<span class="error-hint">\1</span>', $line);
            $markPattern = null;

            $self = $this;
            if (preg_match($filePatternHint, $line, $m)) {
                $line = preg_replace_callback(
                    $filePatternHint,
                    function ($matches) use ($severity, $self) {
                        return $self->logLink($matches, $severity);
                    },
                    $line
                );
                $line = preg_replace('/\(in/', "\n  (in", $line);
                $line = preg_replace('/\), new declaration/', "),\n  new declaration", $line);
            } elseif (preg_match($filePatternDetail, $line, $m)) {
                $markIndent = strlen($m[1]);
                $markPattern = '/\s{' . $markIndent . '}\^/';

                $line = preg_replace_callback(
                    $filePatternDetail,
                    function ($matches) use ($severity, $self) {
                        return $self->logLink($matches, $severity);
                    },
                    $line
                );
            }

            if ($time === null) {
                $lines[] = $line;
            } else {
                $lines[] = "[$time] $line";
            }
        }
        return implode("\n", $lines);
    }

    protected function logLink($match, $severity)
    {
        $stageDir = $match[1];
        $filename = $match[2];
        $suffix = $match[3];
        if (preg_match('/(\d+).*/', $suffix, $m)) {
            $lineNumber = $m[1];
        } else {
            $lineNumber = null;
        }

        $deployment = $this->deployment;
        $params = array(
            'config_checksum' => $deployment->getConfigHexChecksum(),
            'deployment_id'   => $deployment->get('id'),
            'file_path'       => $filename,
            'backTo'          => 'deployment'
        );
        if ($lineNumber !== null) {
            $params['highlight'] = $lineNumber;
            $params['highlightSeverity'] = $severity;
        }

        return Link::create(
            '[stage]/' . $filename,
            'director/config/file',
            $params,
            [
                'title' => $stageDir . $filename
            ]
        ) . $suffix;
    }
}
