<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Web\Controller;

class ScreenshotController extends Controller
{
    public function indexAction()
    {
        $subdir = $this->getParam('subdir');
        $file   = $this->getParam('file');
        $valid = '[A-z0-9][A-z0-9_-]*';
        if (!preg_match('/^' . $valid . '$/', $subdir)
           || !preg_match('/^' . $valid . '\.png$/', $file)
        ) {
            throw new NotFoundError('Not found');
        }

        $filename = sprintf(
            '%s/doc/screenshot/director/%s/%s',
            $this->Module()->getBaseDir(),
            $subdir,
            $file
        );

        if (file_exists($filename)) {
            $this->getResponse()->setHeader('Content-Type', 'image/png', true);
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);
            $fh = fopen($filename, 'r');
            fpassthru($fh);
        } else {
            throw new NotFoundError('Not found: ' . $filename);
        }
    }
}
