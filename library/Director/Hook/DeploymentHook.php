<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Objects\DirectorDeploymentLog;

abstract class DeploymentHook
{
    /**
     * Please override this method if you want to change the behaviour
     * of the deploy (stop it by throwing an exception for some reason)
     *
     * @param DirectorDeploymentLog $deployment
     */
    public function beforeDeploy(DirectorDeploymentLog $deployment)
    {
    }

    /**
     * Please override this method if you want to trigger custom actions
     * on a successfull dump of the Icinga configuration
     *
     * @param DirectorDeploymentLog $deployment
     */
    public function onSuccessfullDump(DirectorDeploymentLog $deployment)
    {
    }
}
