In this tutorial you see an example to add Powershell scripts using the director.

This tutorial based and created on a default installation (Nsclient++ and scripts dir).

Default Environment (Base for tutorial):
- The Nsclient++ installed on the Windows machine
- Install dir is C:\Program Files\NSClient++
- The default script dir you can find under: C:\Program Files\NSClient++\scripts
- Powershell.exe you can find under "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe"

1. Click in the Director Menu on "Commands" and "Commands" again.

![Commands - Add Command](screenshot/director/25-powershell/2501_commands.png) 

2. Now create a new "Command"
  - Command Type is: "Plugin Check Command"
  - Command Name: Enter a Name
  - Command: C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe
And Press "Store"

3. Now click in the "Arguments" tab

4. Create a new Argument and Enter:
  - Argument Name: -command
  - Value: & 'C:\Program Files\NSClient++\scripts\check_test.ps1'
  - Position: -1
 Click "Store"
 
 5. Now go to "Services", "Service Template" and create a new "Service Template"
 
 6. Add Service Template:
  - Name: Enter a Name
  - Check Command: Select your command
  Click "Store"

Now you can add this service to a Windows machine and execute a the script.

The output you will see directly under "Plugin Output"
