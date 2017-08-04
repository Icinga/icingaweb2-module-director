<?php

namespace Icinga\Module\Director;

use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use ipl\Html\Link;
use ipl\Html\Util as iplUtil;
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
        $log = iplUtil::escapeForHtml($deployment->get('startup_log'));
        $lines = array();
        $severity = 'information';
        $sevPattern = '/^(debug|notice|information|warning|critical)\/(\w+)/';
        $filePatternHint = '~(/[\w/]+/api/packages/director/[^/]+/)([^:]+\.conf)(: (\d+))~';
        $filePatternDetail = '~(/[\w/]+/api/packages/director/[^/]+/)([^:]+\.conf)(\((\d+)\))~';
        $markPattern = null;
        // len [stage] + 1
        $markReplace = '        ^';

        foreach (preg_split('/\n/', $log) as $line) {
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

            $lines[] .= $line;
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
