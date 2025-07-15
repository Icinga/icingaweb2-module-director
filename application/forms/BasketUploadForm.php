<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Notification;

class BasketUploadForm extends DirectorObjectForm
{
    protected $listUrl = 'director/baskets';

    protected $failed;

    protected $upload;

    protected $rawUpload;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        if ($this->object === null) {
            $this->addElement('text', 'basket_name', [
                'label'        => $this->translate('Basket Name'),
                'required'     => true,
            ]);
        }
        $this->setAttrib('enctype', 'multipart/form-data');

        $this->addElement('file', 'uploaded_file', [
            'label'       => $this->translate('Choose file'),
            'destination' => $this->getTempDir(),
            'valueDisabled' => true,
            'isArray'       => false,
            'multiple'      => false,
            'ignore'        => true,
        ]);

        $this->setSubmitLabel($this->translate('Upload'));
    }

    protected function getTempDir()
    {
        return sys_get_temp_dir();
    }

    protected function getObjectClassname()
    {
        return '\\Icinga\\Module\\Director\\DirectorObject\\Automation\\Basket';
    }

    /**
     * @return bool
     * @throws IcingaException
     */
    protected function processUploadedSource()
    {
        if (! array_key_exists('uploaded_file', $_FILES)) {
            throw new IcingaException('Got no file');
        }

        if (
            ! isset($_FILES['uploaded_file']['tmp_name'])
            || ! is_uploaded_file($_FILES['uploaded_file']['tmp_name'])
        ) {
            $this->addError('Got no uploaded file');
            $this->failed = true;

            return false;
        }
        $tmpFile = $_FILES['uploaded_file']['tmp_name'];
        $originalFilename = $_FILES['uploaded_file']['name'];

        $source = file_get_contents($tmpFile);
        unlink($tmpFile);
        try {
            $json = Json::decode($source);
            $this->rawUpload = $source;
            $this->upload = $json;
        } catch (Exception $e) {
            $this->addError($originalFilename . ' failed: ' . $e->getMessage());
            Notification::error($originalFilename . ' failed: ' . $e->getMessage());
            $this->failed = true;

            return false;
        }

        return true;
    }

    public function onRequest()
    {
        if ($this->hasBeenSent()) {
            try {
                $this->processUploadedSource();
            } catch (Exception $e) {
                $this->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        /** @var Basket $basket */
        $basket = $this->object();

        foreach ($this->upload as $type => $content) {
            if ($type !== 'Datafield') {
                $basket->addObjects($type, array_keys((array) $content));
            }
        }
        if ($basket->isEmpty()) {
            $this->addError($this->translate("It's not allowed to store an empty basket"));

            return;
        }

        $basket->set('owner_type', 'user');
        $basket->set('owner_value', $this->getAuth()->getUser()->getUsername());
        if ($basket->hasBeenLoadedFromDb()) {
            $this->setSuccessUrl('director/basket/snapshots', ['name' => $basket->get('basket_name')]);
        } else {
            $this->setSuccessUrl('director/basket', ['name' => $basket->get('basket_name')]);
            $basket->store($this->db);
        }

        BasketSnapshot::forBasketFromJson(
            $basket,
            $this->rawUpload
        )->store($this->db);
        $this->beforeSuccessfulRedirect();
        $this->redirectOnSuccess($this->translate('Basket has been uploaded'));
    }
}
