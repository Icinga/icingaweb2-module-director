<?php

namespace Icinga\Module\Director\Web\Form;

use gipfl\IcingaWeb2\Url;
use Icinga\Web\Response;
use ipl\Html\Form;
use Icinga\Web\Window;

class DbSelectorForm extends Form
{
    protected $defaultAttributes = [
        'class' => 'db-selector'
    ];

    protected $allowedNames;

    /** @var Window */
    protected $window;

    protected $response;

    public function __construct(Response $response, Window $window, $allowedNames)
    {
        $this->response = $response;
        $this->window = $window;
        $this->allowedNames = $allowedNames;
    }

    protected function assemble()
    {
        $this->addElement('hidden', 'DbSelector', [
            'value' => 'sent'
        ]);
        $this->addElement('select', 'db_resource', [
            'options' => $this->allowedNames,
            'class'   => 'autosubmit',
            'value'   => $this->getSession()->get('db_resource')
        ]);
    }

    /**
     * A base class should handle this, based on hidden fields
     *
     * @return bool
     */
    public function hasBeenSubmitted()
    {
        return $this->hasBeenSent() && $this->getRequestParam('DbSelector') === 'sent';
    }

    public function onSuccess()
    {
        $this->getSession()->set('db_resource', $this->getElement('db_resource')->getValue());
        $this->response->redirectAndExit(Url::fromRequest($this->getRequest()));
    }

    protected function getRequestParam($name, $default = null)
    {
        $request = $this->getRequest();
        if ($request === null) {
            return $default;
        }
        if ($request->getMethod() === 'POST') {
            $params = $request->getParsedBody();
        } elseif ($this->getMethod() === 'GET') {
            parse_str($request->getUri()->getQuery(), $params);
        } else {
            $params = [];
        }

        if (is_array($params) && array_key_exists($name, $params)) {
            return $params[$name];
        }

        return $default;
    }
    /**
     * @return \Icinga\Web\Session\SessionNamespace
     */
    protected function getSession()
    {
        return $this->window->getSessionNamespace('director');
    }
}
