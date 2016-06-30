function Icinga2AgentModule {
    #
    # Setup parameters which can be accessed
    # with -<ParamName>
    #
    param(
        # Agent setup
        [string]$AgentName,
        [string]$Ticket,
        [string]$InstallAgentVersion,

        # Agent configuration
        [string]$ParentZone,
        [bool]$AcceptConfig               = $TRUE,
        [array]$ParentEndpoints,

        # Agent installation / update
        [string]$DownloadUrl              = 'https://packages.icinga.org/windows/',
        [bool]$AllowUpdates               = $FALSE,
        [array]$InstallerHashes,
        [bool]$FlushApiDirectory          = $FALSE,

        # Agent signing
        [string]$CAServer,
        [int]$CAPort                      = 5665,
        [bool]$ForceCertificateGeneration = $FALSE,

        #Internal handling
        [bool]$DebugMode                  = $FALSE
    )

    #
    # Initialise our installer object
    # and generate our config objects
    #
    $installer = New-Object -TypeName PSObject;
    $installer | Add-Member -membertype NoteProperty -name 'properties' -value @{}
    $installer | Add-Member -membertype NoteProperty -name 'cfg' -value @{
        agent_name       = $AgentName;
        ticket           = $Ticket;
        agent_version    = $InstallAgentVersion;
        parent_zone      = $ParentZone;
        accept_config    = $AcceptConfig;
        endpoints        = $ParentEndpoints;
        download_url     = $DownloadUrl;
        allow_updates    = $AllowUpdates;
        installer_hashes = $InstallerHashes;
        flush_api_dir    = $FlushApiDirectory;
        ca_server        = $CAServer;
        ca_port          = $CAPort;
        force_cert       = $ForceCertificateGeneration;
        debug_mode       = $DebugMode;
    }

    #
    # Access default script config parameters
    # by using this function. These variables
    # are set during the initial call of
    # the script with the parameters
    #
    $installer | Add-Member -membertype ScriptMethod -name 'config' -value {
        param([string] $key)
        return $this.cfg[$key]
    }

    #
    # Convert a boolean value $TRUE $FALSE
    # to a string value
    #
    $installer | Add-Member -membertype ScriptMethod -name 'convertBoolToString' -value {
        param([bool]$key)
        if ($key) {
            return 'true';
        }
        return 'false';
    }

    #
    # Global variables can be accessed
    # by using this function. Example:
    # $this.getProperty('agent_version)
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getProperty' -value {
        param([string] $key)

        # Initialse some variables first 
        # will only be called once
        if (-Not $this.properties.Get_Item('initialized')) {
            $this.init();
        }

        return $this.properties.Get_Item($key);
    }

    #
    # Set the value of a global variable
    # to ensure later usage. Example
    # $this.setProperty('agent_version', '2.4.10')
    #
    $installer | Add-Member -membertype ScriptMethod -name 'setProperty' -value {
        param([string] $key, [string]$value)
        $this.properties.Set_Item($key, $value);
    }

    #
    # This function will dump all global
    # variables of the script for debugging
    # purposes
    #
    $installer | Add-Member -membertype ScriptMethod -name 'dumpProperties' -value {
        echo $this.properties;
    }

    #
    # Print an exception message and wait 5 seconds
    # before continuing the execution. After each
    # exception call we should ensure the script
    # ends with exit.
    # Todo: Adding exit 1 here results in an script
    # error we should take care off before adding it
    # again
    # Deprecated: Do no longer use!
    #
    $installer | Add-Member -membertype ScriptMethod -name 'exception' -value {
        param([string]$message, [string[]]$args)
        $Error.clear();
        $this.warn('Calling deprecated function exception. Use throw instead.');
        throw 'Exception: ' + $message;
    }

    #
    # Print the relevant exception
    # By reading the relevant info
    # from the stack
    #
    $installer | Add-Member -membertype ScriptMethod -name 'printLastException' -value {
        # Todo: Improve this entire handling
        #       for writing exception messages
        #       in general we should only see
        #       the actual thrown error instead of
        #       an stack trace where the error occured
        #Write-Host $this.error($error[$error.count - 1].FullyQualifiedErrorId) -ForegroundColor red;
        Write-Host $_.Exception.Message -ForegroundColor red;
    }
    
    #
    # this function will print an info message
    # or throw an exception, based on the 
    # provided exitcode
    # (0 = ok, anything else => exception)
    #
    $installer | Add-Member -membertype ScriptMethod -name 'printAndAssertResultBasedOnExitCode' -value {
        param([string]$result, [string]$exitcode)
        if ($exitcode -ne 0) {
            throw $result;
        } else {
            $this.info($result);
        }
    }

    #
    # Return an error message with red text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'error' -value {
        param([string] $message, [array] $args)
        Write-Host 'Error:' $message -ForegroundColor red;
    }

    #
    # Return a warning message with yellow text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'warn' -value {
        param([string] $message, [array] $args)
        Write-Host 'Warning:' $message -ForegroundColor yellow;
    }

    #
    # Return a info message with green text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'info' -value {
        param([string] $message, [array] $args)
        Write-Host 'Notice:' $message -ForegroundColor green;
    }

    #
    # Return a debug message with blue text
    # in case debug mode is enabled
    #
    $installer | Add-Member -membertype ScriptMethod -name 'debug' -value {
        param([string] $message, [array] $args)
        if ($this.config('debug_mode')) {
            Write-Host 'Debug:' $message -ForegroundColor blue;
        }
    }

    #
    # Initialise certain parts of the
    # script first
    #
    $installer | Add-Member -membertype ScriptMethod -name 'init' -value {
        $this.setProperty('initialized', $TRUE);
        # Set the default config dir
        $this.setProperty('config_dir', $Env:ProgramData + '\icinga2\etc\icinga2\');
        $this.setProperty('api_dir', $Env:ProgramData + '\icinga2\var\lib\icinga2\api\');
        # Generate endpoint nodes based on iput
        # parameters
        $this.generateEndpointNodes();
    }    

    #
    # We require to run this script as admin. Generate the required function here
    # We might run this script from a non-privileged user. Ensure we have admin 
    # rights first. Otherwise abort the script.
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAdmin' -value {
        $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object System.Security.Principal.WindowsPrincipal($identity)

        if (-not $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
            throw 'You require to run this script as administrator.';
            return $FALSE;
        }
        return $TRUE;
    }

    #
    # Build endpoint hosts and objects based
    # on configuration
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateEndpointNodes' -value {

        if (-Not $this.config('endpoints')) {
            throw 'You require to specify atleast one endpoint with parameter -Endpoints <nodes>';
        }

        $endpoint_objects = '';
        $endpoint_nodes = '';
        foreach ($endpoint in $this.config('endpoints')) {
            $endpoint_objects += 'object Endpoint "' + "$endpoint" +'"{}'+"`n";
            $endpoint_nodes += '"' + "$endpoint" + '", ';
        }
        # Remove the last blank and , from the string
        if (-Not $endpoint_nodes.length -eq 0) {
            $endpoint_nodes = $endpoint_nodes.Remove($endpoint_nodes.length - 2, 2);
        }
        $this.setProperty('endpoint_nodes', $endpoint_nodes);
        $this.setProperty('endpoint_objects', $endpoint_objects);
    }

    #
    # Do we require to update the Agent?
    # Might be disabled by user or current version
    # is already installed
    #    
    $installer | Add-Member -membertype ScriptMethod -name 'requireAgentUpdate' -value {
        if (-Not $this.config('allow_updates') -Or -Not $this.config('agent_version')) {
            $this.warn('Icinga 2 Agent update installation disabled.');
            return $FALSE;
        }

        if ($this.getProperty('agent_version') -eq $this.config('agent_version')) {
            $this.info('Icinga 2 Agent up-to-date. No update required.');
            return $FALSE;
        }

        $this.info('Current Icinga 2 Agent Version (' + $this.getProperty('agent_version') + ') is not matching server version (' + $this.config('agent_version') + '). Downloading new version...');

        return $TRUE;
    }

    #
    # Download the Icinga 2 Agent Installer from out defined source
    #
    $installer | Add-Member -membertype ScriptMethod -name 'downloadInstaller' -value {
        if (-Not $this.config('agent_version')) {
            return;
        }
        $WebStatusCode = 0;
        $url = $this.config('download_url') + $this.getProperty('install_msi_package');
        $this.info('Downloading Icinga 2 Agent Binary from ' + $url + '  ...');
        $execptionMsg = '';
        Try {
            $WebStatusCode = Invoke-WebRequest -Method Head -Uri "$url"

            if ($WebStatusCode.StatusCode -eq 200) {
                Invoke-WebRequest "$url" -OutFile $this.getInstallerPath();
            } else {
                throw 'Failed to download Icinga 2 Agent Installer from URL';
            }
        } catch {
             throw 'Unable to download Icinga 2 Agent from ' + $url + '. Error: ' + $_.Exception.Message;
        }
    }

    #
    # In case we provide a list of hashes to very against
    # we check them to ensure the package we downloaded
    # for the Agent installation is allowed to be installed
    #
    $installer | Add-Member -membertype ScriptMethod -name 'verifyInstallerChecksumAndThrowException' -value {
        if (-Not $this.config('installer_hashes')) {
            $this.warn("Icinga 2 Agent Installer verification disabled.");
            return;
        }

        $installerHash = Get-FileHash $this.getInstallerPath() -Algorithm "SHA1";
        foreach($hash in $this.config('installer_hashes')) {
            if ($hash -eq $installerHash.Hash) {
                $this.info('Icinga 2 Agent hash verification successfull.');
                return;
            }
        }

        throw 'Failed to verify against any provided installer hash.';
        return;
    }

    #
    # Returns the full path to our installer package
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getInstallerPath' -value {
        return $Env:temp + '\' + $this.getProperty('install_msi_package');
    }

    #
    # Verify that the installer package we downloaded
    # does exist in first place
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installerExists' -value {
        if (Test-Path $this.getInstallerPath()) {
            return $TRUE;
        }
        return $FALSE;
    }

    #
    # Install the Icinga 2 agent from the provided installation package
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installAgent' -value {
        $this.downloadInstaller();
        if (-Not $this.installerExists()) {
            throw 'Failed to setup Icinga 2 Agent. Installer package not found.';
        }
        $this.verifyInstallerChecksumAndThrowException();
        $this.info('Installing Icinga 2 Agent');
        Start-Process $this.getInstallerPath() -ArgumentList "/quiet" -wait;
        $this.info('Icinga 2 Agent installed.');
        $this.setProperty('require_restart', 'true');
    }

    #
    # Updates the Agent in case allowed and required.
    # Removes previous version of Icinga 2 Agent first
    #    
    $installer | Add-Member -membertype ScriptMethod -name 'updateAgent' -value {
        $this.downloadInstaller();
        if (-Not $this.installerExists()) {
            throw 'Failed to update Icinga 2 Agent. Installer package not found.';
        }
        $this.verifyInstallerChecksumAndThrowException()
        if (-Not $this.getProperty('uninstall_id')) {
            throw 'Failed to update Icinga 2 Agent. Uninstaller is not specified.';
        }

        $this.info('Removing previous Icinga 2 Agent version...');
        Start-Process "MsiExec.exe" -ArgumentList ($this.getProperty('uninstall_id') +' /q') -wait;
        $this.info('Installing new Icinga 2 Agent version...');
        Start-Process $this.getInstallerPath() -ArgumentList "/quiet" -wait;
        $this.info('Agent successfully updated.');
        $this.setProperty('cur_install_dir', $this.getProperty('def_install_dir'));
        $this.setProperty('require_restart', 'true');
    }    

    #
    # We might have installed the Icinga 2 Agent
    # already. In case we do, get all data to
    # ensure we access the Agent correctly
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAgentInstalled' -value {
        $defaultInstallDir = '';
        $architecture = '';
        $icingaInstallerName = '';
        if ([IntPtr]::Size -eq 4) {
            $defaultInstallDir = ${Env:ProgramFiles(x86)} + "\ICINGA2";
            $architecture = "x86";
            $regPath = 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*';
        } else {
            $defaultInstallDir = ${Env:ProgramFiles} + "\ICINGA2";
            $architecture = "x86_64";
            $regPath = @('HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*', 'HKLM:\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*');
        }

        # Try locating current Icinga 2 Agent installation
        $localData = Get-ItemProperty $regPath | 
            .{
                process {
                    if ($_.DisplayName) {
                        $_ 
                    } 
                }
            } |
            Where {
                $_.DisplayName -eq 'Icinga 2'
            } |
            Select-Object -Property InstallLocation, UninstallString, DisplayVersion

        if ($localData.UninstallString) {
            $this.setProperty('uninstall_id', $localData.UninstallString.Replace("MsiExec.exe ", ""));
        }        
        $this.setProperty('def_install_dir', $defaultInstallDir);
        $this.setProperty('cur_install_dir', $localData.InstallLocation);
        $this.setProperty('agent_version', $localData.DisplayVersion);        
        $this.setProperty('install_msi_package', 'Icinga2-v' + $this.config('agent_version') + '-' + $architecture + '.msi');

        if ($localData.InstallLocation) {
            $this.info('Found Icinga 2 Agent version ' + $localData.DisplayVersion + ' installed at ' + $localData.InstallLocation);
            return $TRUE;
        } else {
            $this.warn('Icinga 2 Agent does not seem to be installed on the system');
        }
        return $FALSE;
    }

    #
    # In case have the Agent already installed
    # We might use a different installation path
    # for the Agent. This function will return
    # the correct, valid installation path
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getInstallPath' -value {
        $agentPath = $this.getProperty('def_install_dir');
        if ($this.getProperty('cur_install_dir')) {
            $agentPath = $this.getProperty('cur_install_dir');
        }
        return $agentPath;
    }

    #
    # In case we installed the agent freshly we
    # require to change configuration once we
    # would like to use the Director properly
    # This function will simply do a backup
    # of the icinga2.conf, ensuring we can
    # use them later again
    #
    $installer | Add-Member -membertype ScriptMethod -name 'backupDefaultConfig' -value {
        $configFile = $this.getProperty('config_dir') + 'icinga2.conf';
        $configBackupFile = $configFile + 'director.bak';

        # Check if a config and backup file already exists
        # Only procceed with backup of the current config if no backup was found
        if (Test-Path $configFile) {
            if (-Not (Test-Path $configBackupFile)) {
                ren $configFile $configBackupFile;
                $this.info('Icinga 2 configuration backup successfull');
            } else {
                $this.warn('Default icinga2.conf backup detected. Skipping backup');
            }
        }
    }

    #
    # Allow us to restart the Icinga 2 Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'cleanupAgentInstaller' -value {
        if (Test-Path $this.getInstallerPath()) {
            $this.info('Removing downloaded Icinga 2 Agent installer');
            Remove-Item $this.getInstallerPath() | out-null
        }
    }
    
    #
    # Get Api directory if Icinga 2
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getApiDirectory' -value {
        return $this.getProperty('api_dir');
    }
    
    #
    # Should we remove the Api directory content
    # from the Agent? Can be defined by setting the
    # -RemoveApiDirectory argument of the function builder
    #
    $installer | Add-Member -membertype ScriptMethod -name 'shouldFlushIcingaApiDirectory' -value {
        return $this.config('flush_api_dir');
    }
    
    #
    # Flush all content from the Icinga 2 Agent
    # Api directory, but keep the folder structure
    #
    $installer | Add-Member -membertype ScriptMethod -name 'flushIcingaApiDirectory' -value {    
        if (Test-Path $this.getApiDirectory()) {
            $this.info('Flushing content of ' + $this.getApiDirectory());    
            Get-ChildItem -Path $this.getApiDirectory() -Recurse | Remove-Item -force -recurse    
        }
    }

    #
    # Restart the Icinga 2 service and get the
    # result if the restart failed or everything
    # worked as expected
    #
    $installer | Add-Member -membertype ScriptMethod -name 'restartAgent' -value {
        $this.info("Restarting Icinga 2 service...");
        Restart-Service icinga2;
        Start-Sleep -Seconds 2;
        $service = Get-WmiObject -Class Win32_Service -Filter "Name='icinga2'"
        if (-Not ($service.State -eq 'Running')) {
            throw 'Failed to restart Icinga 2 service.';
        } else {
            $this.info('Icinga 2 Agent successfully restarted.');
            $this.setProperty('require_restart', '');
        }    
    }

    $installer | Add-Member -membertype ScriptMethod -name 'generateIcingaConfiguration' -value {

        $this.checkConfigInputParametersAndThrowException();

        $icingaCurrentConfig = '';
        if (Test-Path $this.getIcingaConfigFile()) {
            $icingaCurrentConfig = Get-Content $this.getIcingaConfigFile() -Raw;
        }

        $icingaNewConfig = 
'/** Icinga 2 Config - proposed by Icinga Director */
include "constants.conf"
include <itl>
include <plugins>
// include <plugins-contrib>
if (!globals.contains("NscpPath")) {
  NscpPath = dirname(msi_get_component_path("{5C45463A-4AE9-4325-96DB-6E239C034F93}"))
}
object FileLogger "main-log" {
  severity = "information"
  path = LocalStateDir + "/log/icinga2/icinga2.log"
}
// TODO: improve establish connection handling
object Endpoint "' + $this.config('agent_name') + '" {}
' + $this.getProperty('endpoint_objects') + '
object Zone "' + $this.config('parent_zone') + '" {
  endpoints = [ ' + $this.getProperty('endpoint_nodes') +' ]
}
object Zone "director-global" { global = true }
object Zone "' + $this.config('agent_name') + '" {
  parent = "' + $this.config('parent_zone') + '"
  endpoints = [ "' + $this.config('agent_name') + '" ]
}
object ApiListener "api" {
  cert_path = SysconfDir + "/icinga2/pki/' + $this.config('agent_name') + '.crt"
  key_path = SysconfDir + "/icinga2/pki/' + $this.config('agent_name') + '.key"
  ca_path = SysconfDir + "/icinga2/pki/ca.crt"
  accept_commands = true
  accept_config = ' + $this.convertBoolToString($this.config('accept_config')) + '
}'

        $this.setProperty('new_icinga_config', $icingaNewConfig);
        $this.setProperty('old_icinga_config', $icingaCurrentConfig);        
    }

    #
    # Generate a hash for old and new config
    # and determine if the configuration has changed
    #
    $installer | Add-Member -membertype ScriptMethod -name 'hasConfigChanged' -value {

        if (-Not $this.getProperty('new_icinga_config')) {
            throw 'New Icinga 2 configuration not generated. Please call "generateIcingaConfiguration" before.';
        }

        $oldConfigHash = $this.getHashFromString($this.getProperty('old_icinga_config'));
        $newConfigHash = $this.getHashFromString($this.getProperty('new_icinga_config'));

        $this.debug('Old Config Hash: "' + $oldConfigHash + '" New Hash: "' + $newConfigHash + '"');

        if ($oldConfigHash -eq $newConfigHash) {
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # Generate a SHA1 Hash from a provided string
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getHashFromString' -value {
        param([string]$text)        
        $algorithm = new-object System.Security.Cryptography.SHA1Managed
        $hash = [System.Text.Encoding]::UTF8.GetBytes($text)
        $hashInBytes = $algorithm.ComputeHash($hash)
        foreach($byte in $hashInBytes) {
             $result += $byte.ToString()
        }
        return $result;
    }

    #
    # Return the path to the Icinga 2 config file
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getIcingaConfigFile' -value {
        return ($this.getProperty('config_dir') + 'icinga2.conf');
    }

    #
    # Create Icinga 2 configuration file based
    # on Director settings
    #
    $installer | Add-Member -membertype ScriptMethod -name 'writeConfig' -value {        
        # Write new configuration to file
        $this.info('Writing icinga2.conf to ' + $this.getProperty('config_dir'));
        [System.IO.File]::WriteAllText($this.getIcingaConfigFile(), $this.getProperty('new_icinga_config'));
        $this.setProperty('require_restart', 'true');
    }

    #
    # Write old coniguration again
    # just in case we received errors
    #
    $installer | Add-Member -membertype ScriptMethod -name 'rollbackConfig' -value {        
        # Write new configuration to file
        $this.info('Rolling back previous icinga2.conf to ' + $this.getProperty('config_dir'));
        [System.IO.File]::WriteAllText($this.getIcingaConfigFile(), $this.getProperty('old_icinga_config'));
        $this.setProperty('require_restart', 'true');
    }

    #
    # Provide a result of an operation (string) and
    # the intended match value. In case every was
    # ok, the function will return an info message
    # with the result. Otherwise it will thrown an
    # exception
    #
    $installer | Add-Member -membertype ScriptMethod -name 'printResultOkOrException' -value {
        param([string]$result, [string]$expected)
        if ($result -And $expected) {
            if (-Not ($result -Like $expected)) {
                throw $result;
            } else {
                $this.info($result);
            }
        } elseif ($result) {
            $this.info($result);
        }
    }

    #
    # Generate the Icinga 2 SSL certificate to ensure the communication between the
    # Agent and the Master can be established in first place
    #    
    $installer | Add-Member -membertype ScriptMethod -name 'generateCertificates' -value {

        if (-Not $this.config('agent_name') -Or -Not $this.config('ca_server') -Or -Not $this.config('ticket')) {
            throw 'One or more of the following arguments is missing: -AgentName <name> -CAServer <server> -Ticket <ticket>';
        }

        $icingaPkiDir = $this.getProperty('config_dir') + 'pki\';
        $icingaBinary = $this.getInstallPath() + '\sbin\icinga2.exe';
        $agentName = $this.config('agent_name');

        # Generate the certificate
        $this.info("Generating Icinga 2 certificates");
        $result = &$icingaBinary @('pki', 'new-cert', '--cn', $this.config('agent_name'), '--key', ($icingaPkiDir + $agentName + '.key'), '--cert', ($icingaPkiDir + $agentName + '.crt'));
        $this.printAndAssertResultBasedOnExitCode($result, $LASTEXITCODE);

        # Save Certificate
        $this.info("Storing Icinga 2 certificates");
        $result = &$icingaBinary @('pki', 'save-cert', '--key', ($icingaPkiDir + $agentName + '.key'), '--trustedcert', ($icingaPkiDir + 'trusted-master.crt'), '--host', $this.config('ca_server'));
        $this.printAndAssertResultBasedOnExitCode($result, $LASTEXITCODE);

        # Request certificate
        $this.info("Requesting Icinga 2 certificates");
        $result = &$icingaBinary @('pki', 'request', '--host', $this.config('ca_server'), '--port', $this.config('ca_port'), '--ticket', $this.config('ticket'), '--key', ($icingaPkiDir + $agentName + '.key'), '--cert',  ($icingaPkiDir + $agentName + '.crt'), '--trustedcert', ($icingaPkiDir + 'trusted-master.crt'), '--ca', ($icingaPkiDir + 'ca.crt'));
        $this.printAndAssertResultBasedOnExitCode($result, $LASTEXITCODE);

        $this.setProperty('require_restart', 'true');
    }

    #
    # Check the Icinga install directory and determine
    # if the certificates are both available for the
    # Agent. If not, return FALSE
    #
    $installer | Add-Member -membertype ScriptMethod -name 'hasCertificates' -value {
        $icingaPkiDir = $this.getProperty('config_dir') + 'pki\';
        $agentName = $this.config('agent_name');
        if (
            ((Test-Path ($icingaPkiDir + $agentName + '.key')) `
            -And (Test-Path ($icingaPkiDir + $agentName + '.crt')) `
            -And (Test-Path ($icingaPkiDir + 'ca.crt')))
        ) {
            return $TRUE;
        }
        return $FALSE;
    }

    #
    # Have we passed an argument to force
    # the creation of the certificates?
    #
    $installer | Add-Member -membertype ScriptMethod -name 'forceCertificateGeneration' -value {
        return $this.config('force_cert');
    }

    #
    # Is the current Agent the version
    # we would like to install?
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAgentUpToDate' -value {     
        if ($this.canInstallAgent() -And $this.getProperty('agent_version') -eq $this.config('agent_version')) {
            return $TRUE;
        }

        return $FALSE
    }

    #
    # Print a message telling us the installed
    # and intended version of the Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'printAgentUpdateMessage' -value {
        $this.info('Current Icinga 2 Agent Version (' + $this.getProperty('agent_version') + ') is not matching intended version (' + $this.config('agent_version') + '). Downloading new version...');
    }

    #
    # Do we allow Agent updates / downgrades?
    #
    $installer | Add-Member -membertype ScriptMethod -name 'allowAgentUpdates' -value {
        return $this.config('allow_updates');
    }

    #
    # Have we specified a version to install the Agent?
    #
    $installer | Add-Member -membertype ScriptMethod -name 'canInstallAgent' -value {
        if (-Not $this.config('agent_version')) {
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # Check if all required arguments for writing a valid
    # configuration are set
    #
    $installer | Add-Member -membertype ScriptMethod -name 'checkConfigInputParametersAndThrowException' -value {
        if (-Not $this.config('agent_name')) {
            throw 'Argument -AgentName <name> required for config generation.';
        }
        if (-Not $this.config('parent_zone')) {
            throw 'Argument -ParentZone <name> required for config generation.';
        }
        if (-Not $this.getProperty('endpoint_nodes') -Or -Not $this.getProperty('endpoint_objects')) {
            throw 'Argument -Endpoints <name> requires atleast one defined endpoint.';
        }        
    }

    #
    # Execute a check with Icinga2 daemon -C
    # to ensure the configuration is valid
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isIcingaConfigValid' -value {
        param([bool] $checkInternal = $TRUE)
        if (-Not $this.config('parent_zone') -And $checkInternal) {
            throw 'Parent Zone not defined. Please specify it with -ParentZone <name>';
        }
        $icingaBinary = $this.getInstallPath() + '\sbin\icinga2.exe';
        $output = &$icingaBinary @('daemon', '-C');
        if ($LASTEXITCODE -ne 0) {
            return $FALSE;
        }
        return $TRUE;
    }

    #
    # Returns true or false, depending
    # if any changes were made requiring
    # the Icinga 2 Agent to become restarted
    #
    $installer | Add-Member -membertype ScriptMethod -name 'madeChanges' -value {
        return $this.getProperty('require_restart');
    }
    
    #
    # Apply possible configuration changes to
    # our Icinga 2 Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'applyPossibleConfigChanges' -value {
        if ($this.hasConfigChanged()) {
            $this.backupDefaultConfig();
            $this.writeConfig();

            # Check if the config is valid and rollback otherwise
            if (-Not $this.isIcingaConfigValid()) {
                $this.error('Icinga 2 config validation failed. Rolling back to previous version.');
                $this.rollbackConfig();
                if ($this.isIcingaConfigValid($FALSE)) {
                    $this.info('Rollback of Icinga 2 configuration successfull.');
                } else {
                    throw 'Icinga 2 config rollback failed. Please check the icinga2.log';
                }
            } else {
                $this.info('Icinga 2 configuration check successfull.');
            }
        } else {
            $this.info('icinga2.conf did not change. Nothing to do');
        }
    }

    #
    # This function will try to load all
    # data from the system and setup the
    # entire Agent without user interaction
    # including download and update if
    # specified. Returnd 0 or 1 as exit code
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installIcinga2Agent' -value {        
        try {
            if (-Not $this.isAdmin()) {
                return 1;
            }        
            # Try to locate the current
            # Installation data from the Agent
            if ($this.isAgentInstalled()) {
                if (-Not $this.isAgentUpToDate()) {
                    if ($this.allowAgentUpdates()) {
                        $this.printAgentUpdateMessage();
                        $this.updateAgent();
                        $this.cleanupAgentInstaller();
                    }
                } else {
                    $this.info('Icinga 2 Agent is up-to-date. Nothing to do.');
                }
            } else {
                if ($this.canInstallAgent()){
                    $this.installAgent();
                    $this.cleanupAgentInstaller();
                } else {
                    throw 'Icinga 2 Agent is not installed and not allowed of beeing installed. Nothing to do.';
                }
            }

            if (-Not $this.hasCertificates() -Or $this.forceCertificateGeneration()) {
                $this.generateCertificates();
            } else {
                $this.info('Icinga 2 certificates already exist. Nothing to do.');
            }

            $this.generateIcingaConfiguration();
            $this.applyPossibleConfigChanges();

            if ($this.madeChanges()) {
                if ($this.shouldFlushIcingaApiDirectory()) {
                    $this.flushIcingaApiDirectory();
                }
                $this.restartAgent();
            } else {
                $this.info('No changes detected.');
            }
            return 0            
        } catch {
            $this.printLastException();
            return 1
        }
    }

    return $installer
}