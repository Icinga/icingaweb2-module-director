<?php

namespace Icinga\Module\Director;

use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Web\View;

class StartupLogRenderer
{
    public static function beautify(DirectorDeploymentLog $deploymentLog, View $view)
    {
        $log = $view->escape($deploymentLog->get('startup_log'));
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

            if (preg_match($filePatternHint, $line, $m)) {
                $line = preg_replace_callback(
                    $filePatternHint,
                    function ($matches) use ($severity, $view, $deploymentLog) {
                        return StartupLogRenderer::logLink($matches, $severity, $deploymentLog, $view);
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
                    function ($matches) use ($severity, $view, $deploymentLog) {
                        return StartupLogRenderer::logLink($matches, $severity, $deploymentLog, $view);
                    },
                    $line
                );
            }

            $lines[] .= $line;
        }
        return implode("\n", $lines);
    }

    public static function logLink($match, $severity, DirectorDeploymentLog $deploymentLog, View $view)
    {
        $stageDir = $match[1];
        $filename = $match[2];
        $suffix = $match[3];
        if (preg_match('/(\d+).*/', $suffix, $m)) {
            $lineNumber = $m[1];
        } else {
            $lineNumber = null;
        }

        $params = array(
            'config_checksum' => $deploymentLog->getConfigHexChecksum(),
            'deployment_id'   => $deploymentLog->get('id'),
            'file_path'       => $filename,
            'fileOnly'        => true,
        );
        if ($lineNumber !== null) {
            $params['highlight'] = $lineNumber;
            $params['highlightSeverity'] = $severity;
        }

        return $view->qlink(
            '[stage]/' . $filename,
            'director/config/file',
            $params,
            array(
                'data-base-target' => '_next',
                'title' => $stageDir . $filename
            )
        ) . $suffix;
    }
}