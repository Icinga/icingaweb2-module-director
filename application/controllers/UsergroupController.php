<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Forms\IcingaDeleteUsergroupForm;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Web\Notification;
use ipl\Web\Url;

class UsergroupController extends ObjectController
{
    public function deleteAction()
    {
        $this->addTitle($this->translate('Delete Usergroup'));

        $directMemberQuery = $this->db()
                      ->select()
                      ->from('icinga_usergroup_user')
                      ->where('usergroup_id', $this->object->get('id'));

        $appliedMemberQuery = $this->db()
                                  ->select()
                                  ->from('icinga_usergroup_user_resolved')
                                  ->where('usergroup_id', $this->object->get('id'));

        if ($this->db()->count($directMemberQuery) > 0 || $this->db()->count($appliedMemberQuery) > 0) {
            $this->content()->add(
                Hint::info(sprintf(
                    $this->translate('The usergroup "%s" has members. Do you still want to delete it?'),
                    $this->object->getObjectName()
                ))
            );
        } else {
            $this->content()->add(
                Hint::info(sprintf(
                    $this->translate('The usergroup "%s" does not have any members. You can go ahead and delete it.'),
                    $this->object->getObjectName()
                ))
            );
        }

        $this->content()->add(
            (new IcingaDeleteUsergroupForm($this->object, $this->db(), $this->branch))
                ->on(IcingaDeleteUsergroupForm::ON_SUCCESS, function () {
                    Notification::success(sprintf(
                        $this->translate('User group %s has been deleted.'),
                        $this->object->getObjectName()
                    ));

                    $this->redirectNow(Url::fromPath('director/usergroups'));
                })
                ->handleRequest($this->getServerRequest())
        );
    }
}
