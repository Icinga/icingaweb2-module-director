<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use gipfl\Json\JsonString;
use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Web\Form\Windows\JobExecutionForm;
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
    }

    public function handleApiRequest(): JsonSerializable
    {
        $url = $this->request->getUrl();
        $path = $this->stripBaseUrl($url->getPath());
        switch ($path) {
            case '':
                return new MainMenu();
            case 'jobs':
                return new JobsMenu();
            case 'job':
                $form = new JobExecutionForm();
                $form->setAction($url->getPath());
                $serverRequest = ServerRequest::fromGlobals(); // TODO: Not here
                if ($this->request->isApiRequest()) {
                    $body = (string) $serverRequest->getBody();
                    if (strlen($body) > 0) {
                        $serverRequest = $serverRequest->withParsedBody(JsonString::decode($body));
                    }
                    $form->handleRequest($serverRequest);
                } else {
                    $form->handleRequest($serverRequest);
                }
                return $form;
            default:
                throw new NotFoundError('No such Url');
        }
    }

    protected function stripBaseUrl(string $path): string
    {
        $prefix = RemoteApi::BASE_URL;
        $length = strlen($prefix);

        if (substr($path, 0, $length) === $prefix) {
            return substr($path, $length + 1);
        } else {
            throw new NotFoundError('No such Url');
        }
    }
}
