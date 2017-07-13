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
        [bool]$FetchAgentName             = $FALSE,
        [bool]$FetchAgentFQDN             = $FALSE,
        [int]$TransformHostname           = 0,

        # Agent configuration
        [int]$AgentListenPort             = 5665,
        [string]$ParentZone,
        [bool]$AcceptConfig               = $TRUE,
        [bool]$IcingaEnableDebugLog       = $FALSE,
        [bool]$AgentAddFirewallRule       = $FALSE,
        [array]$ParentEndpoints,
        [array]$EndpointsConfig,
        [array]$GlobalZones,

        # Agent installation / update
        [string]$IcingaServiceUser,
        [string]$DownloadUrl              = 'https://packages.icinga.com/windows/',
        [bool]$AllowUpdates               = $FALSE,
        [array]$InstallerHashes,
        [bool]$FlushApiDirectory          = $FALSE,

        # Agent signing
        [string]$CAServer,
        [int]$CAPort                      = 5665,
        [bool]$ForceCertificateGeneration = $FALSE,
        [string]$CAFingerprint,

        # Director communication
        [string]$DirectorUrl,
        [string]$DirectorUser,
        [string]$DirectorPassword,
        [string]$DirectorDomain,
        [string]$DirectorAuthToken,
        [string]$DirectorHostObject,
        [bool]$DirectorDeployConfig       = $FALSE,

        # NSClient Installer
        [bool]$InstallNSClient            = $FALSE,
        [bool]$NSClientAddDefaults        = $FALSE,
        [bool]$NSClientEnableFirewall     = $FALSE,
        [bool]$NSClientEnableService      = $FALSE,
        [string]$NSClientDirectory,
        [string]$NSClientInstallerPath,

        # Uninstaller arguments
        [bool]$FullUninstallation         = $FALSE,

        #Internal handling
        [bool]$DebugMode                  = $FALSE
    );

    #
    # Initialise our installer object
    # and generate our config objects
    #
    $installer = New-Object -TypeName PSObject;
    $installer | Add-Member -membertype NoteProperty -name 'properties' -value @{}
    $installer | Add-Member -membertype NoteProperty -name 'cfg' -value @{
        agent_name              = $AgentName;
        ticket                  = $Ticket;
        agent_version           = $InstallAgentVersion;
        fetch_agent_name        = $FetchAgentName;
        fetch_agent_fqdn        = $FetchAgentFQDN;
        transform_hostname      = $TransformHostname;
        agent_listen_port       = $AgentListenPort;
        parent_zone             = $ParentZone;
        accept_config           = $AcceptConfig;
        icinga_enable_debug_log = $IcingaEnableDebugLog;
        agent_add_firewall_rule = $AgentAddFirewallRule;
        parent_endpoints        = $ParentEndpoints;
        endpoints_config         = $EndpointsConfig;
        global_zones            = $GlobalZones;
        icinga_service_user     = $IcingaServiceUser;
        download_url            = $DownloadUrl;
        allow_updates           = $AllowUpdates;
        installer_hashes        = $InstallerHashes;
        flush_api_directory     = $FlushApiDirectory;
        ca_server               = $CAServer;
        ca_port                 = $CAPort;
        force_cert              = $ForceCertificateGeneration;
        ca_fingerprint          = $CAFingerprint;
        director_url            = $DirectorUrl;
        director_user           = $DirectorUser;
        director_password       = $DirectorPassword;
        director_domain         = $DirectorDomain;
        director_auth_token     = $DirectorAuthToken;
        director_host_object    = $DirectorHostObject;
        director_deploy_config  = $DirectorDeployConfig;
        install_nsclient        = $InstallNSClient;
        nsclient_add_defaults   = $NSClientAddDefaults;
        nsclient_firewall       = $NSClientEnableFirewall;
        nsclient_service        = $NSClientEnableService;
        nsclient_directory      = $NSClientDirectory;
        nsclient_installer_path = $NSClientInstallerPath;
        full_uninstallation     = $FullUninstallation;
        debug_mode              = $DebugMode;
    }

    #
    # Access default script config parameters
    # by using this function. These variables
    # are set during the initial call of
    # the script with the parameters
    #
    $installer | Add-Member -membertype ScriptMethod -name 'config' -value {
        param([string] $key);
        return $this.cfg[$key];
    }

    #
    # Override the given arguments of the PowerShell script with
    # custom values or edited values
    #
    $installer | Add-Member -membertype ScriptMethod -name 'overrideConfig' -value {
        param([string] $key, $value);
        $this.cfg[$key] = $value;
    }

    #
    # Convert a boolean value $TRUE $FALSE
    # to a string value
    #
    $installer | Add-Member -membertype ScriptMethod -name 'convertBoolToString' -value {
        param([bool]$key);
        if ($key) {
            return 'true';
        }
        return 'false';
    }

    #
    # Convert a boolean value $TRUE $FALSE
    # to a int value
    #
    $installer | Add-Member -membertype ScriptMethod -name 'convertBoolToInt' -value {
        param([bool]$key);
        if ($key) {
            return 1;
        }
        return 0;
    }

    #
    # Global variables can be accessed
    # by using this function. Example:
    # $this.getProperty('agent_version)
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getProperty' -value {
        param([string] $key);

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
        param([string] $key, [string]$value);

        # Initialse some variables first
        # will only be called once
        if (-Not $this.properties.Get_Item('initialized')) {
            $this.properties.Set_Item('initialized', $TRUE);
            $this.init();
        }

        $this.properties.Set_Item($key, $value);
    }

    #
    # This function will dump all global
    # variables of the script for debugging
    # purposes
    #
    $installer | Add-Member -membertype ScriptMethod -name 'dumpProperties' -value {
        Write-Output $this.properties;
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
        param([string]$message, [string[]]$args);
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
        param([string]$result, [string]$exitcode);
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
        param([string] $message, [array] $args);
        Write-Host 'Error:' $message -ForegroundColor red;
    }

    #
    # Return a warning message with yellow text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'warn' -value {
        param([string] $message, [array] $args);
        Write-Host 'Warning:' $message -ForegroundColor yellow;
    }

    #
    # Return a info message with green text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'info' -value {
        param([string] $message, [array] $args);
        Write-Host 'Notice:' $message -ForegroundColor green;
    }

    #
    # Return a debug message with blue text
    # in case debug mode is enabled
    #
    $installer | Add-Member -membertype ScriptMethod -name 'debug' -value {
        param([string] $message, [array] $args);
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
        $this.setProperty('api_dir', $Env:ProgramData + '\icinga2\var\lib\icinga2\api');
        $this.setProperty('icinga_ticket', $this.config('ticket'));
        $this.setProperty('local_hostname', $this.config('agent_name'));
        # Ensure we generate the required configuration content
        $this.generateConfigContent();
    }

    #
    # We require to run this script as admin. Generate the required function here
    # We might run this script from a non-privileged user. Ensure we have admin
    # rights first. Otherwise abort the script.
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAdmin' -value {
        $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent();
        $principal = New-Object System.Security.Principal.WindowsPrincipal($identity);

        if (-not $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
            throw 'You require to run this script as administrator.';
            return $FALSE;
        }
        return $TRUE;
    }

    #
    # In case we want to define endpoint configuration (address / port)
    # we will require to fetch data correctly from a given array
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getEndpointConfigurationByArrayIndex' -value {
        param([int] $currentIndex);

        # Load the config into a local variable for quicker access
        [array]$endpoint_config = $this.config('endpoints_config');

        # In case no endpoint config is given, we should do nothing
        if ($endpoint_config -eq $NULL) {
            return '';
        }

        [string]$configArgument = $endpoint_config[$currentIndex];
        [string]$config_string = '';
        [array]$configObject = '';

        if ($configArgument -ne '') {
            $configObject = $configArgument.Split(';');
        } else {
            return '';
        }

        # Write the host data from the first array position
        if ($configObject[0]) {
            $config_string += '  host = "' + $configObject[0] +'"';
        }

        # Write the port data from the second array position
        if ($configObject[1]) {
            $config_string += "`n"+'  port = ' + $configObject[1];
        }

        # Return the host and possible port configuration for this endpoint
        return $config_string;
    }

    #
    # Build endpoint hosts and objects based
    # on configuration
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateEndpointNodes' -value {

        if ($this.config('parent_endpoints')) {
            [string]$endpoint_objects = '';
            [string]$endpoint_nodes = '';
            [int]$endpoint_index = 0;

            foreach ($endpoint in $this.config('parent_endpoints')) {
                $endpoint_objects += 'object Endpoint "' + "$endpoint" +'" {'+"`n";
                $endpoint_objects += $this.getEndpointConfigurationByArrayIndex($endpoint_index);
                $endpoint_objects += "`n" + '}' + "`n";
                $endpoint_nodes += '"' + "$endpoint" + '", ';
                $endpoint_index += 1;
            }
            # Remove the last blank and , from the string
            if (-Not $endpoint_nodes.length -eq 0) {
                $endpoint_nodes = $endpoint_nodes.Remove($endpoint_nodes.length - 2, 2);
            }
            $this.setProperty('endpoint_nodes', $endpoint_nodes);
            $this.setProperty('endpoint_objects', $endpoint_objects);
            $this.setProperty('generate_config', 'true');
        } else {
            $this.setProperty('generate_config', 'false');
        }
    }

    #
    # Generate global zones by configuration
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateGlobalZones' -value {

        # Load all configured global zones
        [array]$global_zones = $this.config('global_zones');
        # Add director-global zone as default - always
        [string]$zones = 'object Zone "director-global" {' + "`n" + ' global = true' + "`n" + '}' + "`n";

        # In case no zones are given, simply add director-global
        if ($global_zones -eq $NULL) {
            $this.setProperty('global_zones', $zones);
            return;
        }

        # Loop through all given zones and add them to our configuration
        foreach ($zone in $global_zones) {
            # Ignore possible configured director-global zone, as already present
            if ($zone -ne 'director-global') {
                $zones = $zones + 'object Zone "' + $zone + '" {' + "`n" + ' global = true' + "`n" + '}' + "`n";
            }
        }
        $this.setProperty('global_zones', $zones);
    }

    #
    # Generate default config values
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateConfigContent' -value {
        $this.generateEndpointNodes();
        $this.generateGlobalZones();
    }

    #
    # This function will ensure we create a
    # Web Client object we can use entirely
    # inside the module to achieve our requirements
    #
    $installer | Add-Member -membertype ScriptMethod -name 'createWebClientInstance' -value {
        param([string]$header, [bool]$directorHeader = $FALSE);

        [System.Object]$webClient = New-Object System.Net.WebClient;
        if ($this.config('director_user') -And $this.config('director_password')) {
            [string]$domain = $null;
            if ($this.config('director_domain')) {
                $domain = $this.config('director_domain');
            }
            $webClient.Credentials = New-Object System.Net.NetworkCredential($this.config('director_user'), $this.config('director_password'), $domain);
        }
        $webClient.Headers.add('accept', $header);
        if ($directorHeader) {
            $webClient.Headers.add('X-Director-Accept', 'text/plain');
        }

        return $webClient;
    }

    #
    # Handle HTTP Requests properly to receive proper status codes in return
    #
    $installer | Add-Member -membertype ScriptMethod -name 'createHTTPRequest' -value {
        param([string]$url, [string]$body, [string]$method, [string]$header, [bool]$directorHeader, [bool]$printExceptionMessage);

        $httpRequest = [System.Net.HttpWebRequest]::Create($url);
        $httpRequest.Method = $method;
        $httpRequest.Accept = $header;
        $httpRequest.ContentType = 'application/json; charset=utf-8';
        if ($directorHeader) {
            $httpRequest.Headers.Add('X-Director-Accept: text/plain');
        }
        $httpRequest.TimeOut = 6000;

        if ($this.config('director_user') -And $this.config('director_password')) {
            [string]$credentials = [System.Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($this.config('director_user') + ':' + $this.config('director_password')));
            $httpRequest.Headers.add('Authorization: Basic ' + $credentials);
        }

        # Only send data in case we want to send some data
        if ($body -ne '') {
            $transmitBytes = [System.Text.Encoding]::UTF8.GetBytes($body);
            $httpRequest.ContentLength = $transmitBytes.Length;
            [System.IO.Stream]$httpOutput = [System.IO.Stream]$httpRequest.GetRequestStream()
            $httpOutput.Write($transmitBytes, 0, $transmitBytes.Length)
            $httpOutput.Close()
        }

        try {

            return $this.readResponseStream($httpRequest.GetResponse());

        } catch [System.Net.WebException] {
            if ($printExceptionMessage) {
                # Print an exception message and the possible body in case we received one
                # to make troubleshooting easier
                [string]$errorResponse = $this.readResponseStream($_.Exception.Response);
                $this.error($_.Exception.Message);
                if ($errorResponse -ne '') {
                    $this.error($errorResponse);
                }
            }

            $exceptionMessage = $_.Exception.Response;
            $httpErrorCode = [int][system.net.httpstatuscode]$exceptionMessage.StatusCode;
            return $httpErrorCode;
        }

        return '';
    }

    #
    # Read the content of a response and return it's value as a string
    #
    $installer | Add-Member -membertype ScriptMethod -name 'readResponseStream' -value {
        param([System.Object]$response);
        $responseStream = $response.getResponseStream();
        $streamReader = New-Object IO.StreamReader($responseStream);
        $result = $streamReader.ReadToEnd();
        $response.close()
        $streamReader.close()

        return $result;
    }

    #
    # Check if the provided result is an HTTP Response code
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isHTTPResponseCode' -value {
        param([string]$httpResult);

        if ($httpResult.length -eq 3) {
            return $TRUE;
        }

        return $FALSE;
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
    # We could try to install the Agent from a local directory
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isDownloadPathLocal' -value {
        if (Test-Path ($this.config('download_url'))) {
            return $TRUE;
        }
        return $FALSE;
    }

    #
    # Download the Icinga 2 Agent Installer from out defined source
    #
    $installer | Add-Member -membertype ScriptMethod -name 'downloadInstaller' -value {
        if (-Not $this.config('agent_version')) {
            return;
        }

        if ($this.isDownloadPathLocal()) {
            $this.info('Installing Icinga 2 Agent from local directory');
        } else {
            $url = $this.config('download_url') + $this.getProperty('install_msi_package');
            $this.info('Downloading Icinga 2 Agent Binary from ' + $url + '  ...');

            Try {
                [System.Object]$client = New-Object System.Net.WebClient;
                $client.DownloadFile($url, $this.getInstallerPath());

                if (-Not $this.installerExists()) {
                    throw 'Unable to locate downloaded Icinga 2 Agent installer file from ' + $url + '. Download destination: ' + $this.getInstallerPath();
                }
            } catch {
                throw 'Unable to download Icinga 2 Agent from ' + $url + '. Please ensure the link does exist and access is possible. Error: ' + $_.Exception.Message;
            }
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

        [string]$installerHash = $this.getInstallerFileHash($this.getInstallerPath());
        foreach($hash in $this.config('installer_hashes')) {
            if ($hash -eq $installerHash) {
                $this.info('Icinga 2 Agent hash verification successfull.');
                return;
            }
        }

        throw 'Failed to verify against any provided installer hash.';
        return;
    }

    #
    # Get the SHA1 hash from our uninstaller file
    # Own function required because Get-FileHash is not
    # supported by PowerShell Version 2
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getInstallerFileHash' -value {
        param([string]$filename);

        [System.Object]$fileInput = New-Object System.IO.FileStream($filename,[System.IO.FileMode]::Open);
        [System.Object]$hash = New-Object System.Text.StringBuilder;
        [System.Security.Cryptography.HashAlgorithm]::Create('SHA1').ComputeHash($fileInput) |
            ForEach-Object {
                [Void]$hash.Append($_.ToString("x2"));
            }
        $fileInput.Close();
        return $hash.ToString().ToUpper();
    }

    #
    # Returns the full path to our installer package
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getInstallerPath' -value {
        if ($this.isDownloadPathLocal()) {
            if (Test-Path ($this.config('download_url') + $this.getProperty('install_msi_package'))) {
                return $this.config('download_url') + $this.getProperty('install_msi_package');
            } else {
                throw 'Failed to locate local Icinga 2 Agent installer at ' + $this.config('download_url') + $this.getProperty('install_msi_package');
            }
        } else {
            return $Env:temp + '\' + $this.getProperty('install_msi_package');
        }
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

        # Start the installer process
        $result = $this.startProcess('MsiExec.exe', $TRUE, '/quiet /i "' + $this.getInstallerPath() + '"');

        # Exit Code 0 means the Agent was installed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error('Failed to install Icinga 2 Agent. ' + $result.Get_Item('message'));
        } else {
            $this.info('Icinga 2 Agent installed.');
        }

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
        # Start the uninstaller process
        $result = $this.startProcess('MsiExec.exe', $TRUE, $this.getProperty('uninstall_id') +' /q');

        # Exit Code 0 means the Agent was removed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error('Failed to remove Icinga 2 Agent. ' + $result.Get_Item('message'));
        } else {
            $this.info('Icinga 2 Agent successfully removed.');
        }

        $this.info('Installing new Icinga 2 Agent version...');
        # Start the installer process
        $result = $this.startProcess('MsiExec.exe', $TRUE, '/quiet /i "' + $this.getInstallerPath() + '"');

        # Exit Code 0 means the Agent was removed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error('Failed to install new Icinga 2 Agent. ' + $result.Get_Item('message'));
        } else {
            $this.info('Icinga 2 Agent successfully updated.');
        }

        $this.setProperty('cur_install_dir', $this.getProperty('def_install_dir'));
        $this.setProperty('require_restart', 'true');
    }

    #
    # We might have installed the Icinga 2 Agent
    # already. In case we do, get all data to
    # ensure we access the Agent correctly
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAgentInstalled' -value {
        [string]$architecture = '';
        [string]$defaultInstallDir = ${Env:ProgramFiles} + "\ICINGA2";
        if ([IntPtr]::Size -eq 4) {
            $architecture = "x86";
            $regPath = 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*';
        } else {
            $architecture = "x86_64";
            $regPath = @('HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*', 'HKLM:\Software\Wow6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*');
        }

        # Try locating current Icinga 2 Agent installation
        $localData = Get-ItemProperty $regPath |
            .{
                process {
                    if ($_.DisplayName) {
                        $_;
                    }
                }
            } |
            Where-Object {
                $_.DisplayName -eq 'Icinga 2';
            } |
            Select-Object -Property InstallLocation, UninstallString, DisplayVersion;

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
    # Ensure we are able to install a firewall rule for the Icinga 2 Agent,
    # allowing masters and satellites to connect to our local agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installIcingaAgentFirewallRule' -value {
        if ($this.config('agent_add_firewall_rule') -eq $FALSE) {
            $this.warn('Icinga 2 Agent Firewall Rule will not be installed.');
            return;
        }

        $this.info('Trying to install Icinga 2 Agent Firewall Rule for port ' + $this.config('agent_listen_port'));

        $result = $this.startProcess('netsh', $FALSE, 'advfirewall firewall show rule name="Icinga 2 Agent Inbound by PS-Module"');
        if ($result.Get_Item('exitcode') -eq 0) {
            # Firewall rule is already defined -> delete it and add it again

            $this.info('Icinga 2 Agent Firewall Rule already installed. Trying to remove it to add it again...');
            $result = $this.startProcess('netsh', $TRUE, 'advfirewall firewall delete rule name="Icinga 2 Agent Inbound by PS-Module"');

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error('Failed to remove Icinga 2 Agent Firewall rule before adding it again: ' + $result.Get_Item('message'));
                return;
            } else {
                $this.info('Icinga 2 Agent Firewall Rule has been removed. Re-Adding now...');
            }
        }

        [string]$argument = 'advfirewall firewall add rule'
        $argument = $argument + ' dir=in action=allow program="' + $this.getInstallPath() + 'sbin\icinga2.exe"';
        $argument = $argument + ' name="Icinga 2 Agent Inbound by PS-Module"';
        $argument = $argument + ' description="Inbound Firewall Rule to allow Icinga 2 masters/satellites to connect to the Icinga 2 Agent installed on this system."';
        $argument = $argument + ' enable=yes';
        $argument = $argument + ' remoteip=any';
        $argument = $argument + ' localip=any';
        $argument = $argument + ' localport=' + $this.config('agent_listen_port');
        $argument = $argument + ' protocol=tcp';

        $result = $this.startProcess('netsh', $FALSE, $argument);
        if ($result.Get_Item('exitcode') -ne 0) {
            # Firewall rule was not added -> print error
            $this.error('Failed to install Icinga 2 Agent Firewall: ' + $result.Get_Item('message'));
            return;
        }

        $this.info('Icinga 2 Agent Firewall Rule successfully installed for port ' + $this.config('agent_listen_port'));
    }

    #
    # Get the default path or our custom path for the NSClient++
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getNSClientDefaultExecutablePath' -value {

        if ($this.config('nsclient_directory')) {
            return $this.config('nsclient_directory') + '\nscp.exe';
        }

        if (Test-Path ('C:\Program Files\NSClient++\nscp.exe')) {
            return 'C:\Program Files\NSClient++\nscp.exe';
        }

        if (Test-Path ('C:\Program Files (x86)\NSClient++\nscp.exe')) {
            return 'C:\Program Files (x86)\NSClient++\nscp.exe';
        }

        return '';
    }

    #
    # In case have the Agent already installed
    # We might use a different installation path
    # for the Agent. This function will return
    # the correct, valid installation path
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getInstallPath' -value {
        [string]$agentPath = $this.getProperty('def_install_dir');
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
        [string]$configFile = $this.getProperty('config_dir') + 'icinga2.conf';
        [string]$configBackupFile = $configFile + 'director.bak';

        # Check if a config and backup file already exists
        # Only procceed with backup of the current config if no backup was found
        if (Test-Path $configFile) {
            if (-Not (Test-Path $configBackupFile)) {
                Rename-Item $configFile $configBackupFile;
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
        if (-Not ($this.isDownloadPathLocal())) {
            if (Test-Path $this.getInstallerPath()) {
                $this.info('Removing downloaded Icinga 2 Agent installer');
                Remove-Item $this.getInstallerPath() | out-null;
            }
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
        return $this.config('flush_api_directory');
    }

    #
    # Flush all content from the Icinga 2 Agent
    # Api directory, but keep the folder structure
    #
    $installer | Add-Member -membertype ScriptMethod -name 'flushIcingaApiDirectory' -value {
        if (Test-Path $this.getApiDirectory()) {
            $this.info('Flushing content of ' + $this.getApiDirectory());
            [System.Object]$folder = New-Object -ComObject Scripting.FileSystemObject;
            $folder.DeleteFolder($this.getApiDirectory());
            $this.setProperty('require_restart', 'true');
        }
    }

    #
    # Modify the user the Icinga services is running with
    #
    $installer | Add-Member -membertype ScriptMethod -name 'modifyIcingaServiceUser' -value {

        # If no user is specified -> do nothing
        if ($this.config('icinga_service_user') -eq '') {
            return;
        }

        [System.Object]$currentUser = Get-WMIObject win32_service -Filter "Name='icinga2'";
        [string]$credentials = $this.config('icinga_service_user');
        [string]$newUser = '';
        [string]$password = 'dummy';

        if ($currentUser -eq $null) {
            $this.warn('Unable to modify Icinga service user: Service not found.');
            return;
        }

        # Check if we defined user name and password (':' cannot appear within a username)
        # If so split them into seperate variables, otherwise simply use the string as user
        if ($credentials.Contains(':')) {
            [int]$delimeter = $credentials.IndexOf(':');
            $newUser = $credentials.Substring(0, $delimeter);
            $password = $credentials.Substring($delimeter + 1, $credentials.Length - 1 - $delimeter);
        } else {
            $newUser = $credentials;
        }

        # If the user's are identical -> do nothing
        if ($currentUser.StartName -eq $newUser) {
            $this.info('Icinga user was not modified. Source and target service user are identical.');
            return;
        }

        # Try to update the service name and return an error in case of a failure
        # In the error case we do not have to deal with cleanup, as no change was made anyway
        $this.info('Updating Icinga 2 service user to ' + $newUser);
        $result = $this.startProcess('sc.exe', $TRUE, 'config icinga2 obj="' + $newUser + '" ' + 'password=' + $password);

        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error($result.Get_Item('message'));
            return;
        }

        # Just write the success message
        $this.info($result.Get_Item('message'));

        # Try to restart the service
        $result = $this.restartService('icinga2');

        # In case of an error try to rollback to the previous assigned user of the service
        # If this fails aswell, set the user to 'LocalSystem' and restart the service to
        # ensure that the agent is atleast running and collecting some data.
        # Of course we throw plenty of errors to notify the user about problems
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error($result.Get_Item('message'));
            $this.info('Reseting user to previous working user ' + $currentUser.StartName);
            $result = $this.startProcess('sc.exe', $TRUE, 'config icinga2 obj="' + $currentUser.StartName + '" ' + 'password=' + $password);
            $result = $this.restartService('icinga2');
            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error('Failed to reset Icinga 2 service user to the previous user ' + $currentUser.StartName + '. Setting user to "LocalSystem" now to ensure the service integrity');
                $result = $this.startProcess('sc.exe', $TRUE, 'config icinga2 obj="LocalSystem" password=dummy');
                $this.info($result.Get_Item('message'));
                $result = $this.restartService('icinga2');
                if ($result.Get_Item('exitcode') -eq 0) {
                    $this.info('Reseting Icinga 2 service user to "LocalSystem" successfull.');
                    return;
                } else {
                    $this.error('Failed to rollback Icinga 2 service user to "LocalSystem": ' + $result.Get_Item('message'));
                    return;
                }
            }
        }

        $this.info('Icinga 2 service is running');
    }

    #
    # Function to make restart of services easier
    #
    $installer | Add-Member -membertype ScriptMethod -name 'restartService' -value {
        param([string]$service);

        $this.info('Restarting service ' + $service + '...');

        # Stop the current service
        $result = $this.startProcess("sc.exe", $TRUE, "stop $service");

        # Wait until the service is stopped
        $serviceResult = $this.waitForServiceToReachState($service, 'Stopped');

        # Start the service again
        $result = $this.startProcess("sc.exe", $TRUE, "start $service");

        # Wait until the service is started
        if ($this.waitForServiceToReachState($service, 'Running') -eq $FALSE) {
            $result.Set_Item('message', 'Failed to restart service ' + $service + '.');
            $result.Set_Item('exitcode', '1');
        }

        return $result;
    }

    #
    # This function will wait for a specific service until it reaches
    # the defined state. Will break after 20 seconds with an error message
    #
    $installer | Add-Member -membertype ScriptMethod -name 'waitForServiceToReachState' -value {
        param([string]$service, [string]$state);

        [int]$counter = 0;

        # Wait until the service reached the desired state
        while ($TRUE) {

            # Get the current state of the service
            $serviceState = (Get-WMIObject win32_service -Filter "Name='$service'").State;
            if ($serviceState -eq $state) {
                break;
            }

            # Sleep a little to prevent crushing the CPU
            Start-Sleep -Milliseconds 100;
            $counter += 1;

            # After 20 seconds break with an error. It look's like the service does not respond
            if ($counter -gt 200) {
                $this.error('Timeout reached while waiting for ' + $service + ' to reach state ' + $state + '. Service is not responding.');
                return $FALSE;
            }
        }

        # Wait one second and check the status again to ensure it remains within it's state
        Start-Sleep -Seconds 1;

        if ($state -ne (Get-WMIObject win32_service -Filter "Name='$service'").State) {
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # Function to start processes and wait for their exit
    # Will return a dictionary with results (message, error, exitcode)
    #
    $installer | Add-Member -membertype ScriptMethod -name 'startProcess' -value {
        param([string]$executable, [bool]$flushNewLines, [string]$arguments);

        $processData = New-Object System.Diagnostics.ProcessStartInfo;
        $processData.FileName = $executable;
        $processData.RedirectStandardError = $true;
        $processData.RedirectStandardOutput = $true;
        $processData.UseShellExecute = $false;
        $processData.Arguments = $arguments;
        $process = New-Object System.Diagnostics.Process;
        $process.StartInfo = $processData;
        $process.Start() | Out-Null;
        $stdout = $process.StandardOutput.ReadToEnd();
        $stderr = $process.StandardError.ReadToEnd();
        $process.WaitForExit();

        if ($flushNewLines) {
            $stdout = $stdout.Replace("`n", '').Replace("`r", '');
            $stderr = $stderr.Replace("`n", '').Replace("`r", '');
        } else {
            if ($stdout.Contains("`n")) {
                $stdout = $stdout.Substring(0, $stdout.LastIndexOf("`n"));
            }
        }

        $result = @{};
        $result.Add('message', $stdout);
        $result.Add('error', $stderr);
        $result.Add('exitcode', $process.ExitCode);

        return $result;
    }

    #
    # Restart the Icinga 2 service and get the
    # result if the restart failed or everything
    # worked as expected
    #
    $installer | Add-Member -membertype ScriptMethod -name 'restartAgent' -value {
        $result = $this.restartService('icinga2');

        if ($result.Get_Item('exitcode') -eq 0) {
            $this.info('Icinga 2 Agent successfully restarted.');
            $this.setProperty('require_restart', '');
        } else {
            $this.error($result.Get_Item('message'));
        }
    }

    $installer | Add-Member -membertype ScriptMethod -name 'generateIcingaConfiguration' -value {
        if ($this.getProperty('generate_config') -eq 'true') {

            $this.checkConfigInputParametersAndThrowException();

            [string]$icingaCurrentConfig = '';
            if (Test-Path $this.getIcingaConfigFile()) {
                $icingaCurrentConfig = [System.IO.File]::ReadAllText($this.getIcingaConfigFile());
            }

            [string]$icingaNewConfig =
'/** Icinga 2 Config - proposed by Icinga 2 PowerShell Module */
include "constants.conf"
include <itl>
include <plugins>
include <nscp>
include <windows-plugins>

const PowerShellIcinga2EnableDebug = false;

if (PowerShellIcinga2EnableDebug) {
  object FileLogger "debug-file" {
    severity = "debug"
    path = LocalStateDir + "/log/icinga2/debug.log"
  }
}

if (!globals.contains("NscpPath")) {
  NscpPath = dirname(msi_get_component_path("{5C45463A-4AE9-4325-96DB-6E239C034F93}"))
}
object FileLogger "main-log" {
  severity = "information"
  path = LocalStateDir + "/log/icinga2/icinga2.log"
}

object Endpoint "' + $this.getProperty('local_hostname') + '" {}
' + $this.getProperty('endpoint_objects') + '
object Zone "' + $this.config('parent_zone') + '" {
  endpoints = [ ' + $this.getProperty('endpoint_nodes') +' ]
}

' + $this.getProperty('global_zones') + '
object Zone "' + $this.getProperty('local_hostname') + '" {
  parent = "' + $this.config('parent_zone') + '"
  endpoints = [ "' + $this.getProperty('local_hostname') + '" ]
}

object ApiListener "api" {
  cert_path = SysconfDir + "/icinga2/pki/' + $this.getProperty('local_hostname') + '.crt"
  key_path = SysconfDir + "/icinga2/pki/' + $this.getProperty('local_hostname') + '.key"
  ca_path = SysconfDir + "/icinga2/pki/ca.crt"
  accept_commands = true
  accept_config = ' + $this.convertBoolToString($this.config('accept_config')) + '
  bind_host = "::"
  bind_port = ' + [int]$this.config('agent_listen_port') + '
}';

            $this.setProperty('new_icinga_config', $icingaNewConfig);
            $this.setProperty('old_icinga_config', $icingaCurrentConfig);
        }
    }

    #
    # Generate a hash for old and new config
    # and determine if the configuration has changed
    #
    $installer | Add-Member -membertype ScriptMethod -name 'hasConfigChanged' -value {

        if ($this.getProperty('generate_config') -eq 'false') {
            return $FALSE;
        }
        if (-Not $this.getProperty('new_icinga_config')) {
            throw 'New Icinga 2 configuration not generated. Please call "generateIcingaConfiguration" before.';
        }

        [string]$oldConfigHash = $this.getHashFromString($this.getProperty('old_icinga_config'));
        [string]$newConfigHash = $this.getHashFromString($this.getProperty('new_icinga_config'));

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
        param([string]$text);
        [System.Object]$algorithm = New-Object System.Security.Cryptography.SHA1Managed;
        $hash = [System.Text.Encoding]::UTF8.GetBytes($text);
        $hashInBytes = $algorithm.ComputeHash($hash);
        [string]$result = '';
        foreach($byte in $hashInBytes) {
             $result += $byte.ToString();
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
        param([string]$configData);

        if (-Not (Test-Path $this.getProperty('config_dir'))) {
            $this.warn('Unable to write Icinga 2 configuration. The required directory was not found. Possibly the Icinga 2 Agent is not installed.');
            return;
        }

        # Write new configuration to file
        $this.info('Writing icinga2.conf to ' + $this.getProperty('config_dir'));
        [System.IO.File]::WriteAllText($this.getIcingaConfigFile(), $configData);
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
        param([string]$result, [string]$expected);
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

        if ($this.getProperty('local_hostname') -And $this.config('ca_server') -And $this.getProperty('icinga_ticket')) {
            [string]$icingaPkiDir = $this.getProperty('config_dir') + 'pki\';
            [string]$icingaBinary = $this.getInstallPath() + '\sbin\icinga2.exe';
            [string]$agentName = $this.getProperty('local_hostname');

            if (-Not (Test-Path $icingaBinary)) {
                $this.warn('Unable to generate Icinga 2 certificates. Icinga 2 executable not found. It looks like the Icinga 2 Agent is not installed.');
                return;
            }

            # Generate the certificate
            $this.info('Generating Icinga 2 certificates');

            $result = $this.startProcess($icingaBinary, $FALSE, 'pki new-cert --cn ' + $this.getProperty('local_hostname') + ' --key ' + $icingaPkiDir + $agentName + '.key --cert ' + $icingaPkiDir + $agentName + '.crt');
            if ($result.Get_Item('exitcode') -ne 0) {
                throw $result.Get_Item('message');
            }
            $this.info($result.Get_Item('message'));

            # Save Certificate
            $this.info("Storing Icinga 2 certificates");
            $result = $this.startProcess($icingaBinary, $FALSE, 'pki save-cert --key ' + $icingaPkiDir + $agentName + '.key --trustedcert ' + $icingaPkiDir + 'trusted-master.crt --host ' + $this.config('ca_server'));
            if ($result.Get_Item('exitcode') -ne 0) {
                throw $result.Get_Item('message');
            }
            $this.info($result.Get_Item('message'));

            # Validate if set against a given fingerprint for the CA
            if (-Not $this.validateCertificate($icingaPkiDir + 'trusted-master.crt')) {
                throw 'Failed to validate against CA authority';
            }

            # Request certificate
            $this.info("Requesting Icinga 2 certificates");
            $result = $this.startProcess($icingaBinary, $FALSE, 'pki request --host ' + $this.config('ca_server') + ' --port ' + $this.config('ca_port') + ' --ticket ' + $this.getProperty('icinga_ticket') + ' --key ' + $icingaPkiDir + $agentName + '.key --cert ' + $icingaPkiDir + $agentName + '.crt --trustedcert ' + $icingaPkiDir + 'trusted-master.crt --ca ' + $icingaPkiDir + 'ca.crt');
            if ($result.Get_Item('exitcode') -ne 0) {
                throw $result.Get_Item('message');
            }
            $this.info($result.Get_Item('message'));

            $this.setProperty('require_restart', 'true');
        } else  {
            $this.info('Skipping certificate generation. One or more of the following arguments is not set: -AgentName <name> -CAServer <server> -Ticket <ticket>');
        }
    }

    #
    # Validate against a given fingerprint if we are connected to the correct CA
    #
    $installer | Add-Member -membertype ScriptMethod -name 'validateCertificate' -value {
        param([string] $certificate);

        [System.Object]$certFingerprint = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2;
        $certFingerprint.Import($certificate);
        $this.info('Certificate fingerprint: ' + $certFingerprint.Thumbprint);

        if ($this.config('ca_fingerprint')) {
            if (-Not ($this.config('ca_fingerprint') -eq $certFingerprint.Thumbprint)) {
                $this.error('CA fingerprint does not match! Expected: ' + $this.config('ca_fingerprint') + ', given: ' + $certFingerprint.Thumbprint);
                return $FALSE;
            } else {
                $this.info('CA fingerprint validation successfull');
                return $TRUE;
            }
        }

        $this.warn('CA fingerprint validation disabled');
        return $TRUE;
    }

    #
    # Check the Icinga install directory and determine
    # if the certificates are both available for the
    # Agent. If not, return FALSE
    #
    $installer | Add-Member -membertype ScriptMethod -name 'hasCertificates' -value {
        [string]$icingaPkiDir = $this.getProperty('config_dir') + 'pki\';
        [string]$agentName = $this.getProperty('local_hostname');
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

        return $FALSE;
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
        if (-Not $this.getProperty('local_hostname')) {
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
        param([bool] $checkInternal = $TRUE);
        if (-Not $this.config('parent_zone') -And $checkInternal) {
            throw 'Parent Zone not defined. Please specify it with -ParentZone <name>';
        }
        $icingaBinary = $this.getInstallPath() + '\sbin\icinga2.exe';

        if (Test-Path $icingaBinary) {
            $result = $this.startProcess($icingaBinary, $FALSE, 'daemon -C');
            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error($result.Get_Item('message'));
                return $FALSE;
            }
        } else {
            $this.warn('Icinga 2 config validation not possible. Icinga 2 executable not found. Possibly the Agent is not installed.');
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
        if ($this.hasConfigChanged() -And $this.getProperty('generate_config') -eq 'true') {
            $this.backupDefaultConfig();
            $this.writeConfig($this.getProperty('new_icinga_config'));

            # Check if the config is valid and rollback otherwise
            if (-Not $this.isIcingaConfigValid()) {
                $this.error('Icinga 2 config validation failed. Rolling back to previous version.');
                if (-Not $this.hasCertificates()) {
                    $this.error('Icinga 2 certificates not found. Please generate the certificates over this module or add them manually.');
                }
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
            $this.info('icinga2.conf did not change or required parameters not set. Nothing to do');
        }
    }

    #
    # Enable or disable the Icinga 2 debug log
    #
    $installer | Add-Member -membertype ScriptMethod -name 'switchIcingaDebugLog' -value {
        # In case the config is not valid -> do nothing
        if (-Not $this.isIcingaConfigValid($FALSE)) {
            throw 'Unable to process Icinga 2 debug configuration. The icinga2.conf is corrupt! Please check the icinga2.log';
        }

        # If there is no config file defined -> do nothing
        if (-Not (Test-Path $this.getIcingaConfigFile())) {
            return;
        }

        [string]$icingaCurrentConfig = [System.IO.File]::ReadAllText($this.getIcingaConfigFile());
        [string]$newIcingaConfig = '';

        if ($this.config('icinga_enable_debug_log')) {
            $this.info('Trying to enable debug log for Icinga 2...');
            if ($icingaCurrentConfig.Contains('const PowerShellIcinga2EnableDebug = false;')) {
                $newIcingaConfig = $icingaCurrentConfig.Replace('const PowerShellIcinga2EnableDebug = false;', 'const PowerShellIcinga2EnableDebug = true;');
                $this.info('Icinga 2 debug log has been enabled');
            } else {
                $this.info('Icinga 2 debug log is already enabled or configuration not found');
            }
        } else {
            $this.info('Trying to disable debug log for Icinga 2...');
            if ($icingaCurrentConfig.Contains('const PowerShellIcinga2EnableDebug = true;')) {
                $newIcingaConfig = $icingaCurrentConfig.Replace('const PowerShellIcinga2EnableDebug = true;', 'const PowerShellIcinga2EnableDebug = false;');
                $this.info('Icinga 2 debug log has been disabled');
            } else {
                $this.info('Icinga 2 debug log is not enabled or configuration not found');
            }
        }

        # In case we made a modification to the configuration -> write it
        if ($newIcingaConfig -ne '') {
            $this.writeConfig($newIcingaConfig);
            # Validate the config if it is valid
            if (-Not $this.isIcingaConfigValid($FALSE)) {
                # if not write the old configuration again
                $this.writeConfig($icingaCurrentConfig);
                if (-Not $this.isIcingaConfigValid($FALSE)) {
                    throw 'Critical exception: Something went wrong while processing debug configuration. The Icinga 2 config is corrupt!  Please check the icinga2.log';
                }
            }
        }
    }

    #
    # Ensure we get the hostname or FQDN
    # from the PowerShell to make things more
    # easier
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchHostnameOrFQDN' -value {
        if ($this.config('fetch_agent_fqdn') -And (Get-WmiObject win32_computersystem).Domain) {
            [string]$hostname = (Get-WmiObject win32_computersystem).DNSHostName + '.' + (Get-WmiObject win32_computersystem).Domain;
            $this.setProperty('local_hostname', $hostname);
            $this.info('Setting internal Agent Name to ' + $this.getProperty('local_hostname'));
        } elseif ($this.config('fetch_agent_name')) {
            [string]$hostname = (Get-WmiObject win32_computersystem).DNSHostName;
            $this.setProperty('local_hostname', $hostname);
            $this.info('Setting internal Agent Name to ' + $this.getProperty('local_hostname'));
        }

         # Add additional variables to our config for more user-friendly usage
        [string]$host_fqdn = (Get-WmiObject win32_computersystem).DNSHostName + '.' + (Get-WmiObject win32_computersystem).Domain;
        [string]$hostname = (Get-WmiObject win32_computersystem).DNSHostName;

        $this.setProperty('fqdn', $host_fqdn);
        $this.setProperty('hostname', $hostname);

        if (-Not $this.getProperty('local_hostname')) {
            $this.warn('You have not specified an Agent Name or turned on to auto fetch this information.');
        }
    }

    #
    # Retreive the current IP-Address of the Host
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchHostIPAddress' -value {

        # First try to lookup the IP by the FQDN
        if ($this.doLookupIPAddressesForHostname($this.getProperty('fqdn'))) {
            return;
        }

        # Now take a look for the given hostname
        if ($this.doLookupIPAddressesForHostname($this.getProperty('hostname'))) {
            return;
        }

        # If still nothing is found, look on the entire host
        if ($this.doLookupIPAddressesForHostname("")) {
            return;
        }
    }

    #
    # Add all found IP-Addresses to our property array
    #
    $installer | Add-Member -membertype ScriptMethod -name 'doLookupIPAddressesForHostname' -value {
        param([string]$hostname);

        $this.info('Trying to fetch Host IP-Address for hostname: ' + $hostname);
        try {
            [array]$ipAddressArray = [Net.DNS]::GetHostEntry($hostname).AddressList;
            $this.addHostIPAddressToProperties($ipAddressArray);
            return $TRUE;
        } catch {
            # Write an error in case something went wrong
            $this.error('Failed to lookup IP-Address with DNS-Lookup for ' + $hostname + ': ' + $_.Exception.Message);
        }
        return $FALSE;
    }

    #
    # Add all found IP-Addresses to our property array
    #
    $installer | Add-Member -membertype ScriptMethod -name 'addHostIPAddressToProperties' -value {
        param($ipArray);

        [int]$ipV4Index = 0;
        [int]$ipV6Index = 0;

        foreach ($address in $ipArray) {
            # Split config attributes for IPv4 and IPv6 into different values
            if ($address.AddressFamily -eq 'InterNetwork') { #IPv4
                # If the first entry of our default ipaddress is empty -> add it
                if ($this.getProperty('ipaddress') -eq $null) {
                    $this.setProperty('ipaddress', $address);
                }
                # Now add the IP's with an array like construct
                $this.setProperty('ipaddress[' + $ipV4Index + ']', $address);
                $ipV4Index += 1;
            } else { #IPv6
                # If the first entry of our default ipaddress is empty -> add it
                if ($this.getProperty('ipaddressV6') -eq $null) {
                    $this.setProperty('ipaddressV6', $address);
                }
                # Now add the IP's with an array like construct
                $this.setProperty('ipaddressV6[' + $ipV6Index + ']', $address);
                $ipV6Index += 1;
            }
        }
    }

    #
    # Transform the hostname to upper or lower case if required
    # 0: Do nothing (default)
    # 1: Transform to lower case
    # 2: Transform to upper case
    #
    $installer | Add-Member -MemberType ScriptMethod -name 'doTransformHostname' -Value {
        [string]$hostname = $this.getProperty('local_hostname');
        [int]$type = $this.config('transform_hostname');
        switch ($type) {
            1 { $hostname = $hostname.ToLower(); }
            2 { $hostname = $hostname.ToUpper(); }
            Default {} # Do nothing by default
        }

        if ($hostname -cne $this.getProperty('local_hostname')) {
            $this.info('Transforming Agent Name to ' + $hostname);
        }

        $this.setProperty('local_hostname', $hostname);
    }

    #
    # Allow the replacing of placeholders within a JSON-String
    #
    $installer | Add-Member -MemberType ScriptMethod -name 'doReplaceJSONPlaceholders' -Value {
        param([string]$jsonString);

        # Replace the encoded & with the original symbol at first
        $jsonString = $jsonString.Replace('\u0026', '&');

        # &hostname& => hostname
        $jsonString = $jsonString.Replace('&hostname&', $this.getProperty('hostname'));

        # &hostname.lowerCase& => hostname to lower
        $jsonString = $jsonString.Replace('&hostname.lowerCase&', $this.getProperty('hostname').ToLower());

        # &hostname.upperCase& => hostname to upper
        $jsonString = $jsonString.Replace('&hostname.upperCase&', $this.getProperty('hostname').ToUpper());

        # &fqdn& => fqdn
        $jsonString = $jsonString.Replace('&fqdn&', $this.getProperty('fqdn'));

        # &fqdn.lowerCase& => fqdn to lower
        $jsonString = $jsonString.Replace('&fqdn.lowerCase&', $this.getProperty('fqdn').ToLower());

        # &fqdn.upperCase& => fqdn to upper
        $jsonString = $jsonString.Replace('&fqdn.upperCase&', $this.getProperty('fqdn').ToUpper());

        # hostname_placeholder => current hostname (either FQDN, hostname, with plain, upper or lower case)
        $jsonString = $jsonString.Replace('&hostname_placeholder&', $this.getProperty('local_hostname'));

        # Try to replace our IP-Address
        if ($jsonString.Contains('&ipaddressV6')) {
            $jsonString = $this.doReplaceJSONIPAddress($jsonString, 'ipaddressV6');
        } elseif ($jsonString.Contains('&ipaddress')) {
            $jsonString = $this.doReplaceJSONIPAddress($jsonString, 'ipaddress');
        }

        # Encode the & again to receive a proper JSON
        $jsonString = $jsonString.Replace('&', '\u0026');

        return $jsonString;
    }

    #
    # Allow the replacing of added IPv4 and IPv6 address
    #
    $installer | Add-Member -MemberType ScriptMethod -name 'doReplaceJSONIPAddress' -Value {
        param([string]$jsonString, [string]$ipType);

        # Add our & delimeter to begin with
        [string]$ipSearchPattern = '&' + $ipType;

        # Now locate the string and cut everything away until only our & tag for the string shall be remaining, including the array placeholder
        [string]$ipAddressEnd = $jsonString.Substring($jsonString.IndexOf($ipSearchPattern) + $ipType.Length + 1, $jsonString.Length - $jsonString.IndexOf($ipSearchPattern) - $ipType.Length - 1);
        # Ensure we still got an ending &, otherwise throw an error
        if ($ipAddressEnd.Contains('&')) {
            # Now cut everything until the first & we found
            $ipAddressEnd = $ipAddressEnd.Substring(0, $ipAddressEnd.IndexOf('&'));
            # Build together our IP-Address string, which could be for example ipaddress[1]
            [string]$ipAddressString = $ipType + $ipAddressEnd;

            # Now replace this finding with our config attribute
            $jsonString = $jsonString.Replace('&' + $ipAddressString + '&', $this.getProperty($ipAddressString));
        } else {
            # If something goes wrong we require to notify our user
            $this.error('Failed to replace IP-Address placeholder. Invalid format for IP-Type ' + $ipType);
        }

        # Return our new JSON-String
        return $jsonString;
    }

    #
    # This function will allow us to create a
    # host object directly inside the Icinga Director
    # with a provided JSON string
    #
    $installer | Add-Member -membertype ScriptMethod -name 'createHostInsideIcingaDirector' -value {

        if ($this.config('director_url') -And $this.getProperty('local_hostname')) {
            if ($this.config('director_auth_token')) {
                if ($this.requireIcingaDirectorAPIVersion('1.4.0', '[Function::createHostInsideIcingaDirector]')) {
                    if ($this.getProperty('director_host_token') -eq '') {
                        [string]$apiKey = $this.config('director_auth_token');
                        [string]$url = $this.config('director_url') + 'self-service/register-host?name=' + $this.getProperty('local_hostname') + '&key=' + $apiKey;
                        [string]$json = '';
                        # If no JSON Object is defined (should be default), we shall create one
                        if (-Not $this.config('director_host_object')) {
                            [string]$hostname = $this.getProperty('local_hostname');
                            $json = '{ "address": "' + $hostname + '", "display_name": "' + $hostname + '" }';
                        } else {
                            # Otherwise use the specified one and replace the host object placeholders
                            $json = $this.doReplaceJSONPlaceholders($this.config('director_host_object'));
                        }

                        $this.info('Creating host ' + $this.getProperty('local_hostname') + ' over API token inside Icinga Director.');

                        [string]$httpResponse = $this.createHTTPRequest($url, $json, 'POST', 'application/json', $TRUE, $TRUE);

                        if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                            $this.setProperty('director_host_token', $httpResponse);
                            $this.writeHostAPIKeyToDisk();
                        } else {
                            if ($httpResponse -eq '400') {
                                $this.warn('Received response 400 from Icinga Director. Possibly you tried to re-create the host ' + $this.getProperty('local_hostname') + '. In case the host already exists, please remove the Host-Api-Key inside the Icinga Director and try again.');
                            } else {
                                $this.warn('Failed to create host. Response code ' + $httpResponse);
                            }
                        }
                    }
                }
            } elseif ($this.config('director_host_object'))  {
                # Setup the url we need to call
                [string]$url = $this.config('director_url') + 'host';
                # Replace the host object placeholders
                [string]$host_object_json = $this.doReplaceJSONPlaceholders($this.config('director_host_object'));
                # Create the host object inside the director
                [string]$httpResponse = $this.createHTTPRequest($url, $host_object_json, 'PUT', 'application/json', $FALSE, $FALSE);

                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    $this.info('Placed query for creating host ' + $this.getProperty('local_hostname') + ' inside Icinga Director. Config: ' + $httpResponse);
                } else {
                    if ($httpResponse -eq '422') {
                        $this.warn('Failed to create host ' + $this.getProperty('local_hostname') + ' inside Icinga Director. The host seems to already exist.');
                    } else {
                        $this.error('Failed to create host ' + $this.getProperty('local_hostname') + ' inside Icinga Director. Error response ' + $httpResponse);
                    }
                }
                # Shall we deploy the config for the generated host?
                if ($this.config('director_deploy_config')) {
                    $url = $this.config('director_url') + 'config/deploy';
                    [string]$httpResponse = $this.createHTTPRequest($url, '', 'POST', 'application/json', $FALSE, $TRUE);
                    $this.info('Deploying configuration from Icinga Director to Icinga. Result: ' + $httpResponse);
                }
            }
        }
    }

    #
    # Write Host API-Key for future usage
    #
    $installer | Add-Member -membertype ScriptMethod -name 'writeHostAPIKeyToDisk' -value {
        if (Test-Path ($this.getProperty('config_dir'))) {
            [string]$apiFile = $this.getProperty('config_dir') + 'icingadirector.token';
            $this.info('Writing host API-Key "' + $this.getProperty('director_host_token') + '" to "' + $apiFile + '"');
            [System.IO.File]::WriteAllText($apiFile, $this.getProperty('director_host_token'));
        }
    }

    #
    # Read Host API-Key from disk for usage
    #
    $installer | Add-Member -membertype ScriptMethod -name 'readHostAPIKeyFromDisk' -value {
        [string]$apiFile = $this.getProperty('config_dir') + 'icingadirector.token';
        if (Test-Path ($apiFile)) {
            [string]$hostToken = [System.IO.File]::ReadAllText($apiFile);
            $this.setProperty('director_host_token', $hostToken);
            $this.info('Reading host api token ' + $hostToken + ' from ' + $apiFile);
        } else {
            $this.setProperty('director_host_token', '');
        }
    }

    #
    # Get the API Version from the Icinga Director. In case we are using
    # an older Version of the Director, we wont get this version
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getIcingaDirectorVersion' -value {
        if ($this.config('director_url')) {
            # Do a legacy call to the Icinga Director and get a JSON-Value
            # Older versions of the Director do not support plain/text and
            # would result in making this request quite useless

            [string]$url = $this.config('director_url') + 'self-service/api-version';
            [string]$versionString = $this.createHTTPRequest($url, '', 'POST', 'application/json', $FALSE, $FALSE);

            if ($this.isHTTPResponseCode($versionString) -eq $FALSE) {
                # Remove all characters we do not need inside the string
                [string]$versionString = $versionString.Replace('"', '').Replace("`r", '').Replace("`n", '');
                [array]$version = $versionString.Split('.');
                $this.setProperty('icinga_director_api_version', $versionString);
                return;
            } else {
                $this.warn('You seem to use an older Version of the Icinga Director, as no API version could be retreived.');
                $this.setProperty('icinga_director_api_version', '0.0.0');
                return;
            }
        }
        $this.setProperty('icinga_director_api_version', 'false');
    }

    #
    # Match the Icinga Director API Version against a provided string
    #
    $installer | Add-Member -membertype ScriptMethod -name 'requireIcingaDirectorAPIVersion' -value {
        param([string]$version, [string]$functionName);

        # Director URL not specified
        if ($this.getProperty('icinga_director_api_version') -eq 'false') {
            return $FALSE;
        }

        if ($this.getProperty('icinga_director_api_version') -eq '0.0.0') {
            $this.error('The feature ' + $functionName + ' requires Icinga Director API-Version ' + $version + '. Your Icinga Director version does not support the API.');
            return $FALSE;
        }

        [bool]$versionValid = $TRUE;
        [array]$requiredVersion = $version.Split('.');
        $currentVersion = $this.getProperty('icinga_director_api_version');

        if ($requiredVersion[0] -gt $currentVersion[0]) {
            $versionValid = $FALSE;
        }

        if ($requiredVersion[1] -gt $currentVersion[2]) {
            $versionValid = $FALSE;
        }

        if ($requiredVersion[1] -ge $currentVersion[2] -And $requiredVersion[2] -gt $currentVersion[4]) {
            $versionValid = $FALSE;
        }

        if ($versionValid -eq $FALSE) {
            $this.error('The feature ' + $functionName + ' requires Icinga Director API-Version ' + $version + '. Got version ' + $currentVersion[0] + '.' + $currentVersion[2] + '.' + $currentVersion[4]);
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # This function will fetch all arguments configured inside the Icinga Director
    # to allow an entire auto configuration of the Icinga 2 Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchArgumentsFromIcingaDirector' -value {
        param([bool]$globalConfig);

        # By default we will use the Host-Api-Key stored on the disk (if written on)
        [string]$key = $this.getProperty('director_host_token');

        # In case we are not having the Host-Api-Key already, use the value from the argument
        if($globalConfig -eq $TRUE) {
             $key = $this.config('director_auth_token');
        }

        # If no key is specified, we are not having set one and should leave this function
        if ($key -eq '') {
            return;
        }

        if ($this.requireIcingaDirectorAPIVersion('1.4.0', '[Function::fetchArgumentsFromIcingaDirector]')) {
            [string]$url = $this.config('director_url') + 'self-service/powershell-parameters?key=' + $key;
            [string]$argumentString = $this.createHTTPRequest($url, '', 'POST', 'application/json', $TRUE, $FALSE);

            if ($this.isHTTPResponseCode($argumentString) -eq $FALSE) {
                # First split the entire result based in new-lines into an array
                [array]$arguments = $argumentString.Split("`n");
                $config = @{};

                # Now loop all elements and construct a dictionary for all values
                foreach ($item in $arguments) {
                    if ($item.Contains(':')) {
                        [int]$argumentPos = $item.IndexOf(":");
                        [string]$argument = $item.Substring(0, $argumentPos)
                        [string]$value = $item.Substring($argumentPos + 2, $item.Length - 2 - $argumentPos);
                        $value = $value.Replace("`r", '');
                        $value = $value.Replace("`n", '');

                        if ($value.Contains( '!')) {
                            [array]$valueArray = $value.Split('!');
                            $this.overrideConfig($argument, $valueArray);
                        } else {
                            if ($value.toLower() -eq 'true') {
                                $this.overrideConfig($argument, $TRUE);
                            } elseif ($value.toLower() -eq 'false') {
                                $this.overrideConfig($argument, $FALSE);
                            } else {
                                $this.overrideConfig($argument, $value);
                            }
                        }
                    }
                }
            } else {
                $this.error('Received ' + $argumentString + ' from Icinga Director. Possibly your API token is no longer valid or the object does not exist.');
            }
            # Ensure we generate the required configuration content
            $this.generateConfigContent();
        }
    }

    #
    # This function will communicate directly with
    # the Icinga Director and ensuring that we get
    # some of the possible required informations
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchTicketFromIcingaDirector' -value {

        if ($this.getProperty('director_host_token')) {
            if ($this.requireIcingaDirectorAPIVersion('1.4.0', '[Function::fetchTicketFromIcingaDirector]')) {
                [string]$url = $this.config('director_url') + 'self-service/ticket?key=' + $this.getProperty('director_host_token');
                [string]$httpResponse = $this.createHTTPRequest($url, '', 'POST', 'application/json', $TRUE, $TRUE);
                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    $this.setProperty('icinga_ticket', $httpResponse);
                } else {
                    $this.error('Failed to fetch Ticket from Icinga Director. Error response ' + $httpResponse);
                }
            }
        } else {
            if ($this.config('director_url') -And $this.getProperty('local_hostname')) {
                [string]$url = $this.config('director_url') + 'host/ticket?name=' + $this.getProperty('local_hostname');
                [string]$httpResponse = $this.createHTTPRequest($url, '', 'POST', 'application/json', $FALSE, $TRUE);

                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    # Lookup all " inside the return string
                    $quotes = Select-String -InputObject $httpResponse -Pattern '"' -AllMatches;

                    # If we only got two ", we should have received a valid ticket
                    # Otherwise we need to throw an error
                    if ($quotes.Matches.Count -ne 2) {
                        throw 'Failed to fetch ticket for host ' + $this.getProperty('local_hostname') +'. Got ' + $httpResponse + ' as ticket.';
                    } else {
                        $httpResponse = $httpResponse.subString(1, $httpResponse.length - 3);
                        $this.info('Fetched ticket ' + $httpResponse + ' for host ' + $this.getProperty('local_hostname') + '.');
                        $this.setProperty('icinga_ticket', $httpResponse);
                    }
                } else {
                    $this.error('Failed to fetch Ticket from Icinga Director. Error response ' + $httpResponse);
                }
            }
        }
    }

    #
    # Shall we install the NSClient as well on the system?
    # All possible actions are handeled here
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installNSClient' -value {

        if ($this.config('install_nsclient')) {

            [string]$installerPath = $this.getNSClientInstallerPath();
            $this.info('Trying to install NSClient++ from ' + $installerPath);

            # First check if the package does exist
            if (Test-Path ($installerPath)) {

                # Get all required arguments for installing the NSClient unattended
                [string]$NSClientArguments = $this.getNSClientInstallerArguments();

                # Start the installer process
                $result = $this.startProcess('MsiExec.exe', $TRUE, '/quiet /i "' + $installerPath + '" ' + $NSClientArguments);

                # Exit Code 0 means the NSClient was installed successfully
                # Otherwise we require to throw an error
                if ($result.Get_Item('exitcode') -ne 0) {
                    $this.error('Failed to install NSClient++. ' + $result.Get_Item('message'));
                } else {
                    $this.info('NSClient++ successfully installed.');
                }

                # If defined remove the Firewall Rule to secure the system
                # By default the NSClient is only called from the Icinga 2 Agent locally
                $this.removeNSClientFirewallRule();
                # Remove the service if we only call the NSClient locally
                $this.removeNSClientService();
                # Add the default NSClient config if we want to do more
                $this.addNSClientDefaultConfig();
                # To tell Icinga 2 we installed the NSClient and to make
                # the NSCPPath variable available, we require to restart Icinga 2
                $this.setProperty('require_restart', 'true');
            } else {
                $this.error('Failed to locate NSClient++ Installer at ' + $installerPath);
            }
        } else {
            $this.info('NSClient++ will not be installed on the system.');
        }
    }

    #
    # Determine the location of the NSClient installer
    # By default we are using the shipped NSClient from the Icinga 2 Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getNSClientInstallerPath' -value {

        if ($this.config('nsclient_installer_path') -ne '') {

            # Check of the installer is a local path
            # If so, use this as installer source
            if (Test-Path ($this.config('nsclient_installer_path'))) {
                return $this.config('nsclient_installer_path');
            }

            $this.info('Trying to download NSClient++ from ' + $this.config('nsclient_installer_path'));
            [System.Object]$client = New-Object System.Net.WebClient;
            $client.DownloadFile($this.config('nsclient_installer_path'), $Env:temp + '\NSCP.msi');

            return $Env:temp + '\NSCP.msi';
        } else {
            # Icinga is shipping a NSClient Version after installation
            # Install this version if defined
            return $this.getInstallPath() + '\sbin\NSCP.msi';
        }

        return '';
    }

    #
    # If we only want to use the NSClient++ to be called from the Icinga 2 Agent
    # we do not require an open Firewall Rule to allow traffic.
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getNSClientInstallerArguments' -value {
        [string]$NSClientArguments = '';

        if ($this.config('nsclient_directory')) {
            $NSClientArguments += ' INSTALLLOCATION=' + $this.config('nsclient_directory');
        }

        return $NSClientArguments;
    }

    #
    # If we only want to use the NSClient++ to be called from the Icinga 2 Agent
    # we do not require an open Firewall Rule to allow traffic.
    #
    $installer | Add-Member -membertype ScriptMethod -name 'removeNSClientFirewallRule' -value {
        if ($this.config('nsclient_firewall') -eq $FALSE) {

            $result = $this.startProcess('netsh', $FALSE, 'advfirewall firewall show rule name="NSClient++ Monitoring Agent"');
            if ($result.Get_Item('exitcode') -ne 0) {
                # Firewall rule was not found. Nothing to do
                $this.info('NSClient++ Firewall Rule is not installed');
                return;
            }

            $this.info('Trying to remove NSClient++ Firewall Rule...');

            $result = $this.startProcess('netsh', $TRUE, 'advfirewall firewall delete rule name="NSClient++ Monitoring Agent"');

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error('Failed to remove NSClient++ Firewall rule: ' + $result.Get_Item('message'));
            } else {
                $this.info('NSClient++ Firewall Rule has been successfully removed');
            }
        }
    }

    #
    # If we only want to use the NSClient++ to be called from the Icinga 2 Agent
    # we do not require a running NSClient++ Service
    #
    $installer | Add-Member -membertype ScriptMethod -name 'removeNSClientService' -value {
        if ($this.config('nsclient_service') -eq $FALSE) {
            $NSClientService = Get-WmiObject -Class Win32_Service -Filter "Name='nscp'";
            if ($NSClientService -ne $null) {
                $this.info('Trying to remove NSClient++ service...');
                # Before we remove the service, stop it (to prevent ghosts)
                Stop-Service 'nscp';
                # Now remove it
                $result = $NSClientService.delete();
                if ($result.ReturnValue -eq 0) {
                    $this.info('NSClient++ Service has been removed');
                } else {
                    $this.error('Failed to remove NSClient++ service');
                }
            } else {
                $this.info('NSClient++ Service is not installed')
            }
        }
    }

    #
    # In case we want to do more with the NSClient, we can auto-generate
    # all NSClient++ config attributes
    #
    $installer | Add-Member -membertype ScriptMethod -name 'addNSClientDefaultConfig' -value {
        if ($this.config('nsclient_add_defaults')) {
            [string]$NSClientBinary = $this.getNSClientDefaultExecutablePath();

            if ($NSClientBinary -eq '') {
                $this.error('Unable to generate NSClient++ default config. Executable nscp.exe could not be found ' +
                             'on default locations or the specified custom location. If you installed the NSClient on a ' +
                             'custom location, please specify the path with -NSClientDirectory');
                return;
            }

            if (Test-Path ($NSClientBinary)) {
                $this.info('Generating all default NSClient++ config values');
                $result = $this.startProcess($NSClientBinary, $TRUE, 'settings --generate --add-defaults --load-all');
                if ($result.Get_Item('exitcode') -ne 0) {
                    $this.error($result.Get_Item('message'));
                }
            } else {
                $this.error('Failed to generate NSClient++ defaults config. Path to executable is not valid: ' + $NSClientBinary);
            }
        }
    }

    #
    # Deprecated function
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installIcinga2Agent' -value {
        $this.warn('The function "installIcinga2Agent" is deprecated and will be removed soon. Please use "installMonitoringComponents" instead.')
        return $this.installMonitoringComponents();
    }

    #
    # This function will try to load all
    # data from the system and setup the
    # entire Agent without user interaction
    # including download and update if
    # specified. Returnd 0 or 1 as exit code
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installMonitoringComponents' -value {
        try {
            if (-Not $this.isAdmin()) {
                return 1;
            }

            # Get the current API-Version from the Icinga Director
            $this.getIcingaDirectorVersion();
            # Read arguments for auto config from the Icinga Director
            # At first only with our public key for global config attributes
            $this.fetchArgumentsFromIcingaDirector($TRUE);
            # Read the Host-API Key in case it exists
            $this.readHostAPIKeyFromDisk();
            # Get host name or FQDN if required
            $this.fetchHostnameOrFQDN();
            # Get IP-Address of host
            $this.fetchHostIPAddress();
            # Transform the hostname if required
            $this.doTransformHostname();
            # Try to create a host object inside the Icinga Director
            $this.createHostInsideIcingaDirector();
            # Load the configuration again, but this time with our
            # Host key to fetch additional informations like endpoints
            $this.fetchArgumentsFromIcingaDirector($FALSE);
            # First check if we should get some parameters from the Icinga Director
            $this.fetchTicketFromIcingaDirector();

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
                if ($this.canInstallAgent()) {
                    $this.installAgent();
                    $this.cleanupAgentInstaller();
                    # In case we have an API key assigned, write it to disk
                    $this.writeHostAPIKeyToDisk();
                } else {
                    $this.warn('Icinga 2 Agent is not installed and not allowed of beeing installed.');
                }
            }

            if (-Not $this.hasCertificates() -Or $this.forceCertificateGeneration()) {
                $this.generateCertificates();
            } else {
                $this.info('Icinga 2 certificates already exist. Nothing to do.');
            }

            if ($this.shouldFlushIcingaApiDirectory()) {
                $this.flushIcingaApiDirectory();
            }

            $this.generateIcingaConfiguration();
            $this.applyPossibleConfigChanges();
            $this.switchIcingaDebugLog();
            $this.installIcingaAgentFirewallRule();
            $this.installNSClient();

            if ($this.madeChanges()) {
                $this.restartAgent();
            } else {
                $this.info('No changes detected.');
            }

            # We modify the service user at the very last to ensure
            # the user we defined for logging in is valid
            $this.modifyIcingaServiceUser();
            return 0;
        } catch {
            $this.printLastException();
            return 1;
        }
    }

    #
    # Removes the Icinga 2 Agent from the system
    #
    $installer | Add-Member -membertype ScriptMethod -name 'uninstallIcinga2Agent' -value {
        $this.info('Trying to locate Icinga 2 Agent...');

        if ($this.isAgentInstalled()) {
            $this.info('Removing Icinga 2 Agent from the system...');
            $result = $this.startProcess('MsiExec.exe', $TRUE, $this.getProperty('uninstall_id') + ' /q');

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error($result.Get_Item('message'));
                return [int]$result.Get_Item('exitcode');
            }

            $this.info('Icinga 2 Agent successfully removed.');
        }

        if ($this.config('full_uninstallation')) {
            $this.info('Flushing Icinga 2 program data directory...');
            if (Test-Path ($Env:ProgramData + '\icinga2\')) {
                try {
                    [System.Object]$folder = New-Object -ComObject Scripting.FileSystemObject;
                    $folder.DeleteFolder($Env:ProgramData + '\icinga2');
                    $this.info('Remaining Icinga 2 configuration successfully removed.');
                } catch {
                    $this.error('Failed to delete Icinga 2 Program Data Directory: ' + $_.Exception.Message);
                    return 1;
                }
            } else {
                $this.warn('Icinga 2 Agent program directory not present.');
            }
        }

        return 0;
    }

    return $installer;
}