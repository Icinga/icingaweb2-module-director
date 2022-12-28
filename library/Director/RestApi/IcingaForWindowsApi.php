<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Windows\JobsMenu;
use Icinga\Module\Director\Windows\MainMenu;
use Icinga\Module\Director\Windows\RemoteApi;
use JsonSerializable;

class IcingaForWindowsApi extends RequestHandler
{
    protected function processApiRequest()
    {
        try {
            $this->sendJson($this->handleApiRequest());
        } catch (NotFoundError $e) {
            $this->sendJsonError($e, 404);
            return;
        } catch (DuplicateKeyException $e) {
            $this->sendJsonError($e, 422);
            return;
        } catch (Exception $e) {
            $this->sendJsonError($e);
        }

        if ($this->request->getActionName() !== 'index') {
            throw new NotFoundError('Not found');
        }
    }

    public function handleApiRequest(): JsonSerializable
    {
        $path = $this->request->getUrl()->getPath();
        $prefix = RemoteApi::BASE_URL;
        $length = strlen($prefix);

        if (substr($path, 0, $length) === $prefix) {
            $path = substr($path, $length + 1);
        } else {
            throw new NotFoundError('No such Url');
        }
        switch ($path) {
            case '':
                return new MainMenu();
            case 'jobs':
                return new JobsMenu();
            default:
                throw new NotFoundError('No such Url');
        }
    }
}
