<#
.Synopsis
   Icinga 2 PowerShell Module - the most flexible and easy way to configure and install Icinga 2 Agents on Windows. 
.DESCRIPTION
   More Information on https://github.com/Icinga/icinga2-powershell-module
.EXAMPLE
       exit $icinga = Icinga2AgentModule `
                   -AgentName        'windows-host-name' `
                   -Ticket           '3459843583450834508634856383459' `
                   -ParentZone       'icinga-master' `
                   -ParentEndpoints  'icinga2a', 'icinga2b' `
                   -CAServer         'icinga-master' `
                   -RunInstaller;
 .NOTES

#>
function Icinga2AgentModule {

    #
    # Setup parameters which can be accessed
    # with -<ParamName>
    #
    [CmdletBinding()]
    param(

        # This is in general the name of your Windows host. It will have to match with your Icinga configuration, as it is part of the Icinga 2 Ticket and Certificate handling to ensure a valid certificate is generated
        [string]$AgentName,
        # The Ticket you will receive from your Icinga 2 CA. In combination with the Icinga Director, it will tell you which Ticket you will require for your host
        [string]$Ticket,
        # You can either leave this parameter or add it to allow the module to install or update the Icinga 2 Agent on your system
        [string]$InstallAgentVersion,
        # Instead of setting the Agent Name with -AgentName, the PowerShell module is capable of retreiving the information automaticly from Windows. Please note this is not the FQDN
        [switch]$FetchAgentName             = $FALSE,
        # Like -FetchAgentName, this argument will ensure the hostname is set inside the script, will however include the domain to provide the FQDN internally.
        [switch]$FetchAgentFQDN             = $FALSE,
        # Allows to transform the hostname to either lower or upper case if required. 0: Do nothing 1: To lower case 2: To upper case
        [int]$TransformHostname             = -1,

        # This variable allows to specify on which port the Icinga 2 Agent will listen on
        [int]$AgentListenPort               = -1,
        # Each Icinga 2 Agent is in general forwarding it's check results to a parent master or satellite zone. Here you will have to specify the name of the parent zone
        [string]$ParentZone,#
        # Icinga 2 internals to make it configurable if the Agent is accepting configuration from the Icinga config master.
        [int]$AcceptConfig                  = -1,
        # This argument will define if the Icinga 2 debug log will be enabled or disabled.
        [switch]$IcingaEnableDebugLog       = $FALSE,
        # This argument will define if we enable or disable the Icinga 2 logging feature
        [switch]$IcingaDisableLogging       = $FALSE,
        # Allows to specify if the PowerShell Module will add a firewall rule, allowing Icinga 2 masters or Satellites to connect to the Icinga 2 Agent on the defined port
        [switch]$AgentAddFirewallRule       = $FALSE,
        # This parameter requires an array of string values, to which endpoints the Agent should in general connect to. If you are only having one endpoint, only add one. You will have to specify all endpoints the Agent requires to connect to
        [array]$ParentEndpoints,
        # While -ParentEndpoints will define the name of endpoints by an array, this parameter will allow to assign IP address and port configuration, allowing the Icinga 2 Agent to directly connect to parent Icinga 2 instances. To specify IP address and port, you will have to seperate these entries by using ';' without blank spaces. The order of the config has to match the assignment of -ParentEndpoints. You can specify the IP address only without a port definition by just leaving the last part. If you wish to not specify a config for a specific endpoint, simply add an empty string to the correct location.
        [array]$EndpointsConfig,
        # Allows to specify global zones, which will be added into the icinga2.conf. Note: In case no global zone will be defined, director-global will be added by default. If you specify zones by yourself, please ensure to add director-global as this is not done automaticly when adding custom global-zones.
        [array]$GlobalZones                 = @(),


        # Agent installation / update
        <# This argument will allow to override the user the Icinga 2 service is running with. Windows provides some basic users already which can be configured:

        LocalSystem
        NT AUTHORITY\NetworkService (Icinga default)
        NT AUTHORITY\LocalService
        If you require an own user, you can add that one as well for the argument. If a password is required for the user to login, seperate username and password with a ':'.

        Example: jdoe:mysecretpassword

        Furthermore you can also use domains in combination and pass them over.

        Example: icinga\jdoe:mysecretpassword[string]$IcingaServiceUser,
        #>
        [string]$IcingaServiceUser,
        #With this parameter you can define a download Url or local directory from which the module will download/install a specific Icinga 2 Agent MSI Installer package. Please ensure to only define the base download Url / Directory, as the Module will generate the MSI file name based on your operating system architecture and the version to install. The Icinga 2 MSI Installer name is internally build as follows: Icinga2-v[InstallAgentVersion]-[OSArchitecture].msi

        # Full example: Icinga2-v2.8.0-x86_64.msi
        [string]$DownloadUrl,
        # Allows to specify in which directory the Icinga 2 Agent will be installed into. In case of an Agent update you can specify with this argument a new directory the new Agent will be installed into. The old directory will be removed caused by the required uninstaller process.
        [string]$AgentInstallDirectory,
        # In case the Icinga 2 Agent is already installed on the system, this parameter will allow you to configure if you wish to upgrade / downgrade to a specified version with the -InstallAgentVersion parameter as well. If none of both parameters is defined, the module will not update or downgrade the agent.
        # If argument -AgentInstallDirectory is not specified, the Icinga 2 Agent will be installed into the same directory as before. In case defined, the PowerShell Module will use the new directory as installation target.
        [switch]$AllowUpdates               = $FALSE,
        # To ensure downloaded packages are build by the Icinga Team and not compromised by third parties, you will be able to provide an array of SHA1 hashes here. In case you have defined any hashses, the module will not continue with updating / installing the Agent in case the SHA1 hash of the downloaded MSI package is not matching one of the provided hashes of this parameter.
        [array]$InstallerHashes,
        # In case the Icinga Agent will accept configuration from the parent Icinga 2 system, it will possibly write data to /var/lib/icinga2/api/* By adding this parameter to your script call, all content inside the api directory will be flushed once a change is detected by the module which requires a restart of the Icinga 2 Agent
        [switch]$FlushApiDirectory          = $FALSE,

        # Here you can provide a string to the Icinga 2 CA or any other CA responsible to generate the required certificates for the SSL communication between the Icinga 2 Agent and it's parent
        [string]$CAServer,
        # TODO
        [string]$CACertificatePath,
        # Here you can specify a custom port in case your CA Server is not listening on 5665 
        [int]$CAPort                        = 5665,
        # The module will generate the certificates in general only if one of the required files is missing. By adding this parameter to your call, the module will force the re-creation of the certificates.
        [switch]$ForceCertificateGeneration = $FALSE,
        # This option will allow the validation of the trusted-master.crt generated during certificate generation, to ensure we are connected to the correct endpoint to prevent possible man-in-the-middle attacks.
        [string]$CAFingerprint,
        # Use this switch to enable the CAProxy feature Introduced with Icinga 2.8
        [switch]$CAProxy                    = $FALSE,

        # Director communication
        #This argument will tell the PowerShell where the Icinga Director can be found. Please specify the entire path to the Icinga Director! Example: https://example.com/icingaweb2/director/
        [string]$DirectorUrl,
        #To fetch the Ticket for a host, creating host objects or deploying the configuration you will have to authenticate against the Icinga Director. This parameter allows to set the User we shall use to login.
        [string]$DirectorUser,
        # To fetch the Ticket for a host, creating host objects or deploying the configuration you will have to authenticate against the Icinga Director. This parameter allows to set the Password we shall use to login.
        [string]$DirectorPassword,
        # TODO
        [string]$DirectorDomain,
        # API key for specific host templates, allowing the configuration and creation of host objects within the Icinga Director without password authentication. This is the API token assigned to a host template. Hosts created with this token, will automaticly receive the Host-Template assigned to the API key. Furthermore this token allows to access the Icinga Director Self-Service API to fetch basic arguments for the module.
        # Note: This argument requires Icinga Director API Version 1.4.0 or higher
        [string]$DirectorAuthToken,
        # This argument allows you to parse either a valid JSON-String or an hashtable / array, containing all informations for the host object to create. Please note that using arrays or hashtable objects for this argument will require PowerShell version 3 and above.
        [System.Object]$DirectorHostObject,
        # If you add this parameter to your script call, the PowerShell module will tell the Icinga Director to deploy outstanding configurations. This parameter can be used in combination with -DirectorHostObject, to create objects and deploy them right away. This argument requires the user and password argument and will not work with the Self Service api.
        # Caution: If set, all outstanding deployments inside the Icinga Director will be deployed. Use with caution!!!
        [switch]$DirectorDeployConfig       = $FALSE,

        # NSClient Installer
        [switch]$InstallNSClient            = $FALSE,
        [switch]$NSClientAddDefaults        = $FALSE,
        [switch]$NSClientEnableFirewall     = $FALSE,
        [switch]$NSClientEnableService      = $FALSE,
        [string]$NSClientDirectory,
        [string]$NSClientInstallerPath,

        # Uninstaller arguments
        # This argument is only used by the function 'uninstall' and will remove the remaining content from 'C:\Program Data\icinga2' to prepare a clean setup of the Icinga 2 infrastrucure.
        [switch]$FullUninstallation         = $FALSE,
        # When this argument is set, the installed NSClient++ will be removed from the system as well. This argument is only used by calling the function 'uninstall'
        [switch]$RemoveNSClient             = $FALSE,

        # Dump Icinga Config 
        [switch]$DumpIcingaConfig           = $FALSE,
        # Dump Icinga Objects
        [switch]$DumpIcingaObjects          = $FALSE,

        #Internal handling
        # This argument allows to shorten the entire call of the module, not requiring to define a custom variable and executing the installation function of the monitoring components.
        [switch]$RunInstaller               = $FALSE,
        # This argument allows to shorten the entire call of the module, not requiring to define a custom variable and executing the uninstallation function of the monitoring components.
        [switch]$RunUninstaller             = $FALSE,
        #In certain cases it could be required to ingore SSL certificate validations from the Icinga Web 2 installation (for example in case self-signed certificates are used). By default the PowerShell Module is validating SSL certificates and throws an error if the validation fails.
        #In case self-signed certificates are used and not included to the local certificate store of the Windows machine, the module will fail. By providing this argument, validation will always be valid and the script will execute as if the certificate was valid.
        [switch]$IgnoreSSLErrors            = $FALSE,

        [switch]$DebugMode                  = $FALSE,
        # Specify a path to either a directory or a file to write all output from the PowerShell module into a file for later debugging. In case a directory is specified, the script will automaticly create a new file with a unique name into it. If a file is specified which is not yet present, it will be created.
        [string]$ModuleLogFile
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
        icinga_disable_log      = $IcingaDisableLogging;
        agent_add_firewall_rule = $AgentAddFirewallRule;
        parent_endpoints        = $ParentEndpoints;
        endpoints_config        = $EndpointsConfig;
        global_zones            = $GlobalZones;
        icinga_service_user     = $IcingaServiceUser;
        download_url            = $DownloadUrl;
        agent_install_directory = $AgentInstallDirectory;
        allow_updates           = $AllowUpdates;
        installer_hashes        = $InstallerHashes;
        flush_api_directory     = $FlushApiDirectory;
        ca_server               = $CAServer;
        ca_certificate_path     = $CACertificatePath;
        ca_port                 = $CAPort;
        force_cert              = $ForceCertificateGeneration;
        ca_fingerprint          = $CAFingerprint;
        caproxy                 = $CAProxy;
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
        remove_nsclient         = $RemoveNSClient;
        ignore_ssl_errors       = $IgnoreSSLErrors;
        debug_mode              = $DebugMode;
        module_log_file         = $ModuleLogFile;
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
    # In case we run the script not through Icinga Director, we might want to set
    # script default values
    #
    $installer | Add-Member -membertype ScriptMethod -name 'setScriptDefaultVariables' -value {
        if ($this.cfg['transform_hostname'] -eq -1) {
            $this.cfg['transform_hostname'] = 0;
            $this.debug('Setting "transform_hostname" to default 0');
        }
        if ($this.cfg['download_url'] -eq '') {
            $this.cfg['download_url'] = 'https://packages.icinga.com/windows/';
            $this.debug('Setting "download_url" to default "https://packages.icinga.com/windows/"');
        }
        if ($this.cfg['agent_listen_port'] -eq -1) {
            $this.cfg['agent_listen_port'] = 5665;
            $this.debug('Setting "agent_listen_port" to default 5665');
        }
        if ($this.cfg['global_zones'].Count -eq 0) {
            $this.cfg['global_zones'] = @( 'director-global' );
            $this.debug('Setting "global_zones" to default "director-global"');
        }
        if ($this.cfg['accept_config'] -eq -1) {
            $this.cfg['accept_config'] = $TRUE;
            $this.debug('Setting "accept_config" to default "true"');
        }
    }

    #
    # Override the given arguments of the PowerShell script with
    # custom values or edited values
    #
    $installer | Add-Member -membertype ScriptMethod -name 'overrideConfig' -value {
        param([string] $key, $value, $keepScriptArguments);

        # Ensure the director will not override our custom config for arguments
        if ($keepScriptArguments) {
            $scriptValue = $this.cfg[$key];

            if ([string]::IsNullOrEmpty($scriptValue) -eq $FALSE) {
                if ($scriptValue.GetType().Name -eq 'SwitchParameter' -And $scriptValue -eq $TRUE) {
                    $this.debug("Skipping overriding of '$key', as set by script. [$scriptValue]");
                    return;
                }

                if ($scriptValue.GetType().Name -eq 'SwitchParameter' -And $scriptValue -eq $FALSE) {
                    # Do not keep value
                } elseif ($scriptValue.GetType().Name -eq 'Int32' -And $scriptValue -eq -1) {
                    # Do not keep value
                } elseif ([string]::IsNullOrEmpty($scriptValue) -eq $FALSE) {
                    $this.debug("Skipping overriding of '$key', as set by script. [$scriptValue]");
                    return;
                } else {
                    $this.debug("Skipping overriding of '$key', as set by script. [$scriptValue]");
                    return;
                }
            }
        }

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
        param([string]$key, $value);

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
        [string]$dumpData = $this.properties | Out-String;
        $this.debug('Dumping properties...');
        $this.debug($dumpData);
    }

    #
    # Dump all configured arguments for easier debugging
    #
    $installer | Add-Member -membertype ScriptMethod -name 'dumpConfig' -value {
        [string]$dumpData = $this.cfg | Out-String;
        $this.debug('Dumping config...');
        $this.debug($dumpData);
    }

    #
    # Write all output from consoles to a logfile
    #
    $installer | Add-Member -membertype ScriptMethod -name 'writeLogFile' -value {
        param([string]$severity, [string]$content);

        # If no logfile is specified, do nothing
        if (-Not $this.config('module_log_file')) {
            return;
        }

        # Store our logfile into a variable
        $logFile = $this.config('module_log_file');

        # Have we specified a directory to write into or a file already?
        try {
            # Check if we are a directory or a file
            # Will return false for files or non-existing files
            $directory = (Get-Item $logFile) -is [System.IO.DirectoryInfo];
        } catch {
            # Nothing to catch. Simply get rid of error messages from aboves function in case of error
            # Will return false anyways on error
        }

        # If we are a directory, add a file we can write to
        if ($directory) {
            $logFile = Join-Path -Path $logFile -ChildPath 'icinga2agent_psmodule.log';
        }

        # Format a timestamp to get to know the exact date and time. Example: 2017-13-07 22:09:13.263.263
        $timestamp = Get-Date -Format "yyyy-dd-MM HH:mm:ss.fff";
        $content = [string]::Format('{0} [{1}]: {2}', $timestamp, $severity, $content);

        # Write the content to our logfile
        Add-Content -Path $logFile -Value $content;
    }

    #
    # This function will print messages as errors, but add them internally to
    # an exception list. These will re-printed at the end to summarize possible
    # issues during the run
    #
    $installer | Add-Member -membertype ScriptMethod -name 'exception' -value {
        param([string]$message, [string[]]$args);
        [array]$exceptions = $this.getProperty('exception_messages');
        if ($exceptions -eq $null) {
            $exceptions = @();
        }
        $exceptions += $message;
        $this.setProperty('exception_messages', $exceptions);
        write-host 'Fatal:' $message -ForegroundColor red;
        $this.writeLogFile('fatal', $message);
    }

    #
    # Get the current exit code of the script. Return 0 for no errors and 1 for
    # possible errors, including a summary of what went wrong
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getScriptExitCode' -value {
        [array]$exceptions = $this.getProperty('exception_messages');

        $this.dumpProperties();
        $this.dumpConfig();

        if ($exceptions -eq $null) {
            return 0;
        }

        $this.writeLogFile('fatal', '##################################################################');
        $message = '######## The script encountered several errors during run ########';
        $this.writeLogFile('fatal', $message);
        $this.writeLogFile('fatal', '##################################################################');
        write-host $message -ForegroundColor red;
        foreach ($err in $exceptions) {
            write-host 'Fatal:' $err -ForegroundColor red;
            $this.writeLogFile('fatal', $err);
        }

        return 1;
    }

    #
    # Print the relevant exception
    # By reading the relevant info
    # from the stack
    #
    $installer | Add-Member -membertype ScriptMethod -name 'printLastException' -value {
        $this.exception($_.Exception.Message);
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
        $this.writeLogFile('error', $message);
    }

    #
    # Return a warning message with yellow text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'warn' -value {
        param([string] $message, [array] $args);
        Write-Host 'Warning:' $message -ForegroundColor yellow;
        $this.writeLogFile('warning', $message);
    }

    #
    # Return a info message with green text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'info' -value {
        param([string] $message, [array] $args);
        Write-Host 'Notice:' $message -ForegroundColor green;
        $this.writeLogFile('info', $message);
    }

    #
    # Return a output message with wrhite text
    #
    $installer | Add-Member -membertype ScriptMethod -name 'output' -value {
        param([string] $message, [array] $args);
        Write-Host '' $message -ForegroundColor white;
        $this.writeLogFile('', $message);
    }

    #
    # Return a debug message with blue text
    # in case debug mode is enabled
    #
    $installer | Add-Member -membertype ScriptMethod -name 'debug' -value {
        param([string] $message, [array] $args);
        if ($this.config('debug_mode')) {
            Write-Host 'Debug:' $message -ForegroundColor blue;
            $this.writeLogFile('debug', $message);
        }
    }

    #
    # Initialise certain parts of the
    # script first
    #
    $installer | Add-Member -membertype ScriptMethod -name 'init' -value {
        $this.setProperty('initialized', $TRUE);
        # Set the default config dir
        $this.setProperty('config_dir', (Join-Path -Path $Env:ProgramData -ChildPath 'icinga2\etc\icinga2\'));
        $this.setProperty('api_dir', (Join-Path -Path $Env:ProgramData -ChildPath 'icinga2\var\lib\icinga2\api'));
        $this.setProperty('cert_dir', (Join-Path -Path $Env:ProgramData -ChildPath 'icinga2\var\lib\icinga2\certs'));
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
            $config_string += [string]::Format('  host = "{0}"', $configObject[0]);
        }

        # Write the port data from the second array position
        if ($configObject[1]) {
            $config_string += [string]::Format('{0}  port = {1}', "`n", $configObject[1]);
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
                $endpoint_objects += [string]::Format('object Endpoint "{0}" {1}{2}', $endpoint, '{', "`n");
                $endpoint_objects += $this.getEndpointConfigurationByArrayIndex($endpoint_index);
                $endpoint_objects += [string]::Format('{0}{1}{2}', "`n", '}', "`n");
                $endpoint_nodes += [string]::Format('"{0}", ', $endpoint);
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
        [string]$zones = '';

        # In case no zones are given, simply add director-global
        if ($global_zones -eq $NULL) {
            $this.setProperty('global_zones', $zones);
            return;
        }

        # Loop through all given zones and add them to our configuration
        foreach ($zone in $global_zones) {
            if ($zone -ne '') {
                $zones += [string]::Format('object Zone "{0}" {1}{2} global = true{3}{4}{5}', $zone, '{', "`n", "`n", '}', "`n");
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

        # If we are using self-signed certificates for example, the HTTP request will
        # fail caused by the SSL certificate. With this we can allow even faulty
        # certificates. This should be used with caution
        if ($this.config('ignore_ssl_errors')) {
            [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
        }

        if ($this.config('director_user') -And $this.config('director_password')) {
            [string]$credentials = [System.Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes([string]::Format('{0}:{1}', $this.config('director_user'), $this.config('director_password'))));
            $httpRequest.Headers.add([string]::Format('Authorization: Basic {0}', $credentials));
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
            if ($exceptionMessage.StatusCode) {
                return [int][System.Net.HttpStatusCode]$exceptionMessage.StatusCode;
            } else {
                return 900;
            }
        }

        return '';
    }

    #
    # Read the content of a response and return it's value as a string
    #
    $installer | Add-Member -membertype ScriptMethod -name 'readResponseStream' -value {
        param([System.Object]$response);

        if ($response) {
            $responseStream = $response.getResponseStream();
            $streamReader = New-Object IO.StreamReader($responseStream);
            $result = $streamReader.ReadToEnd();
            $response.close()
            $streamReader.close()

            return $result;
        }

        $this.exception('Could not retreive response from remote server. Response is null. This might be caused by SSL errors. Please try using -IgnoreSSLErrors as argument and try again.');
        return 'No response from remote server';
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

        $this.info([string]::Format('Current Icinga 2 Agent Version ({0}) is not matching server version ({1}). Downloading new version...'), $this.getProperty('agent_version'), $this.config('agent_version'));

        return $TRUE;
    }

    #
    # We could try to install the Agent from a local directory
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isDownloadPathLocal' -value {
        if ($this.config('download_url') -And (Test-Path ($this.config('download_url')))) {
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
            $this.info([string]::Format('Downloading Icinga 2 Agent Binary from "{0}"', $url));

            Try {
                [System.Object]$client = New-Object System.Net.WebClient;
                $client.DownloadFile($url, $this.getInstallerPath());

                if (-Not $this.installerExists()) {
                    $this.exception([string]::Format('Unable to locate downloaded Icinga 2 Agent installer file from {0}. Download destination: {1}', $url, $this.getInstallerPath()));
                }
            } catch {
                $this.exception([string]::Format('Unable to download Icinga 2 Agent from {0}. Please ensure the link does exist and access is possible. Error: {1}', $url, $_.Exception.Message));
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
        if (-Not $this.config('download_url') -Or -Not $this.getProperty('install_msi_package')) {
            return '';
        }
        [string]$installerPath = '';
        if (Test-Path ($this.config('download_url'))) {
            $installerPath = Join-Path -Path $this.config('download_url') -ChildPath $this.getProperty('install_msi_package');
        } else {
            $installerPath = [string]::Format('{0}/{1}', $this.config('download_url'), $this.getProperty('install_msi_package'));
        }

        if ($this.isDownloadPathLocal()) {
            if (Test-Path $installerPath) {
                return $installerPath;
            } else {
                $this.exception([string]::Format('Failed to locate local Icinga 2 Agent installer at {0}', $installerPath));
                return '';
            }
        } else {
            return (Join-Path -Path $Env:temp -ChildPath $this.getProperty('install_msi_package'));
        }
    }

    #
    # Verify that the installer package we downloaded
    # does exist in first place
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installerExists' -value {
        if ($this.getInstallerPath() -And (Test-Path $this.getInstallerPath())) {
            return $TRUE;
        }
        return $FALSE;
    }

    #
    # Get all arguments for the Icinga 2 Agent installer package
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getIcingaAgentInstallerArguments' -value {
        # Initialise some basic variables
        [string]$arguments = '';
        [string]$installerLocation = '';

        # By default, install the Icinga 2 Agent again in the pre-installed directory
        # before the update. Will only apply during updates / downgrades of the Agent
        if ($this.getProperty('cur_install_dir')) {
            # In case we perform an architecture change, we should use the new default location as source in case
            # we have installed the Agent into Program Files (x86) for example but are now using a x64 Agent
            # which should be installed into Program Files instead
            if ($this.getProperty('agent_architecture_change') -And $this.getProperty('agent_migration_target')) {
                $installerLocation = [string]::Format(' INSTALL_ROOT="{0}"', $this.getProperty('agent_migration_target'));
            } else {
                $installerLocation = [string]::Format(' INSTALL_ROOT="{0}"', $this.getProperty('cur_install_dir'));
            }
        }

        # However, if we specified a custom directory over the argument, always use that
        # one as installer target directory
        if ($this.config('agent_install_directory')) {
            $installerLocation = [string]::Format(' INSTALL_ROOT="{0}"', $this.config('agent_install_directory'));
            $this.setProperty('cur_install_dir', $this.config('agent_install_directory'));
        }

        $arguments += $installerLocation;

        return $arguments;
    }

    #
    # Do we require to migrate data from previous Icinga 2 Agent Directory
    #
    $installer | Add-Member -membertype ScriptMethod -name 'checkForIcingaMigrationRequirement' -value {
        if ($this.getProperty('cur_install_dir')) {
            [string]$installDir = $this.getProperty('cur_install_dir');
            # Just in case we installed an x86 Agent on the System, we will require to migrate to x64 on a x64 system.
            if (${Env:ProgramFiles(x86)} -And $installDir.contains(${Env:ProgramFiles(x86)}) -And $this.getProperty('system_architecture') -eq 'x86_64') {
                [string]$migrationPath = $installDir.Replace(${Env:ProgramFiles(x86)}, ${Env:ProgramFiles});
                $this.setProperty('agent_architecture_change', $TRUE);
                $this.setProperty('require_migration', $TRUE);
                $this.setProperty('agent_migration_source', $installDir);
                $this.setProperty('agent_migration_target', $migrationPath);
                $this.setProperty('cur_install_dir', $migrationPath);
                $this.warn('Detected architecture change. Current installed Agent version is x86, while new installed version will be x64. Possible data will be migrated.');
            } else {
                $this.setProperty('agent_migration_source', $this.getProperty('cur_install_dir'));
            }
        }

        if ($this.config('agent_install_directory')) {
            [string]$currentInstallDir = $this.cutLastSlashFromDirectoryPath($this.getProperty('cur_install_dir'));
            [string]$intendedInstallDir = $this.cutLastSlashFromDirectoryPath($this.config('agent_install_directory'));

            if ($currentInstallDir -ne $intendedInstallDir) {
                $this.setProperty('agent_migration_target', $this.config('agent_install_directory'));
                $this.setProperty('require_migration', $TRUE);
            }
        }
    }

    #
    # To ensure we handle path strings correctly, we always require to cut the last \
    #
    $installer | Add-Member -membertype ScriptMethod -name 'cutLastSlashFromDirectoryPath' -value {
        param([string]$path);

        if (-Not $path -Or $path -eq '') {
            return $path;
        }

        if ($path[$path.Length - 1] -eq '\') {
            $path = $path.Substring(0, $path.Length - 1);
        }

        return $path;
    }

    #
    # Install the Icinga 2 agent from the provided installation package
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installAgent' -value {
        $this.downloadInstaller();
        if (-Not $this.installerExists()) {
            $this.exception('Failed to setup Icinga 2 Agent. Installer package not found.');
            return;
        }
        $this.verifyInstallerChecksumAndThrowException();
        $this.info('Installing Icinga 2 Agent');

        # Start the installer process
        $result = $this.startProcess('MsiExec.exe', $TRUE, [string]::Format('/quiet /i "{0}" {1}', $this.getInstallerPath(), $this.getIcingaAgentInstallerArguments()));

        # Exit Code 0 means the Agent was installed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.exception('Failed to install Icinga 2 Agent. ' + $result.Get_Item('message'));
        } else {
            $this.info('Icinga 2 Agent installed.');
        }

        # Update the Icinga 2 Agent Directories in case of a version change
        # Required by updating from older versions to 2.8.0. and newer
        $return = $this.isAgentInstalled();

        $this.setProperty('require_restart', 'true');
    }

    #
    # Updates the Agent in case allowed and required.
    # Removes previous version of Icinga 2 Agent first
    #
    $installer | Add-Member -membertype ScriptMethod -name 'updateAgent' -value {
        $this.downloadInstaller();
        if (-Not $this.installerExists()) {
            $this.exception('Failed to update Icinga 2 Agent. Installer package not found.');
            return;
        }
        $this.verifyInstallerChecksumAndThrowException()
        if (-Not $this.getProperty('uninstall_id')) {
            $this.exception('Failed to update Icinga 2 Agent. Uninstaller is not specified.');
            return;
        }

        $this.info('Removing previous Icinga 2 Agent version');
        # Start the uninstaller process
        $result = $this.startProcess('MsiExec.exe', $TRUE, $this.getProperty('uninstall_id') +' /q');

        # Exit Code 0 means the Agent was removed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.exception('Failed to remove Icinga 2 Agent. ' + $result.Get_Item('message'));
        } else {
            $this.info('Icinga 2 Agent successfully removed.');
        }

        $this.checkForIcingaMigrationRequirement();
        $this.applyPossibleAgentMigration();

        $this.info('Installing new Icinga 2 Agent version');
        # Start the installer process
        $result = $this.startProcess('MsiExec.exe', $TRUE, [string]::Format('/quiet /i "{0}" {1}', $this.getInstallerPath(), $this.getIcingaAgentInstallerArguments()));

        # Exit Code 0 means the Agent was removed successfully
        # Otherwise we require to throw an error
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.exception([string]::Format('Failed to install new Icinga 2 Agent. {0}', $result.Get_Item('message')));
        } else {
            $this.info('Icinga 2 Agent successfully updated.');
        }

        # Update the Icinga 2 Agent Directories in case of a version change
        # Required by updating from older versions to 2.8.0. and newer
        $return = $this.isAgentInstalled();
        $this.setProperty('require_restart', 'true');
    }

    #
    # Migrate a folder and it's content from a previous Agent installation to
    # a new target destination
    #
    $installer | Add-Member -membertype ScriptMethod -name 'doMigrateIcingaDirectory' -value {
        param([string]$sourcePath, [string]$targetPath, [string]$directory);

        if (Test-Path (Join-Path -Path $sourcePath -ChildPath $directory)) {
            [string]$source = Join-Path -Path $sourcePath -ChildPath $directory;
            [string]$target = Join-Path -Path $targetPath -ChildPath $directory;
            $this.info([string]::Format('Migrating content from "{0}" to "{1}"', $source, $target));
            $result = Copy-Item $source $target -Recurse;
        }
    }

    #
    # Copy a single file from it's source location to our target location
    #
    $installer | Add-Member -membertype ScriptMethod -name 'doMigrateIcingaFile' -value {
        param([string]$sourcePath, [string]$targetPath, [string]$file);
        $this.info([string]::Format('Migrating file from "{0}" to "{1}\{2}"', $sourcePath, $targetPath, $_));
        Copy-Item $sourcePath $targetPath;
    }

    #
    # This function will determine if we require to migrate content from a previous
    # Icinga 2 Agent installation to the new location
    #
    $installer | Add-Member -membertype ScriptMethod -name 'applyPossibleAgentMigration' -value {
        if (-Not $this.getProperty('require_migration') -Or $this.getProperty('require_migration') -eq $FALSE) {
            $this.info('No migration of Icinga 2 Agent data required.')
            return;
        }

        $this.info([string]::Format('Icinga 2 Agent installation location changed from {0} to {1}. Migrating possible content...', $this.getProperty('agent_migration_source'), $this.getProperty('agent_migration_target')));

        if ($this.getProperty('agent_migration_source') -And (Test-Path ($this.getProperty('agent_migration_source')))) {
            # Load Directories and Remove \ at the end of the path if present to ensure we have the same path base
            [string]$sourcePath = $this.cutLastSlashFromDirectoryPath($this.getProperty('agent_migration_source'));
            [string]$targetPath = $this.cutLastSlashFromDirectoryPath($this.getProperty('agent_migration_target'));

            # Get all objects within our source root and copy it to our target destination
            $result = Get-ChildItem -Path $sourcePath |
                ForEach-Object {
                    if ($_.PSIsContainer) {
                        $this.doMigrateIcingaDirectory($sourcePath, $targetPath, $_);
                    } else {
                        $this.doMigrateIcingaFile($_.FullName, $targetPath, $_);
                    }
                }
            $this.info([string]::Format('Migration of source folder applied. Please remove content from previous directory {0} if no longer required.', $sourcePath));
        } else {
            $this.info('No data for migration found. Setup is clean.');
        }
    }

    #
    # We might have installed the Icinga 2 Agent
    # already. In case we do, get all data to
    # ensure we access the Agent correctly
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isAgentInstalled' -value {
        [string]$architecture = '';
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
        $this.setProperty('cur_install_dir', $localData.InstallLocation);
        $this.setProperty('agent_version', $localData.DisplayVersion);
        $this.setProperty('install_msi_package', 'Icinga2-v' + $this.config('agent_version') + '-' + $architecture + '.msi');
        $this.setProperty('system_architecture', $architecture);
        $this.setIcinga2AgentVersion($localData.DisplayVersion);

        if (-Not $this.validateVersions('2.8.0', $this.getProperty('icinga2_agent_version'))) {
            $this.setProperty('cert_dir', (Join-Path -Path $this.getProperty('config_dir') -ChildPath 'pki'));
            if ($this.getProperty('use_new_cert_dir')) {
                $this.setProperty('require_cert_migration', $TRUE);
                $this.info('You are downgrading from a newer Icinga 2 Version to a older one. This will require a certificate migration.');
            }
        } else {
            $this.setProperty('cert_dir', (Join-Path -Path $Env:ProgramData -ChildPath 'icinga2\var\lib\icinga2\certs'));
            $this.setProperty('use_new_cert_dir', $TRUE);
        }

        $this.info([string]::Format('Using Icinga version "{0}", setting certificate directory to "{1}"',
                                    $localData.DisplayVersion,
                                    $this.getProperty('cert_dir')
                                    )
                    );

        if ($localData.InstallLocation) {
            $this.info([string]::Format('Found Icinga 2 Agent version {0} installed at "{1}"', $localData.DisplayVersion, $localData.InstallLocation));
            return $TRUE;
        } else {
            $this.warn('Icinga 2 Agent does not seem to be installed on the system');
            # Set Default value for install dir
            $this.setProperty('cur_install_dir', (Join-Path $Env:ProgramFiles -ChildPath 'ICINGA2'));
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

        $this.info([string]::Format('Trying to install Icinga 2 Agent Firewall Rule for port {0}', $this.config('agent_listen_port')));

        $result = $this.startProcess('netsh', $FALSE, 'advfirewall firewall show rule name="Icinga 2 Agent Inbound by PS-Module"');
        if ($result.Get_Item('exitcode') -eq 0) {
            # Firewall rule is already defined -> delete it and add it again

            $this.info('Icinga 2 Agent Firewall Rule already installed. Trying to remove it to add it again...');
            $result = $this.startProcess('netsh', $TRUE, 'advfirewall firewall delete rule name="Icinga 2 Agent Inbound by PS-Module"');

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error([string]::Format('Failed to remove Icinga 2 Agent Firewall rule before adding it again: {0}', $result.Get_Item('message')));
                return;
            } else {
                $this.info('Icinga 2 Agent Firewall Rule has been removed. Re-Adding now...');
            }
        }

        [string]$binaryPath = Join-Path $this.getInstallPath() -ChildPath 'sbin\icinga2.exe';
        [string]$argument = 'advfirewall firewall add rule'
        $argument += [string]::Format(' dir=in action=allow program="{0}"', $binaryPath);
        $argument += ' name="Icinga 2 Agent Inbound by PS-Module"';
        $argument += ' description="Inbound Firewall Rule to allow Icinga 2 masters/satellites to connect to the Icinga 2 Agent installed on this system."';
        $argument += ' enable=yes';
        $argument += ' remoteip=any';
        $argument += ' localip=any';
        $argument += [string]::Format(' localport={0}', $this.config('agent_listen_port'));
        $argument += ' protocol=tcp';

        $result = $this.startProcess('netsh', $FALSE, $argument);
        if ($result.Get_Item('exitcode') -ne 0) {
            # Firewall rule was not added -> print error
            $this.error([string]::Format('Failed to install Icinga 2 Agent Firewall: {0}', $result.Get_Item('message')));
            return;
        }

        $this.info([string]::Format('Icinga 2 Agent Firewall Rule successfully installed for port {0}', $this.config('agent_listen_port')));
    }

    #
    # Get the default path or our custom path for the NSClient++
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getNSClientDefaultExecutablePath' -value {

        if ($this.config('nsclient_directory')) {
            return (Join-Path -Path $this.config('nsclient_directory') -ChildPath 'nscp.exe');
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
        [string]$agentPath = '';
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
        [string]$configFile = Join-Path -Path $this.getProperty('config_dir') -ChildPath 'icinga2.conf';
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
            if ($this.getInstallerPath() -And (Test-Path $this.getInstallerPath())) {
                $this.info('Removing downloaded Icinga 2 Agent installer');
                Remove-Item $this.getInstallerPath() | Out-Null;
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
        if ((Test-Path $this.getApiDirectory()) -And $this.shouldFlushIcingaApiDirectory()) {
            $this.info([string]::Format('Flushing content of "{0}"', $this.getApiDirectory()));
            $this.stopIcingaService();
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
        [string]$password = '';

        if ($currentUser -eq $null) {
            $this.warn('Unable to modify Icinga service user: Service not found.');
            return;
        }

        # Check if we defined user name and password (':' cannot appear within a username)
        # If so split them into seperate variables, otherwise simply use the string as user
        if ($credentials.Contains(':')) {
            [int]$delimeter = $credentials.IndexOf(':');
            $newUser = $credentials.Substring(0, $delimeter);
            $password = [string]::Format(' password= {0}', $credentials.Substring($delimeter + 1, $credentials.Length - 1 - $delimeter));
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
        $this.info([string]::Format('Updating Icinga 2 service user to {0}', $newUser));
        $result = $this.startProcess('sc.exe', $TRUE, [string]::Format('config icinga2 obj= "{0}"{1}', $newUser, $password));

        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error($result.Get_Item('message'));
            return;
        }

        # Just write the success message
        $this.info($result.Get_Item('message'));

        # Try to restart the service
        $result = $this.restartService('icinga2');

        # In case of an error try to rollback to the previous assigned user of the service
        # If this fails aswell, set the user to 'NT AUTHORITY\NetworkService' and restart the service to
        # ensure that the agent is atleast running and collecting some data.
        # Of course we throw plenty of errors to notify the user about problems
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.error($result.Get_Item('message'));
            $this.info([string]::Format('Reseting user to previous working user {0}', $currentUser.StartName));
            $result = $this.startProcess('sc.exe', $TRUE, [string]::Format('config icinga2 obj= "{0}"{1}', $currentUser.StartName, $password));
            $result = $this.restartService('icinga2');
            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error([string]::Format('Failed to reset Icinga 2 service user to the previous user "{0}". Setting user to "NT AUTHORITY\NetworkService" now to ensure the service integrity', $currentUser.StartName));
                $result = $this.startProcess('sc.exe', $TRUE, 'config icinga2 obj= "NT AUTHORITY\NetworkService" password=dummy');
                $this.info($result.Get_Item('message'));
                $result = $this.restartService('icinga2');
                if ($result.Get_Item('exitcode') -eq 0) {
                    $this.info('Reseting Icinga 2 service user to "NT AUTHORITY\NetworkService" successfull.');
                    return;
                } else {
                    $this.error([string]::Format('Failed to rollback Icinga 2 service user to "NT AUTHORITY\NetworkService": {0}', $result.Get_Item('message')));
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

        $this.info([string]::Format('Restarting service {0}', $service));

        # Stop the current service
        $result = $this.startProcess("sc.exe", $TRUE, "stop $service");

        # Wait until the service is stopped
        $serviceResult = $this.waitForServiceToReachState($service, 'Stopped');

        # Start the service again
        $result = $this.startProcess("sc.exe", $TRUE, "start $service");

        # Wait until the service is started
        if ($this.waitForServiceToReachState($service, 'Running') -eq $FALSE) {
            $result.Set_Item('message', [string]::Format('Failed to restart service {0}.', $service));
            $result.Set_Item('exitcode', '1');
        }

        return $result;
    }

    #
    # Function to stop the Icinga 2 service
    #
    $installer | Add-Member -membertype ScriptMethod -name 'stopIcingaService' -value {
        # Stop the Icinga 2 Service
        $this.info('Stopping the Icinga 2 Service...')
        $result = $this.startProcess("sc.exe", $TRUE, "stop icinga2");

        # Wait until the service is stopped
        $serviceResult = $this.waitForServiceToReachState('icinga2', 'Stopped');
        $this.info('Icinga 2 service has been stopped.')
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
                $this.error([string]::Format('Timeout reached while waiting for "{0}" to reach state "{1}". Service is not responding.', $service, $state));
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

    #
    # This function will determine if and how we create the
    # API-Listener configuration
    #
    $installer | Add-Member -membertype ScriptMethod -name 'getApiListenerConfiguration' -value {
        if (-Not $this.hasCertificates() -And -Not $this.getProperty('certs_created')) {
            $this.warn('Configuring Icinga 2 Agent without ApiListener, as certificates have not been generated.');
            return [string]::Format('{0}/* ApiListener has not been configured, as certificates have not been generated. */', "`n`n");
        }

        [string]$apiListenerConfig = '';
        [string]$certificateConfig = '';
        # Icinga 2 Agent Versions below 2.8.0 will require cert_path, key_path and ca_path
        if (-Not $this.validateVersions('2.8.0', $this.getProperty('icinga2_agent_version'))) {
            $certificateConfig = '
  cert_path = SysconfDir + "/icinga2/pki/' + $this.getProperty('local_hostname') + '.crt"
  key_path = SysconfDir + "/icinga2/pki/' + $this.getProperty('local_hostname') + '.key"
  ca_path = SysconfDir + "/icinga2/pki/ca.crt"';
        }

        $apiListenerConfig = '
object ApiListener "api" {' + $certificateConfig + '
  accept_commands = true
  accept_config = ' + $this.convertBoolToString($this.config('accept_config')) + '
  bind_host = "::"
  bind_port = ' + [int]$this.config('agent_listen_port') + '
}';

        return $apiListenerConfig;
    }

    #
    # Generate the new configuration for Icinga 2
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateIcingaConfiguration' -value {
        if ($this.getProperty('generate_config') -eq 'true') {

            $this.checkConfigInputParametersAndThrowException();

            [string]$icingaCurrentConfig = '';
            if (Test-Path $this.getIcingaConfigFile()) {
                $icingaCurrentConfig = [System.IO.File]::ReadAllText($this.getIcingaConfigFile());
            }

            [string]$icingaNewConfig =
'/**
 * Icinga 2 Config - Proposed by Icinga 2 PowerShell Module
 */

/* Define our includes to run the agent properly. */
include "constants.conf"
include <itl>
include <plugins>
include <nscp>
include <windows-plugins>

/* Required for Icinga 2.8.0 and above */
const NodeName = "' + $this.getProperty('local_hostname') + '"

/* Define our block required to enable or disable Icinga 2 debug log
 * Enable or disable it by using the PowerShell Module with
 * argument -IcingaEnableDebugLog or by switching
 * PowerShellIcinga2EnableDebug to true or false manually.
 * true: Debug log is active
 * false: Debug log is deactivated
 * IMPORTANT: ";" after true or false has to remain to allow the
 *            PowerShell Module to switch this feature on or off.
 */
const PowerShellIcinga2EnableDebug = false;
const PowerShellIcinga2EnableLog = true;

if (PowerShellIcinga2EnableDebug) {
  object FileLogger "debug-file" {
    severity = "debug"
    path = LocalStateDir + "/log/icinga2/debug.log"
  }
}

/* Try to define a constant for our NSClient++ installation
 * IMPORTANT: If the NSClient++ is installed newly to the system, the
 * Icinga Service has to be restarted in order to set this variable
 * correctly. If the NSClient++ is installed over the PowerShell Module,
 * the Icinga 2 Service is restarted automaticly.
 */
if (!globals.contains("NscpPath")) {
  NscpPath = dirname(msi_get_component_path("{5C45463A-4AE9-4325-96DB-6E239C034F93}"))
}

/* Enable our default main logger feature to write log output. */
if (PowerShellIcinga2EnableLog) {
  object FileLogger "main-log" {
    severity = "information"
    path = LocalStateDir + "/log/icinga2/icinga2.log"
  }
}

/* All informations required to correctly connect to our parent Icinga 2 nodes. */
object Endpoint "' + $this.getProperty('local_hostname') + '" {}
' + $this.getProperty('endpoint_objects') + '
/* Define the zone and its containing endpoints we should communicate with. */
object Zone "' + $this.config('parent_zone') + '" {
  endpoints = [ ' + $this.getProperty('endpoint_nodes') +' ]
}

/* All of our global zones, check commands and other configuration are synced into.
 * Director global zone must be defined in case the Icinga Director is beeing used.
 * Default value for this is "director-global".
 * All additional zones can be configured with -GlobalZones argument.
 * IMPORTANT: If -GlobalZones argument is used, the Icinga Director global zones has
 *            to be defined as well within the argument array.
 */
' + $this.getProperty('global_zones') + '
/* Define a zone for our current agent and set our parent zone for proper communication. */
object Zone "' + $this.getProperty('local_hostname') + '" {
  parent = "' + $this.config('parent_zone') + '"
  endpoints = [ "' + $this.getProperty('local_hostname') + '" ]
}

/* Configure all settings we require for our API listener to properly work.
 * This will include the certificates, if we accept configurations which
 * can be changed with argument -AcceptConfig and the bind informations.
 * The bind_port can be modified with argument -AgentListenPort.
 */' + $this.getApiListenerConfiguration();

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

        $this.debug([string]::Format('Old Config Hash: "{0}" New Hash: "{1}"', $oldConfigHash, $newConfigHash));

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
        [System.Object]$algorithm = New-Object System.Security.Cryptography.SHA1CryptoServiceProvider;
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
        return (Join-Path -Path $this.getProperty('config_dir') -ChildPath 'icinga2.conf');
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
        $this.info([string]::Format('Writing icinga2.conf to "{0}"', $this.getProperty('config_dir')));
        [System.IO.File]::WriteAllText($this.getIcingaConfigFile(), $configData);
        $this.setProperty('require_restart', 'true');
    }

    #
    # Write old coniguration again
    # just in case we received errors
    #
    $installer | Add-Member -membertype ScriptMethod -name 'rollbackConfig' -value {
        # Write new configuration to file
        $this.info([string]::Format('Rolling back previous icinga2.conf to "{0}"', $this.getProperty('config_dir')));
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
    # Create Host-Certificates for Icinga 2
    #
    $installer | Add-Member -membertype ScriptMethod -name 'createHostCertificates' -value {
        param([string]$hostname, [string]$certDir);

        $this.info('Generating Host certificates required by Icinga 2');
        [string]$icingaBinary = Join-Path -Path $this.getInstallPath() -ChildPath 'sbin\icinga2.exe';
        $result = $this.startProcess($icingaBinary, $FALSE, [string]::Format('pki new-cert --cn {0} --key {1}{0}.key --cert {1}{0}.crt',
                                                                            $hostname,
                                                                            $certDir
                                                                            )
        );

        if ($result.Get_Item('exitcode') -ne 0) {
            throw $result.Get_Item('message');
        }
        $this.info($result.Get_Item('message'));
    }

    #
    # Fix certificate naming for upper / lower case changes
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fixCertificateNames' -value {
        param([string]$hostname, [string]$certDir);
        # Rename the certificates to apply possible upper / lower case naming changes
        # which is not done by Windows by default
        Move-Item (Join-Path -Path $certDir -ChildPath ($hostname + '.key')) (Join-Path -Path $certDir -ChildPath ($hostname + '.key'))
        Move-Item (Join-Path -Path $certDir -ChildPath ($hostname + '.crt')) (Join-Path -Path $certDir -ChildPath ($hostname + '.crt'))
    }

    #
    # Generate the Icinga 2 SSL certificate to ensure the communication between the
    # Agent and the Master can be established in first place
    #
    $installer | Add-Member -membertype ScriptMethod -name 'generateCertificates' -value {

        [string]$icingaCertDir = Join-Path $this.getProperty('cert_dir') -ChildPath '\';
        [string]$icingaBinary = Join-Path -Path $this.getInstallPath() -ChildPath 'sbin\icinga2.exe';
        [string]$agentName = $this.getProperty('local_hostname');

        if (-Not (Test-Path $icingaBinary)) {
            $this.warn('Unable to generate Icinga 2 certificates. Icinga 2 executable not found. It looks like the Icinga 2 Agent is not installed.');
            return;
        }

        if (-Not $this.getProperty('local_hostname')) {
            $this.info('Skipping function for generating certificates, as hostname is not specified within the module.');
            return;
        }

        # Handling for Icinga 2.8.0 and above: CA-Proxy support
        if ($this.config('caproxy')) {
            if (-Not $this.validateVersions('2.8.0', $this.getProperty('icinga2_agent_version'))) {
                throw 'The argument "-CAProxy" is only supported by Icinga Version 2.8.0 and above.';
                return;
            }

            if (-Not $this.config('ca_certificate_path')) {
                throw 'You will require to specify a source path of your CA certificate with -CACertificatePath in order to use CA proxy certificate generation.';
            }

            # Generate the certificate
            [string]$caDestPath = (Join-Path $icingaCertDir -ChildPath '\ca.crt');
            $this.createHostCertificates($agentName, $icingaCertDir);
            $this.fixCertificateNames($agentName, $icingaCertDir);
            $this.setProperty('require_restart', 'true');
            $this.info('Your host certificate has been generated. Please review the request on your Icinga CA with "icinga2 ca list" and sign it with "icinga2 ca sign <request_id>".');
            $this.info([string]::Format('Trying to copy your specified CA certificate "{0}" to "{1}".',
                                        $this.config('ca_certificate_path'),
                                        $caDestPath
                                        ));
            if (-Not (Test-Path $this.config('ca_certificate_path'))) {
                throw [string]::Format('Failed to copy your CA certificate from "{0}" to "{1}". Your source destination does not exist.',
                                                $this.config('ca_certificate_path'),
                                                $caDestPath
                                                );
                return;
            }
            Copy-Item $this.config('ca_certificate_path') $caDestPath;
            $this.setProperty('certs_created', $TRUE);
            return;
        }

        if ($this.config('ca_server') -And $this.getProperty('icinga_ticket')) {
            # Generate the certificate
            $this.createHostCertificates($agentName, $icingaCertDir);

            # Save Certificate
            $this.info("Storing Icinga 2 certificates");
            $result = $this.startProcess($icingaBinary, $FALSE, [string]::Format('pki save-cert --key {0}{1}.key --trustedcert {0}trusted-master.crt --host {2}',
                                                                                $icingaCertDir,
                                                                                $agentName,
                                                                                $this.config('ca_server')
                                                                                )
                                        );
            if ($result.Get_Item('exitcode') -ne 0) {
                throw $result.Get_Item('message');
            }
            $this.info($result.Get_Item('message'));

            # Validate if set against a given fingerprint for the CA
            if (-Not $this.validateCertificate([string]::Format('{0}trusted-master.crt', $icingaCertDir))) {
                throw 'Failed to validate against CA authority';
            }

            # Request certificate
            $this.info("Requesting Icinga 2 certificates");
            $result = $this.startProcess($icingaBinary, $FALSE, [string]::Format('pki request --host {0} --port {1} --ticket {2} --key {3}{4}.key --cert {3}{4}.crt --trustedcert {3}trusted-master.crt --ca {3}ca.crt',
                                                                                $this.config('ca_server'),
                                                                                $this.config('ca_port'),
                                                                                $this.getProperty('icinga_ticket'),
                                                                                $icingaCertDir,
                                                                                $agentName
                                                                                )
                                        );
            if ($result.Get_Item('exitcode') -ne 0) {
                if ($this.getProperty('agent_name_change')) {
                    $this.exception('You have changed the naming of the Agent (upper / lower case) and therefor your certificates are no longer valid. Certificate generation failed because of a possible wrong ticket. Please ensure to set the "hostname" within the Icinga 2 configuration correctly and re-run this script.');
                }
                throw $result.Get_Item('message');
            }
            $this.info($result.Get_Item('message'));
            $this.fixCertificateNames($agentName, $icingaCertDir);
            $this.setProperty('require_restart', 'true');
            $this.setProperty('certs_created', $TRUE);
        } else  {
            $this.info('Skipping certificate generation. One or more of the following arguments is not set: -CAServer <server> -Ticket <ticket>');
        }
    }

    #
    # Validate against a given fingerprint if we are connected to the correct CA
    #
    $installer | Add-Member -membertype ScriptMethod -name 'validateCertificate' -value {
        param([string] $certificate);

        [System.Object]$certFingerprint = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2;
        $certFingerprint.Import($certificate);
        $this.info([string]::Format('Certificate fingerprint: "{0}"', $certFingerprint.Thumbprint));

        if ($this.config('ca_fingerprint')) {
            if (-Not ($this.config('ca_fingerprint') -eq $certFingerprint.Thumbprint)) {
                $this.error([string]::Format('CA fingerprint does not match! Expected: "{0}", given: "{1}"',
                                            $certFingerprint.Thumbprint,
                                            $this.config('ca_fingerprint')
                                            )
                            );
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
    # In case we migrate from an Icinga 2 Version with the new certificate path to
    # a version with the old one, we require to migrate the certificates
    #
    $installer | Add-Member -membertype ScriptMethod -name 'migrateCertificates' -value {
        if (-Not $this.getProperty('require_cert_migration')) {
            return;
        }

        [string]$agentName = $this.getProperty('local_hostname');

        [string]$caPath = Join-Path -Path $Env:ProgramData -ChildPath 'icinga2\var\lib\icinga2\certs\ca.crt';
        [string]$newCA = Join-Path -Path $this.getProperty('config_dir') -ChildPath 'pki\ca.crt';
        [string]$certPath = Join-Path -Path $Env:ProgramData -ChildPath ([string]::Format('icinga2\var\lib\icinga2\certs\{0}.crt', $agentName));
        [string]$newCertPath = Join-Path -Path $this.getProperty('config_dir') -ChildPath ([string]::Format('pki\{0}.crt', $agentName));
        [string]$keyPath = Join-Path -Path $Env:ProgramData -ChildPath ([string]::Format('icinga2\var\lib\icinga2\certs\{0}.key', $agentName));
        [string]$newKeyPath = Join-Path -Path $this.getProperty('config_dir') -ChildPath ([string]::Format('pki\{0}.key', $agentName));

        if (Test-Path $caPath) {
            Copy-Item $caPath $newCA;
            $this.info([string]::Format('Migrating ca.crt from "{0}" to "{1}".', $caPath, $newCA));
        }

        if (Test-Path $certPath) {
            Copy-Item $certPath $newCertPath;
            $this.info([string]::Format('Migrating {0}.crt from "{1}" to "{2}".', $agentName, $certPath, $newCertPath));
        }

        if (Test-Path $keyPath) {
            Copy-Item $keyPath $newKeyPath;
            $this.info([string]::Format('Migrating {0}.crt from "{1}" to "{2}".', $agentName, $keyPath, $newKeyPath));
        }
    }

    #
    # Check the Icinga install directory and determine
    # if the certificates are both available for the
    # Agent. If not, return FALSE
    #
    $installer | Add-Member -membertype ScriptMethod -name 'hasCertificates' -value {
        [string]$icingaCertDir = Join-Path -Path $this.getProperty('cert_dir') -ChildPath '\';
        [string]$agentName = $this.getProperty('local_hostname');
        [bool]$filesExist = $FALSE;
        # First check if the files in generell exist
        if (
            ((Test-Path ((Join-Path -Path $icingaCertDir -ChildPath $agentName) + '.key'))) `
            -And (Test-Path ((Join-Path -Path $icingaCertDir -ChildPath $agentName) + '.crt')) `
            -And (Test-Path (Join-Path -Path $icingaCertDir -ChildPath 'ca.crt'))
        ) {
            $filesExist = $TRUE;
        }

        # In case they do, check if the characters (upper / lowercase) are matching as well
        if ($filesExist -eq $TRUE) {

            [string]$hostCRT = [string]::Format('{0}.crt', $agentName);
            [string]$hostKEY = [string]::Format('{0}.key', $agentName);

            # Get all files inside your certificate directory
            $certificates = Get-ChildItem -Path $icingaCertDir;
            # Now loop each file and match their name with our hostname
            foreach ($cert in $certificates) {
                if ($cert.Name.toLower() -eq $hostCRT.toLower() -Or $cert.Name.toLower() -eq $hostKEY.toLower()) {
                    $file = $cert.Name.Replace('.key', '').Replace('.crt', '');
                    if (-Not ($file -clike $agentName)) {
                        $this.warn([string]::Format('Certificate file {0} is not matching the hostname {1}. Certificate generation is required.', $cert.Name, $agentName));
                        $this.setProperty('agent_name_change', $true);
                        return $FALSE;
                    }
                }
            }
        }

        return $filesExist;
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
        $this.info([string]::Format('Current Icinga 2 Agent Version ({0}) is not matching intended version ({1}). Downloading new version...',
                                    $this.getProperty('agent_version'),
                                    $this.config('agent_version')
                                    )
                );
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
        if ($this.config('download_url') -And $this.config('agent_version')) {
            return $TRUE;
        }

        if (-Not $this.config('download_url') -And -Not $this.config('agent_version')) {
            $this.warn('Icinga 2 Agent will not be installed. Arguments -DownloadUrl and -InstallAgentVersion both not defined.');
            return $FALSE;
        }

        if (-Not $this.config('agent_version')) {
            $this.warn('Icinga 2 Agent will not be installed. Argument -InstallAgentVersion is not defined.');
            return $FALSE;
        }

        if (-Not $this.config('download_url')) {
            $this.warn('Icinga 2 Agent will not be installed. Argument -DownloadUrl is not defined.');
            return $FALSE;
        }

        return $FALSE;
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
        $icingaBinary = Join-Path -Path $this.getInstallPath() -ChildPath 'sbin\icinga2.exe';

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
            # Throw an exception in case we use a parent zone which is a global zone
            foreach ($zone in $this.config('global_zones')) {
                if ($zone -eq $this.config('parent_zone')) {
                    $this.exception([string]::Format('The zone specified for the Icinga 2 Agent to connect to is set to "{0}". This is a global zone which cannot be used. Please review either your arguments used for this module or the Host-Template within the Icinga Director to use the correct zone for this Agent.', $this.config('parent_zone')));
                }
            }
            # In case no parent endpoints are configured, print a warning as we can't write valid Icinga 2 configuration
            if (-Not $this.config('parent_endpoints')) {
                $this.warn('No parent endpoints have been defined within the module call. Either specify them by using the "-ParentEndpoints" argument or ensure you configured your Icinga Director properly in case you are using the Self-Service API. Icinga2.conf has not been generated.');
            }
            $this.info('icinga2.conf did not change or required parameters not set. Nothing to do');
        }
    }

    #
    # Enable or disable the Icinga 2 debug log
    #
    $installer | Add-Member -membertype ScriptMethod -name 'switchIcingaLogging' -value {
        # In case the config is not valid -> do nothing
        if (-Not $this.isIcingaConfigValid($FALSE)) {
            throw 'Unable to process Icinga 2 debug configuration. The icinga2.conf is corrupt! Please check the icinga2.log';
        }

        # If there is no config file defined -> do nothing
        if (-Not (Test-Path $this.getIcingaConfigFile())) {
            return;
        }

        [string]$icingaCurrentConfig = [System.IO.File]::ReadAllText($this.getIcingaConfigFile());
        [string]$newIcingaConfig = $icingaCurrentConfig;

        if ($this.config('icinga_enable_debug_log')) {
            $this.info('Trying to enable debug log for Icinga 2...');
            if ($newIcingaConfig.Contains('const PowerShellIcinga2EnableDebug = false;')) {
                $newIcingaConfig = $newIcingaConfig.Replace('const PowerShellIcinga2EnableDebug = false;', 'const PowerShellIcinga2EnableDebug = true;');
                $this.info('Icinga 2 debug log has been enabled');
            } else {
                $this.info('Icinga 2 debug log is already enabled or configuration not found');
            }
        } else {
            $this.info('Trying to disable debug log for Icinga 2...');
            if ($newIcingaConfig.Contains('const PowerShellIcinga2EnableDebug = true;')) {
                $newIcingaConfig = $newIcingaConfig.Replace('const PowerShellIcinga2EnableDebug = true;', 'const PowerShellIcinga2EnableDebug = false;');
                $this.info('Icinga 2 debug log has been disabled');
            } else {
                $this.info('Icinga 2 debug log is not enabled or configuration not found');
            }
        }

        if ($this.config('icinga_disable_log') -eq $FALSE) {
            $this.info('Trying to enable logging for Icinga 2...');
            if ($newIcingaConfig.Contains('const PowerShellIcinga2EnableLog = false;')) {
                $newIcingaConfig = $newIcingaConfig.Replace('const PowerShellIcinga2EnableLog = false;', 'const PowerShellIcinga2EnableLog = true;');
                $this.info('Icinga 2 logging has been enabled');
            } else {
                $this.info('Icinga 2 logging is already enabled or configuration not found');
            }
        } else {
            $this.info('Trying to disable logging for Icinga 2...');
            if ($newIcingaConfig.Contains('const PowerShellIcinga2EnableLog = true;')) {
                $newIcingaConfig = $newIcingaConfig.Replace('const PowerShellIcinga2EnableLog = true;', 'const PowerShellIcinga2EnableLog = false;');
                $this.info('Icinga 2 logging has been disabled');
            } else {
                $this.info('Icinga 2 logging is not enabled or configuration not found');
            }
        }

        # In case we made a modification to the configuration -> write it
        if ($newIcingaConfig -ne $icingaCurrentConfig) {
            $this.writeConfig($newIcingaConfig);
            # Validate the config if it is valid
            if (-Not $this.isIcingaConfigValid($FALSE)) {
                # if not write the old configuration again
                $this.writeConfig($icingaCurrentConfig);
                if (-Not $this.isIcingaConfigValid($FALSE)) {
                    throw 'Critical exception: Something went wrong while processing logging configuration. The Icinga 2 config is corrupt!  Please check the icinga2.log';
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

        # Add additional variables to our config for more user-friendly usage
        [string]$host_fqdn = [string]::Format('{0}.{1}',
                                 (Get-WmiObject win32_computersystem).DNSHostName,
                                 (Get-WmiObject win32_computersystem).Domain
                             );

        if ([string]::IsNullOrEmpty($this.config('agent_name')) -eq $FALSE) {
            $this.setProperty('local_hostname', $this.config('agent_name'));
            $this.setProperty('fqdn', $host_fqdn);
            $this.setProperty('hostname', $this.config('agent_name'));
        } else {
            if ($this.config('fetch_agent_fqdn') -And (Get-WmiObject win32_computersystem).Domain) {
                [string]$hostname = [string]::Format('{0}.{1}',
                                                    (Get-WmiObject win32_computersystem).DNSHostName,
                                                    (Get-WmiObject win32_computersystem).Domain
                                                    );
                $this.setProperty('local_hostname', $hostname);
            } elseif ($this.config('fetch_agent_name')) {
                [string]$hostname = (Get-WmiObject win32_computersystem).DNSHostName;
                $this.setProperty('local_hostname', $hostname);
            }

            $this.info([string]::Format('Setting internal Agent Name to "{0}"', $this.getProperty('local_hostname')));

            [string]$hostname = (Get-WmiObject win32_computersystem).DNSHostName;

            $this.setProperty('fqdn', $host_fqdn);
            $this.setProperty('hostname', $hostname);
        }

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

        $this.exception('Failed to lookup any IP-Address for this host');
    }

    #
    # This function will try to locate the IPv4 address used
    # for communicating with the network
    #
    $installer | Add-Member -membertype ScriptMethod -name 'lookupPrimaryIPv4Address' -value {
        # First execute nslookup for your FQDN and hostname to check if this
        # host is registered and receive it's IP address
        [System.Collections.Hashtable]$fqdnLookup = $this.startProcess('nslookup.exe', $TRUE, $this.getProperty('fqdn'));
        [System.Collections.Hashtable]$hostnameLookup = $this.startProcess('nslookup.exe', $TRUE, $this.getProperty('hostname'));

        # Now get the message of our result we should work with (nslookup output)
        [string]$fqdnLookup = $fqdnLookup.Get_Item('message');
        [string]$hostnameLookup = $hostnameLookup.Get_Item('message');
        # Get our basic IP first
        [string]$usedIP = $this.getProperty('ipaddress');

        # First try to lookup the basic address. If it is not contained, look further
        if ($this.isIPv4AddressInsideLookup($fqdnLookup, $hostnameLookup, $usedIP) -eq $FALSE) {
            [int]$ipCount = $this.getProperty('ipv4_count');
            [bool]$found = $FALSE;
            # Loop through all found IPv4 IP's and try to locate the correct one
            for ($index = 0; $index -lt $ipCount; $index++) {
                $usedIP = $this.getProperty([string]::Format('ipaddress[{0}]', $index));
                if ($this.isIPv4AddressInsideLookup($fqdnLookup, $hostnameLookup, $usedIP)) {
                    # Swap IP values once we found a match and exit this loop
                    $this.setProperty([string]::Format('ipaddress[{0}]', $index), $this.getProperty('ipaddress'));
                    $this.setProperty('ipaddress', $usedIP);
                    $found = $TRUE;
                    break;
                }
            }

            if ($found -eq $FALSE) {
                $this.warn([string]::Format('Failed to lookup primary IP for this host. Unable to match nslookup against any IPv4 addresses on this system. Using {0} as default now. Access it with &ipaddress& for all JSON requests.',
                                            $this.getProperty('ipaddress')
                                            )
                        );
                return;
            }
        }

        $this.info([string]::Format('Setting IP {0} as primary IP for this host for all requests. Access it with &ipaddress& for all JSON requests.',
                                    $usedIP
                                    )
                    );
    }

    #
    # Check if inside our lookup the IP-Address is found
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isIPv4AddressInsideLookup' -value {
        param([string]$fqdnLookup, [string]$hostnameLookup, [string]$ipv4Address);

        if ($fqdnLookup.Contains($ipv4Address) -Or $hostnameLookup.Contains($ipv4Address)) {
            return $TRUE;
        }

        return $FALSE;
    }

    #
    # Add all found IP-Addresses to our property array
    #
    $installer | Add-Member -membertype ScriptMethod -name 'doLookupIPAddressesForHostname' -value {
        param([string]$hostname);

        $this.info([string]::Format('Trying to fetch Host IP-Address for hostname: {0}', $hostname));
        try {
            [array]$ipAddressArray = [Net.DNS]::GetHostEntry($hostname).AddressList;
            $this.addHostIPAddressToProperties($ipAddressArray);
            return $TRUE;
        } catch {
            # Write an error in case something went wrong
            $this.warn([string]::Format('Failed to lookup IP-Address with DNS-Lookup for "{0}": {1}',
                                        $hostname,
                                        $_.Exception.Message
                                        )
                    );
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
                $this.setProperty([string]::Format('ipaddress[{0}]', $ipV4Index), $address);
                $ipV4Index += 1;
            } else { #IPv6
                # If the first entry of our default ipaddress is empty -> add it
                if ($this.getProperty('ipaddressV6') -eq $null) {
                    $this.setProperty('ipaddressV6', $address);
                }
                # Now add the IP's with an array like construct
                $this.setProperty([string]::Format('ipaddressV6[{0}]', $ipV6Index), $address);
                $ipV6Index += 1;
            }
        }
        $this.setProperty('ipv4_count', $ipV4Index);
        $this.setProperty('ipv6_count', $ipV6Index);
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
            $this.info([string]::Format('Transforming Agent Name to {0}', $hostname));
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
            $this.error([string]::Format('Failed to replace IP-Address placeholder. Invalid format for IP-Type {0}',
                                        $ipType
                                        )
                    );
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
            if ($this.getProperty('use_self_service_api')) {

                if ($this.getProperty('icinga_host_exist')) {
                    $this.info('Host is already registered within Icinga Director.');
                    return;
                }

                if ($this.getProperty('no_valid_api_token')) {
                    $this.info('Skipping host creation over Icinga Director Self-Service API, as no valid token has been specified.');
                    return;
                }

                # If not, try to create the host and fetch the API key
                [string]$apiKey = $this.config('director_auth_token');
                [string]$url = [string]::Format('{0}self-service/register-host?name={1}&key={2}',
                                                $this.config('director_url'),
                                                $this.getProperty('local_hostname'),
                                                $apiKey
                                                );
                [string]$json = '';
                # If no JSON Object is defined (should be default), we shall create one
                if (-Not $this.config('director_host_object')) {
                    [string]$hostname = $this.getProperty('local_hostname');
                    $json = [string]::Format('{ "address": "{0}", "display_name": "{0}" }',
                                            $hostname
                                            );
                } else {
                    # Otherwise use the specified one and replace the host object placeholders
                    $json = $this.doReplaceJSONPlaceholders($this.config('director_host_object'));
                }

                $this.info([string]::Format('Creating host "{0}" over API token inside Icinga Director.', $this.getProperty('local_hostname')));

                [string]$httpResponse = $this.createHTTPRequest($url, $json, 'POST', 'application/json', $TRUE, $TRUE);

                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    $this.setProperty('director_host_token', $httpResponse);
                    $this.writeHostAPIKeyToDisk();
                    [string]$response = $this.fetchIcingaDirectorSelfServiceAPIConfig($httpResponse, $FALSE);
                    if ($response -ne '200') {
                        $this.error([string]::Format('Failed to fetch config arguments of Icinga Director Self-Service API after adding new host to Icinga Director. Response was "{0}"', $httpResponse));
                    } else {
                        $this.info('Successfully fetched configuration for this host over Self-Service API.')
                    }
                } else {
                    if ($httpResponse -eq '400') {
                        throw [string]::Format("Received response 400 from Icinga Director. In general this means you tried to re-create an existing host inside the Icinga Director with a host template API key, but the host itself has already a key assigned. Please drop the API key for the host '{0}' and re-run this script to claim ownership. This error usually occures in case the host token was removed manually from the host.", $this.getProperty('local_hostname'));
                    } else {
                        $this.warn([string]::Format('Failed to create host. Response code {0}', $httpResponse));
                    }
                }
            } elseif ($this.config('director_host_object'))  {
                # Setup the url we need to call
                [string]$url = $this.config('director_url') + 'host';
                # Replace the host object placeholders
                [string]$host_object_json = $this.doReplaceJSONPlaceholders($this.config('director_host_object'));
                # Create the host object inside the director
                [string]$httpResponse = $this.createHTTPRequest($url, $host_object_json, 'PUT', 'application/json', $FALSE, $this.config('debug_mode'));

                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    $this.info([string]::Format('Placed query for creating host "{0}" inside Icinga Director. Config: {1}',
                                                $this.getProperty('local_hostname'),
                                                $httpResponse
                                                )
                                );
                } else {
                    if ($httpResponse -eq '422') {
                        $this.warn([string]::Format('Failed to create host "{0}" inside Icinga Director. The host seems to already exist.', $this.getProperty('local_hostname')));
                    } else {
                        $this.error([string]::Format('Failed to create host "{0}" inside Icinga Director. Error response {1}',
                                                    $this.getProperty('local_hostname'),
                                                    $httpResponse
                                                    )
                                );
                    }
                }
                # Shall we deploy the config for the generated host?
                if ($this.config('director_deploy_config')) {
                    $url = $this.config('director_url') + 'config/deploy';
                    [string]$httpResponse = $this.createHTTPRequest($url, '', 'POST', 'application/json', $FALSE, $TRUE);
                    $this.info([string]::Format('Deploying configuration from Icinga Director to Icinga. Result: {0}', $httpResponse));
                }
            }
        }
    }

    #
    # Write Host API-Key for future usage
    #
    $installer | Add-Member -membertype ScriptMethod -name 'writeHostAPIKeyToDisk' -value {
        if (Test-Path ($this.getProperty('config_dir'))) {
            [string]$apiFile = Join-Path -Path $this.getProperty('config_dir') -ChildPath 'icingadirector.token';
            $this.info([string]::Format('Writing host API-Key "{0}" to "{1}"',
                                        $this.getProperty('director_host_token'),
                                        $apiFile
                                        )
                        );
            [System.IO.File]::WriteAllText($apiFile, $this.getProperty('director_host_token'));
        }
    }

    #
    # Read Host API-Key from disk for usage
    #
    $installer | Add-Member -membertype ScriptMethod -name 'readHostAPIKeyFromDisk' -value {
        [string]$apiFile = Join-Path -Path $this.getProperty('config_dir') -ChildPath 'icingadirector.token';
        if (Test-Path ($apiFile)) {
            [string]$hostToken = [System.IO.File]::ReadAllText($apiFile);
            $this.setProperty('director_host_token', $hostToken);
            $this.info([string]::Format('Reading host api token "{0}" from "{1}"',
                                        $hostToken,
                                        $apiFile
                                        )
                        );
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
            [string]$versionString = $this.createHTTPRequest($url, '', 'POST', 'application/json', $FALSE, $this.config('debug_mode'));

            if ($this.isHTTPResponseCode($versionString) -eq $FALSE) {
                # Remove all characters we do not need inside the string
                [string]$versionString = $versionString.Replace('"', '').Replace("`r", '').Replace("`n", '');
                [array]$version = $versionString.Split('.');
                $this.setProperty('icinga_director_api_version', $versionString);
                return;
            } else {
                if ($versionString -eq '900') {
                    throw 'Failed to query Icinga Director API. Received error code 900. Please enable debug mode with -DebugMode for the script run to reteive additional information regarding this error.';
                }
                $this.warn('You seem to use an older Version of the Icinga Director, as no API version could be retreived.');
                $this.setProperty('icinga_director_api_version', '0.0.0');
                return;
            }
        }
        $this.setProperty('icinga_director_api_version', 'false');
    }

    #
    # Set Icinga 2 Agent Version based no the installed Agent
    #
    $installer | Add-Member -membertype ScriptMethod -name 'setIcinga2AgentVersion' -value {
        param([string]$versionString)

        if (-Not $versionString) {
            return;
        }

        $this.setProperty('icinga2_agent_version', $versionString.Split('.'));
    }

    #
    # Compare Version-Strings and check if we are running a higher or lower version
    #
    $installer | Add-Member -membertype ScriptMethod -name 'validateVersions' -value {
        param([string]$requiredVersion, [array]$providedVersion);

        if (-Not $requiredVersion -Or -Not $providedVersion) {
            return $FALSE;
        }

        [array]$requiredVersion = $requiredVersion.Split('.');
        $currentVersion = $providedVersion;

        if ([int]$requiredVersion[0] -gt [int]$currentVersion[0]) {
            return $FALSE;
        }

        if ([int]$requiredVersion[1] -gt [int]$currentVersion[1]) {
            return $FALSE;
        }

        if ([int]$requiredVersion[1] -ge [int]$currentVersion[1] -And [int]$requiredVersion[2] -gt [int]$currentVersion[2]) {
            return $FALSE;
        }

        return $TRUE;
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
            $this.error([string]::Format('The feature "{0}" requires Icinga Director API-Version {1}. Your Icinga Director version does not support the API.',
                                        $functionName,
                                        $version
                                        )
                        );
            return $FALSE;
        }

        [bool]$versionValid = $this.validateVersions($version, $this.getProperty('icinga_director_api_version').Split('.'));

        if ($versionValid -eq $FALSE) {
            $this.error([string]::Format('The feature "{0}" requires Icinga Director API-Version {1}. Got version {2}',
                                        $functionName,
                                        $version,
                                        $this.getProperty('icinga_director_api_version')
                                        )
                        );
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # This function will convert a [hashtable] or [array] object to string
    # with function ConvertTo-Json for argument -DirectorHostObject.
    # It will however only process those if the PowerShell Version is 3
    # and above, because Version 2 is not providing the required
    # functionality. In that case the module will throw an exception
    #
    $installer | Add-Member -membertype ScriptMethod -name 'convertDirectorHostObjectArgument' -value {

        # First add the value to an object we can work with
        [System.Object]$json = $this.config('director_host_object');

        # Prevent processing of empty data
        if ($json -eq $null -Or $json -eq '') {
            return;
        }

        # In case the argument is already a string -> nothing to do
        if ($json.GetType() -eq [string]) {
            # Do nothing
            return;
        } elseif ($json.GetType() -eq [hashtable] -Or $json.GetType() -eq [array]) {
            # Check which PowerShell Version we are using and throw an error in case our Version does not support the argument
            if ($PSVersionTable.PSVersion.Major -lt 3) {
                [string]$errorMessage = 'You are trying to pass an object of type [hashtable] or [array] for argument "-DirectorHostObject", but are using ' +
                                        'PowerShell Version 2 or lower. Passing hashtables through this argument is possible, but it requires to be ' +
                                        'converted with function ConvertTo-Json, which is available on PowerShell Version 3 and above only. ' +
                                        'You can still process JSON-Values with this module, even on PowerShell Version 2, but you will have to pass the ' +
                                        'JSON as string instead of an object. This module will now exit with an error code. For further details, please ' +
                                        'read the documentation for the "-DirectorHostObject" argument. ' +
                                        'Documentation: https://github.com/Icinga/icinga2-powershell-module/blob/master/doc/10-Basic-Arguments.md';
                $this.exception($errorMessage);
                throw 'PowerShell Version exception.';
            }

            # If our PowerShell Version is supporting the function, convert it to a valid string
            $this.overrideConfig('director_host_object', (ConvertTo-Json -Compress $json), $FALSE);
        }
    }

    #
    # This function will connect to the Icinga Director Self-Service API
    # and try to fetch the configuration for our host or the global
    # configuraton, depending if the Host-Token does exist and is valid
    # or in case it does not exist or is invalid if the API-tiken is
    # specified
    #
    $installer | Add-Member -membertype ScriptMethod -name 'connectToIcingaDirectorSelfServiceAPI' -value {
        if (-Not $this.config('director_url')) {
            return;
        }

        if ($this.config('director_user') -And $this.config('director_password')) {
            $this.info('User and Password for Icinga Director have been specified, Self-Service API will not be used.');
            $this.setProperty('use_password_auth', $TRUE);
            return;
        }

        $this.setProperty('icinga_host_exist', $FALSE);

        [string]$response = $this.fetchIcingaDirectorSelfServiceAPIConfig($this.getProperty('director_host_token'), $FALSE);
        switch ($response) {
            '200' {
                $this.info('Connected successfully to Icinga Director Self-Service API over stored host token.');
                $this.setProperty('icinga_host_exist', $TRUE);
                $this.setProperty('use_self_service_api', $TRUE);
                return;
            };
            '404' {
                $this.warn('The local host token could not be found inside the Icinga Director.');
            };
            '500' {
                $this.warn('An internal server error occured while processing your local host token against the Icinga Director Self-Service API.');
            };
        }

        if ($this.config('director_auth_token') -eq '' -And $this.getProperty('director_host_token')) {
            $this.error('No template API token has been specified and the host token seems no longer valid.')
            $this.setProperty('no_valid_api_token', $TRUE);
            return;
        }

        # In case no host-token is set or no longer valid, use our API token if
        # specified to fetch the global configuration from the API
        $response = $this.fetchIcingaDirectorSelfServiceAPIConfig($this.config('director_auth_token'), $FALSE);
        switch ($response) {
            '200' {
                $this.info('Connected successfully to Icinga Director Self-Service API over API token.');
                $this.setProperty('use_self_service_api', $TRUE);
                return;
            };
            '404' {
                $this.warn('Failed to query Icinga Director Self-Service API.');
            };
            '500' {
                $this.warn('An internal server error occured while processing your API token against the Icinga Director Self-Service API.');
            };
            '900' {
                # Nothing to do
                return;
            };
        }

        if ($this.getProperty('director_host_token') -Or $this.config('director_auth_token')) {
            $this.error(
                [string]::Format('Failed to connect to Icinga Director Self-Service API. Tokens were specified but informations could not be fetched. Please review your tokens: Host: "{0}", API: "{1}".',
                                    $this.getProperty('director_host_token'),
                                    $this.config('director_auth_token')
                                    ));
            $this.setProperty('no_valid_api_token', $TRUE);
        }
    }

    #
    # This function will try to call the Icinga Director API
    # with either a host-token or our API-token and retreive
    # our arguments for processing with the configuration of
    # our Icinga 2 Agent Setup
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchIcingaDirectorSelfServiceAPIConfig' -value {
        param([string]$token, [bool]$writeError);
        if (-Not $this.config('director_url') -Or $token -eq '') {
            return '900';
        }

        if (-Not $this.requireIcingaDirectorAPIVersion('1.4.0', '[Function::fetchIcingaDirectorSelfServiceAPIConfig]')) {
            return '900';
        }

        [string]$url = [string]::Format('{0}self-service/powershell-parameters?key={1}',
                                        $this.config('director_url'),
                                        $token
                                        );
        [string]$argumentString = $this.createHTTPRequest($url, '', 'POST', 'application/json', $TRUE, $this.config('debug_mode'));

        if ($this.isHTTPResponseCode($argumentString) -eq $FALSE) {
            # First split the entire result based in new-lines into an array
            [array]$arguments = $argumentString.Split("`n");

            # Now loop all elements and construct a dictionary for all values
            foreach ($item in $arguments) {
                if ($item.Contains(':')) {
                    $this.debug([string]::Format('Processing Director API config argument "{0}"', $item));
                    [int]$argumentPos = $item.IndexOf(":");
                    [string]$argument = $item.Substring(0, $argumentPos);
                    if (($argumentPos + 2) -le $item.Length) {
                        [string]$value = $item.Substring($argumentPos + 2, $item.Length - 2 - $argumentPos);
                        $value = $value.Replace("`r", '');
                        $value = $value.Replace("`n", '');

                        if ($value.Contains( '!')) {
                            [array]$valueArray = $value.Split('!');
                            $this.overrideConfig($argument, $valueArray, $TRUE);
                        } else {
                            if ($value.toLower() -eq 'true') {
                                $this.overrideConfig($argument, $TRUE, $TRUE);
                            } elseif ($value.toLower() -eq 'false') {
                                $this.overrideConfig($argument, $FALSE, $TRUE);
                            } else {
                                $this.overrideConfig($argument, $value, $TRUE);
                            }
                        }
                    } else {
                        $this.debug([string]::Format('Got key argument "{0}" without a value.', $argument));
                    }
                }
            }
        } else {
            if ($writeError) {
                $this.error([string]::Format('Received "{0}" from Icinga Director. Possibly your API token is no longer valid or the object does not exist.', $argumentString));
            }
            return $argumentString;
        }

        # Ensure we generate the required configuration content
        $this.generateConfigContent();
        return '200';
    }

    #
    # This function will communicate directly with
    # the Icinga Director and ensuring that we get
    # some of the possible required informations
    #
    $installer | Add-Member -membertype ScriptMethod -name 'fetchTicketFromIcingaDirector' -value {

        if ($this.getProperty('director_host_token') -And -Not $this.getProperty('use_password_auth')) {
            if ($this.getProperty('no_valid_api_token')) {
                $this.info('Skipping fetching of SSL ticket, as no valid API token has been specified.');
                return;
            }
            if ($this.requireIcingaDirectorAPIVersion('1.4.0', '[Function::fetchTicketFromIcingaDirector]')) {
                [string]$url = [string]::Format('{0}self-service/ticket?key={1}',
                                                $this.config('director_url'),
                                                $this.getProperty('director_host_token')
                                                );
                [string]$httpResponse = $this.createHTTPRequest($url, '', 'POST', 'application/json', $TRUE, $TRUE);
                if ($this.isHTTPResponseCode($httpResponse) -eq $FALSE) {
                    $this.setProperty('icinga_ticket', $httpResponse);
                    $this.info([string]::Format('Fetched ticket "{0}" from Icinga Director', $httpResponse));
                } else {
                    $this.error([string]::Format('Failed to fetch Ticket from Icinga Director. Error response {0}', $httpResponse));
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
                        throw [string]::Format('Failed to fetch ticket for host "{0}". Got "{1}" as ticket.',
                                                $this.getProperty('local_hostname'),
                                                $httpResponse
                                                );
                    } else {
                        $httpResponse = $httpResponse.subString(1, $httpResponse.length - 3);
                        $this.info([string]::Format('Fetched ticket "{0}" for host "{1}".',
                                                    $httpResponse,
                                                    $this.getProperty('local_hostname')
                                                    )
                                );
                        $this.setProperty('icinga_ticket', $httpResponse);
                    }
                } else {
                    if ($httpResponse -eq '404') {
                        $this.error('Unable to fetch host ticket from Icinga Director. The Host object could not be found. Ensure the object is already present or created by specifying the -DirectorHostObject argument of this script.');
                    } else {
                        $this.error([string]::Format('Failed to fetch Ticket from Icinga Director. Error response {0}', $httpResponse));
                    }
                }
            }
        }
    }

    #
    # Check if NSClient is installed on the system
    #
    $installer | Add-Member -membertype ScriptMethod -name 'isNSClientInstalled' -value {
        $nsclient = Get-WmiObject -Class Win32_Product |
                Where-Object {
                    $_.Name -match 'NSClient*';
                }

        if ($nsclient -eq $null) {
            return $FALSE;
        }

        return $TRUE;
    }

    #
    # Shall we install the NSClient as well on the system?
    # All possible actions are handeled here
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installNSClient' -value {

        if ($this.config('install_nsclient')) {

            [string]$installerPath = $this.getNSClientInstallerPath();
            $this.info([string]::Format('Trying to install and configure NSClient++ from "{0}"', $installerPath));

            # First check if the package does exist
            if (Test-Path ($installerPath)) {

                if ($this.isNSClientInstalled() -eq $FALSE) {
                    # Get all required arguments for installing the NSClient unattended
                    [string]$NSClientArguments = $this.getNSClientInstallerArguments();

                    # Start the installer process
                    $result = $this.startProcess('MsiExec.exe', $TRUE, [string]::Format('/quiet /i "{0}" {1}', $installerPath, $NSClientArguments));

                    # Exit Code 0 means the NSClient was installed successfully
                    # Otherwise we require to throw an error
                    if ($result.Get_Item('exitcode') -ne 0) {
                        $this.exception([string]::Format('Failed to install NSClient++. {0}', $result.Get_Item('message')));
                    } else {
                        $this.info('NSClient++ successfully installed.');

                        # To tell Icinga 2 we installed the NSClient and to make
                        # the NSCPPath variable available, we require to restart Icinga 2
                        $this.setProperty('require_restart', 'true');
                    }
                } else {
                    $this.info('NSClient++ is already installed on the system.');
                }

                # If defined remove the Firewall Rule to secure the system
                # By default the NSClient is only called from the Icinga 2 Agent locally
                $this.removeNSClientFirewallRule();
                # Remove the service if we only call the NSClient locally
                $this.removeNSClientService();
                # Add the default NSClient config if we want to do more
                $this.addNSClientDefaultConfig();
            } else {
                $this.error([string]::Format('Failed to locate NSClient++ Installer at "{0}"', $installerPath));
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

            $this.info([string]::Format('Trying to download NSClient++ from "{0}"', $this.config('nsclient_installer_path')));
            [System.Object]$client = New-Object System.Net.WebClient;
            $client.DownloadFile($this.config('nsclient_installer_path'), (Join-Path -Path $Env:temp -ChildPath 'NSCP.msi'));

            return (Join-Path -Path $Env:temp -ChildPath 'NSCP.msi');
        } else {
            # Icinga is shipping a NSClient Version after installation
            # Install this version if defined
            return (Join-Path -Path $this.getInstallPath() -ChildPath 'sbin\NSCP.msi');
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
            $NSClientArguments += [string]::Format(' INSTALLLOCATION={0}', $this.config('nsclient_directory'));
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

            $this.info('Trying to remove NSClient++ Firewall Rule');

            $result = $this.startProcess('netsh', $TRUE, 'advfirewall firewall delete rule name="NSClient++ Monitoring Agent"');

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error([string]::Format('Failed to remove NSClient++ Firewall rule: {0}', $result.Get_Item('message')));
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
                $this.info('Trying to remove NSClient++ service');
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
                $this.error([string]::Format('Failed to generate NSClient++ defaults config. Path to executable is not valid: {0}', $NSClientBinary));
            }
        }
    }

    #
    # Deprecated function
    #
    $installer | Add-Member -membertype ScriptMethod -name 'installIcinga2Agent' -value {
        $this.warn('The function "installIcinga2Agent" is deprecated and will be removed soon. Please use "install" instead.')
        return $this.install();
    }
    $installer | Add-Member -membertype ScriptMethod -name 'installMonitoringComponents' -value {
        $this.warn('The function "installMonitoringComponents" is deprecated and will be removed soon. Please use "install" instead.')
        return $this.install();
    }

    #
    # This function will try to load all
    # data from the system and setup the
    # entire Agent without user interaction
    # including download and update if
    # specified. Returnd 0 or 1 as exit code
    #
    $installer | Add-Member -membertype ScriptMethod -name 'install' -value {
        try {
            if (-Not $this.isAdmin()) {
                return 1;
            }

            # Write an output to the logfile only, ensuring we always get a proper 'start entry' for the user
            $this.info('Started script run...');
            # Get the current API-Version from the Icinga Director
            $this.getIcingaDirectorVersion();
            # Convert our DirectorHostObject argument from Object to String if required
            $this.convertDirectorHostObjectArgument();
            # Read the Host-API Key in case it exists
            $this.readHostAPIKeyFromDisk();
            # Establish connection to Icinga Director Self-Service API if required
            # and fetch basic / host configuration if tokens are set
            $this.connectToIcingaDirectorSelfServiceAPI();
            # Set Script defaults
            $this.setScriptDefaultVariables();
            # Get host name or FQDN if required
            $this.fetchHostnameOrFQDN();
            # Get IP-Address of host
            $this.fetchHostIPAddress();
            # Try to locate the primary IP Address
            $this.lookupPrimaryIPv4Address();
            # Transform the hostname if required
            $this.doTransformHostname();
            # Before we continue, flush the API Directory if specified. This will require
            # us to stop the Icinga 2 Agent, but should prevent any false positive in
            # case dependencies within the API Director are no longer pressent and will
            # ensure a possible config rollback is working as intended as well
            $this.flushIcingaApiDirectory();

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
                } else {
                    $this.warn('Icinga 2 Agent is not installed and not allowed of beeing installed.');
                }
            }

            # Try to create a host object inside the Icinga Director
            $this.createHostInsideIcingaDirector();
            # First check if we should get some parameters from the Icinga Director
            $this.fetchTicketFromIcingaDirector();

            # In case we downgrade from Icinga 2.8.0 or above to a older version (like Icinga 2.7.2)
            $this.migrateCertificates();
            if (-Not $this.hasCertificates() -Or $this.forceCertificateGeneration()) {
                $this.generateCertificates();
            } else {
                $this.info('Icinga 2 certificates already exist. Nothing to do.');
            }

            $this.generateIcingaConfiguration();
            $this.applyPossibleConfigChanges();
            $this.switchIcingaLogging();
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
            return $this.getScriptExitCode();
        } catch {
            $this.printLastException();
            [void]$this.getScriptExitCode();
            return 1;
        }
    }

    #
    # Deprecated function
    #
    $installer | Add-Member -membertype ScriptMethod -name 'uninstallIcinga2Agent' -value {
        $this.warn('The function "uninstallIcinga2Agent" is deprecated and will be removed soon. Please use "uninstall" instead.')
        return $this.uninstall();
    }
    $installer | Add-Member -membertype ScriptMethod -name 'uninstallMonitoringComponents' -value {
        $this.warn('The function "uninstallMonitoringComponents" is deprecated and will be removed soon. Please use "uninstall" instead.')
        return $this.uninstall();
    }

    #
    # Removes the Icinga 2 Agent from the system
    #
    $installer | Add-Member -membertype ScriptMethod -name 'uninstall' -value {
        $this.info('Trying to locate Icinga 2 Agent...');

        if ($this.isAgentInstalled()) {
            $this.info('Removing Icinga 2 Agent from the system');
            $result = $this.startProcess('MsiExec.exe', $TRUE, [string]::Format('{0} /q', $this.getProperty('uninstall_id')));

            if ($result.Get_Item('exitcode') -ne 0) {
                $this.error($result.Get_Item('message'));
                return [int]$result.Get_Item('exitcode');
            }

            $this.info('Icinga 2 Agent successfully removed.');
        }

        if ($this.config('full_uninstallation')) {
            $this.info('Flushing Icinga 2 program data directory...');
            if (Test-Path ((Join-Path -Path $Env:ProgramData -ChildPath 'icinga2'))) {
                try {
                    [System.Object]$folder = New-Object -ComObject Scripting.FileSystemObject;
                    [void]$folder.DeleteFolder((Join-Path -Path $Env:ProgramData -ChildPath 'icinga2'));
                    $this.info('Remaining Icinga 2 configuration successfully removed.');
                } catch {
                    $this.exception([string]::Format('Failed to delete Icinga 2 Program Data Directory: {0}', $_.Exception.Message));
                }
            } else {
                $this.warn('Icinga 2 Agent program directory not present.');
            }
        }

        if ($this.config('remove_nsclient')) {
            $this.info('Trying to remove installed NSClient++...');

            $nsclient = Get-WmiObject -Class Win32_Product |
                Where-Object {
                    $_.Name -match 'NSClient*';
                }

            if ($nsclient -ne $null) {
                $this.info('Removing installed NSClient++...');
                [void]$nsclient.Uninstall();
                $this.info('NSClient++ has been successfully removed.');
            } else {
                $this.warn('NSClient++ could not be located on the system. Nothing to remove.');
            }
        }

        return $this.getScriptExitCode();
    }

    #
    # Locate the current installation of Icinga 2 and dump the icinga2.conf to the window
    #
    $installer | Add-Member -membertype ScriptMethod -name 'dumpIcinga2Conf' -value {
        if (-Not $this.isAgentInstalled()) {
            $this.info('Icinga 2 Agent is not installed on the system. No configuration to dump.');
            return $this.getScriptExitCode();
        }

        [string]$icingaConfig = '';
        if (Test-Path $this.getIcingaConfigFile()) {
            $icingaConfig = [System.IO.File]::ReadAllText($this.getIcingaConfigFile());
            $this.info([string]::Format('Dumping content of the Icinga 2 configuration from "{0}".', $this.getIcingaConfigFile()));
            $this.output($icingaConfig);

        } else {
            $this.exception([string]::Format('Failed to lookup Icinga 2 configuration at "{0}". File does not exist.', $this.getIcingaConfigFile()));
        }
    }

    #
    # Locate the current installation of Icinga 2 and dump all Icinga 2 objects
    #
    $installer | Add-Member -membertype ScriptMethod -name 'dumpIcinga2Objects' -value {
        if (-Not $this.isAgentInstalled()) {
            $this.info('Icinga 2 Agent is not installed on the system. No objects to dump.');
            return $this.getScriptExitCode();
        }

        [string]$icingaBinary = Join-Path -Path $this.getInstallPath() -ChildPath 'sbin\icinga2.exe';

        if (-Not (Test-Path $icingaBinary)) {
            $this.exception([string]::Format('Failed to query Icinga 2 objects. Executable at "{0}" does not exist.', $icingaBinary));
            return $this.getScriptExitCode();
        }

        $result = $this.startProcess($icingaBinary, $FALSE, 'object list');
        if ($result.Get_Item('exitcode') -ne 0) {
            $this.exception($result.Get_Item('message'));
        } else {
            $this.info('Dumping all objects from Icinga 2');
            $this.output($result.Get_Item('message'));
        }
    }

    # Make the installation / uninstallation of the script easier and shorter
    [int]$installerExitCode = 0;
    [int]$uninstallerExitCode = 0;
    [int]$dumpConfigExitCode = 0;
    [int]$dumpObjectsExitCode = 0;
    # If flag RunUninstaller is set, do the uninstallation of the components
    if ($RunUninstaller) {
        $uninstallerExitCode = $installer.uninstall();
    }
    # If flag RunInstaller is set, do the installation of the components
    if ($RunInstaller) {
        $installerExitCode = $installer.install();
    }
    # If flag DumpIcingaConfig is set, print the current Icinga 2 configuration
    if ($DumpIcingaConfig) {
        $dumpConfigExitCode = $installer.dumpIcinga2Conf();
    }
    if ($DumpIcingaObjects) {
        $dumpObjectsExitCode = $installer.dumpIcinga2Objects();
    }
    if ($RunInstaller -Or $RunUninstaller -Or $DumpIcingaConfig -Or $DumpIcingaObjects) {
        if ($installerExitCode -ne 0 -Or $uninstallerExitCode -ne 0 -Or $dumpConfigExitCode -ne 0 -Or $dumpObjectsExitCode -ne 0) {
            return 1;
        }
    }

    # Otherwise handle everything as before
    return $installer;
}
