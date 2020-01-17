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
     * on a successful dump of the Icinga configuration
     *
     * @param DirectorDeploymentLog $deployment
     */
    public function onSuccessfulDump(DirectorDeploymentLog $deployment)
    {
    }

    /**
     * There is a typo in this method name, do not use this.
     *
     * @deprecated Please use onSuccessfulDump
     * @param DirectorDeploymentLog $deployment
     */
    public function onSuccessfullDump(DirectorDeploymentLog $deployment)
    {
    }

    /**
     * Compatibility helper
     *
     * The initial version of this hook had a typo in the onSuccessfulDump method
     * That's why we call this hook, which then calls both the correct and the
     * erroneous method to make sure that we do not break existing implementations.
     *
     * @param DirectorDeploymentLog $deploymentLog
     */
    final public function triggerSuccessfulDump(DirectorDeploymentLog $deploymentLog)
    {
        $this->onSuccessfulDump($deploymentLog);
        $this->onSuccessfullDump($deploymentLog);
    }
}
