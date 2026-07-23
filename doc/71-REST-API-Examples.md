<a id="REST-API-Examples"></a>The Icinga Director REST API in examples

Workflow
--------

Your purpose of using Icinga is most likely to monitor given parameters on 
your hosts.

There are probably many ways to achieve this, but here is a working example of
how to cover most (if not all) of the requirements of a regular user.

The interaction with the REST API can be done with different tools, but let's
take as `default` the `director_curl.sh` helper introduced in the general
documentation of the REST API.

The challenging part is most likely to figure out the syntax of the JSON to be
sent, so here are the examples:

Host Template
==============
Although it is possible to define all variables explicitly on an object, it
belongs to the best-practices to group some of the common settings on a
template which we can import on the actual objects.

We will create a host template called, i.e. *d_host* (for Director Host),
with `object_type` as *template*, and add two properties, so that it
actually does something. The first property is *check_command* set to
*hostalive*, which will make a simple *ping* to the (IP) `address` of the host
by default, and we can define also, for example a `check_interval` to one
minute.

The *hostalive* command is pre-defined in the ITL (Icinga Template Library), which is probably located in your `/usr/share/icinga2/include/command-plugins.conf` (line 99).

This means that every host importing this template will get a *ping* check
every minute.


```sh
./director_curl.sh POST director/host \
'{
  "object_name": "d_host",
  "object_type": "template",
  "check_command": "hostalive",
  "check_interval": "1m"
}'
```

If the command succeded, the the previous command will reply with the
object (JSON) we just submitted.

At this moment the instruction to create the host template is already at the
Icinga server, but to get it exectured we have to *deploy* the config, for
example, as follows:

```sh
./director_curl.sh POST director/config/deploy
```

The equivalent object in a normal Icinga object (.conf file) would look like
this:

```sh
template Host "d_host" {
  check_command = "hostalive"
  check_interval = 1m
}
```

Host
====
The hosts are the main objects in Icinga. The values that will be
monitored on each of them can be triggered by using *host parameters*.

In the following example, we create a host object with the name *testhost* and
add two optional parameters:
- `imports` tells it to use the *d_host* template, which means that the host
  will automatically inherit a *hostalive* (ping) check if an `address` is
  defined
- `address` defines a network address (most likely an IPv4). User the IP of
   your host instead of the *192.168.1.3* ;)


```sh
./director_curl.sh POST director/host \
'{
  "object_name": "testhost",
  "object_type": "object",
  "imports": ["d_host"],
  "address": "192.168.1.3"
}'
```

Now deploy (execute) the config as in with the host template:

```sh
./director_curl.sh POST director/config/deploy
```

and we can check that the host was properly created with:

```sh
./director_curl.sh POST director/host?name=testhost
```

Which should reply with the same JSON we used to create the host.

Now, let's delete it:

```sh
./director_curl.sh DELETE director/host?name=testhost
```

> Remember to **commit* the changes after each configuration change.

Now the host *testhost* is gone.

Let's create it again with some an extra parameter for the operating system
stored as an string ("Linux"):

```sh
./director_curl.sh POST director/host \
'{
  "object_name": "testhost",
  "object_type": "object",
  "imports": ["d_host"],
  "address": "192.168.1.3",
  "vars":{
    "os": "Linux"
  }
}'
```

There is an *ssh* apply rule pre-defined in `/etc/icinga2/conf.d/services.conf`,
but if we are using Director, it is recommended to disable those objects, by i.e.
removing those .conf files or commenting out the line with
`include_recursive "conf.d"` in `/etc/icinga2/icinga2.conf`.

Oh! but we forgot to add a parameter, and we don't want to delete the host and
create it again. We can can just update it:

```sh
./director_curl.sh POST director/host?name=testhost \
'{
  "vars":{
    "os": "Linux",
    "tcp_ports": ["22", "80"]
  }
}'
```

Now the *testhost* host object also contains a "vars.ports" variable of type
array, including port numbers (integers) for port 22 (SSH) and 80 (HTTP).

At the moment, the host object will look like this:

```sh
{
    "address": "192.168.1.3",
    "imports": [
        "d_host"
    ],
    "object_name": "testhost",
    "object_type": "object",
    "vars": {
        "os": "Linux",
        "tcp_ports": [
          "22",
          "80"
        ] 
    }
}
```

The equivalent in the *old fashion* Icinga configuration files would be: 

```sh
object Host "testhost" {
  import "d_host"
  address = "192.168.1.3"
  vars.os = "Linux"
  vars.tcp_ports = [22, 80]
}
```

Service Template
================

We can create a service template for convenience, just as we did with the host
template.

This way we can inherit a set of parameters without having to define them
explicitly on each service.


```sh
./director_curl.sh POST director/service \
'{
  "object_name": "service_5m",
  "object_type": "template",
  "check_interval": "5m",
  "retry_interval": "30s"
}'
```

This way, we can ensure that the services importing this template run every
5 minutes and try again after 30 seconds.



Apply Service
=============

The *services* pre-defined in the `/etc/icinga2/conf.d/services.conf` are
actually not real "services" (objects), but *service apply rules*, which means
that they get assigned automatically to hosts with one or multiple given
properties.

Here is an example for the pre-defined *ssh* apply service:

```sh
./director_curl.sh POST director/service \
'{
    "object_name": "d_ssh",
    "object_type": "apply",
    "imports": ["service_5m"],
    "check_command": "ssh",
    "assign_filter": "host.vars.os=%22Linux%22"
}'
```

I called it *d_ssh* to avoid conflicts with the *ssh" service in the config
files, in case we did not disable it.

Those fancy "*%22*" things are a trick to include double quotes in JSON.
Which would be equivalent to *host.vars.os="Linux"*.


And now, we will create another service apply object that generates
a tcp check for each port in `host.vars.tcp_ports` (in case it is defined).

```sh
./director_curl.sh POST director/service \
'{
    "object_name": "TCP port ",
    "object_type": "apply",
    "imports": ["service_5m"],
    "check_command": "tcp",
    "apply_for": "host.vars.tcp_ports",
    "vars":{
      "tcp_port": "$config$"
    },
    "assign_filter": "host.vars.tcp_ports=true"
}'
```

We are going we are kind of twisting the twister. We are generating a service
for each element in an array (`host.vars.tcp_ports`), for each matching host
(`host.vars.tcp_ports=true`).

The space at the end of the `object_name` is not a *typo*. The `apply_for`
generates a service for each iteration on the iterable (in this case a
list/array), with it's own name. Our iterables are integers, so a generated
service will be called for example "*TCP port 22*". Without the space, it would
look uglier (*TCP port22*).

Notice that for this apply rule we are defining the `vars` variable, and
passing the `tcp_port` parameter. The value for this `tcp_port` parameter
is `$config$` which is a variable containing each element in the `apply_for`
iterable.

The `$` sign at the beginning and end of the string is another hack to be able
to pass a non-quoted string in JSON format. JSON does not accept to write it as
`"tcp_port": config`.

If your host does not have a port 80 listening, it will definitely show a
"CRITICAL" state for this service. But it was just an example.

This apply service object would look like this in the good old Icinga config
file format:

```sh
apply Service "TCP port " for (port in host.vars.tcp_ports) {
  import "service_5m"
  check_command = "tcp"
  vars.tcp_port = port
  assign where host.vars.tcp_ports
}
```

If you want to understand better that `vars.tcp_port` variable, take a look
to the ITL (`/usr/share/icinga2/include/command-plugins.conf` around line 179).
 You could also, for example, define some `vars.tcp_expect` if you are
expecting a given response from the TCP request, and so on.


The tricky part with the *apply* rules in Director is not to define them
but to grab them back to edit or delete them.

The procedure is not as straight-forward as with the rest of the objects,
but it is doable.

First of all, we need to get the list of apply rules with:

```sh
./director_curl.sh GET director/serviceapplyrules
```

This will reply something like:

```sh
{ "objects": [ {
    "assign_filter": "host.vars.os=%22Linux%22",
    "check_command": "ssh",
    "id": "151",
    "imports": [
        "service_5m"
    ],
    "object_name": "d_ssh",
    "object_type": "apply"
}, {
    "apply_for": "host.vars.tcp_ports",
    "assign_filter": "host.vars.tcp_ports=true",
    "check_command": "tcp",
    "id": "154",
    "imports": [
        "service_5m"
    ],
    "object_name": "TCP port ",
    "object_type": "apply",
    "vars": {
        "tcp_port": "$config$"
}] }
```

from where we can parse the `id` if the rule we want to handle.

Let's say, we want to delete the *TCP port * rule. We could do that by parsing
its `id` (in this case 154), and calling the service with that `id` instead
of the usual `name` attribute.

So, basically:

```sh
./director_curl.sh DELETE director/service?id=154
```

Or instead of deleting it, we can modify something, for example, let's set
the `apply_filter` to match only "hosts" (only one) with the name we created
in the example (*testhost*).

```sh
./director_curl.sh POST director/service?id=154 \
'{
    "object_type": "apply",
    "assign_filter": "host.name=%22testhost%22"
}'
```

And let's modify the *ssh* one for a more fine matching. Right now, we assume
that all *Linux* hosts have an SSH service running on port 22, which might
not be always the case. Additionaly, we could assume, that if we explictly check
for the port 22 with the `tcp_ports` parameter, the host does have an SSH
service on that port.

So, we parse the `id` of the service associated to the apply rule for *ssh*
(which in this example is 151) and tailor a more complex filter.

```sh
./director_curl.sh POST director/service?id=151 \
'{
    "object_type": "apply",
    "assign_filter": "host.vars.os=%22Linux%22&\"22\"=host.vars.tcp_ports"
}'
```

This filter means that if the value of the `host.vars.os` is exactly *Linux*
AND (thereby the `&`) the element *22* is included in `host.vars.tcp_ports`
(array), then assign this rule.

As you saw, some of those expressions are not so intuitive. In the web-GUI we
write those expressions as:

    host.vars.os = Linux AND host.vars.tcp_ports contains 22

A trick to figure out a working (JSON) expression for your expression is to
create the object using the GUI and then check the JSON it produced.

For checking the apply rules we just created, visit the GUI at:

    Icinga Director > Services > Service Apply Rules

and on the left of the the *+ Add*, expand the arrow downwards, and click on
*Download as JSON*. It will show the exact JSON you should use to produce the
object you created in the GUI using the REST API.

> You will download a JSON (text) file, if you are in Linux, you probably know
what to do with it. If you are in Windows, use Notepad or something to read it.

The equivalent to the last version of this *d_ssh* service apply rule in Icinga
".conf" *file* format would be:

```sh
apply Service "d_ssh" for (port in host.vars.tcp_ports) {
  import "service_5m"
  check_command = "ssh"
  vars.tcp_port = port
  assign where host.vars.os == "Linux" && 22 in host.vars.tcp_ports
}
```




Command
=======

The ITL (Icinga Template Library) has already plenty of really good pre-defined
commands to covering the most common scenarios. It is very convenient to use,
as it is defined and "hard-coded" in every machine running Icinga 
(Servers, Clients, ...), so using `check_command = "tcp"` is a safe bet, 
because if it works in one machine, it will most likely work in the rest.

If you don't feel like reading the original ITL files, there is a good (and
user friendlier) [ITL documentation](https://icinga.com/docs/icinga2/latest/doc/10-icinga-template-library/).

But maybe you need to create a `Command` to map some plugin you downloaded from
[Icinga Exchange](https://exchange.icinga.com/), [Nagios Exchange](https://exchange.nagios.org/) or somewhere else. Or maybe you wrote your own plugin...

Anyway, let's create an alternative `ping` command.

First, we create a basic plugin which just pings the `address`, in BASH.

For that, we create a file called `check_myping` and store it for example,
where the rest of the plugins are stored. In RHEL based machines, that is
`/usr/lib64/nagios/plugins/`.

```sh
#! /bin/bash
# My alternative ping plugin
# Usage: check_myping -H IP_ADDRESS
if [ "$1" != '-H' ]; then
    echo 'UNKNOWN: the syntax is ./check_myping -H IP_ADDRESS' && exit 3
fi
if [ -z $2 ]; then
    echo 'UNKNOWN: you must provide an address to ping' && exit 3
fi

ping -c1 -q "$2" &> /dev/null
rc=$?
if [ $rc == 0 ]; then
    echo "OK - $2 responds to ping"
    exit 0
else
    echo "CRITICAL - $2 not responding to ping"
    exit 2
fi
echo 'UNKNOWN - Something is wrong'
exit 3
```

Now we create an Icinga (Director) `Command` object to map that plugin (script)
as an Icinga command we can reference:

```sh
./director_curl.sh POST director/command \
'{
    "object_name": "my_ping",
    "object_type": "object",
    "command": "check_myping",
    "methods_execute": "PluginCheck",
    "arguments":{
      "-H": "$address$"
    },
    "vars": {
      "address": "$address$"
    }
}'
```

And create a service to use it:

```sh
./director_curl.sh POST director/service \
'{
    "object_name": "My Ping",
    "object_type": "apply",
    "imports": ["service_5m"],
    "check_command": "my_ping",
    "assign_filter": "host.name=%22testhost%22"
}'
```

Users
=====

In order to create `Notifications` we need to create `Users` (and optionally
`Groups` of users).

An Icinga user is just another object (like a `host`) which associates a user
`name` with its e-mail address and some other optional parameters.
Thereby, these users have nothing to do with System users, Icingaweb2 Users or
or API users. You could see them as a way to map an e-mail address into a
`name` which you can reference in `UserGroups`, `Notifications`, ...

For some reason, the original (default) config files in Icinga
(`/etc/icinga2/conf.d/templates.conf`) suggrest to import a *generic-user*
template, which in de default form is empty. Let's re-create it in director
under the name *d_user*:

```sh
./director_curl.sh POST director/user \
'{
    "object_name": "d_user",
    "object_type": "template"
}'
```

And now we create a `user` with that template. Note that importing the `d_user`
template is useless here, since we have not included any parameter into it.
We could have included the `states` parameter into it, but well... we will
define it on the `user` directly:

```sh
./director_curl.sh POST director/user \
'{
  "object_name": "John",
  "object_type": "object",
  "imports": "d_user",
  "display_name": "John Doe",
  "email": "john@example.com",
  "states": [ "Down" ]
}'
```

Notifications
=============

Now, let's configure some notifications for the user we just created.

First we create a `Notification Template` called *d_mail_host* to define some
 presets. In this case it will trigger notifications only for `host` objects:

```sh
./director_curl.sh POST director/notification \
'{
  "object_name": "d_mail_host",
  "object_type": "template",
  "command": "mail-host-notification"
}'
```

Same for sending notifications about *Services* by e-mail:

```sh
./director_curl.sh POST director/notification \
'{
  "object_name": "d_mail_service",
  "object_type": "template",
  "command": "mail-service-notification"
}'
```

Without the notification templates, we won't be able to *apply* those
notifications.


We will also need to create one or more `timeperiods` to define when the
notifications should be sent or not. We will create one for *Always*,
equivalent to the "24x7" in the default Icinga2 configs. 


```sh
./director_curl.sh PUT director/timeperiod \
'{
  "object_name": "Always",
  "object_type": "object",
  "display_name": "Continuous notifications",
  "ranges": {
    "monday - sunday": "00:00-24:00"
  },
  "update_method": "LegacyTimePeriod"
}'
```

Now we create a `Notification` object to send an e-mail to the user *John*
in case a host with name *testhost* goes *CRITICAL*:

```sh
./director_curl.sh POST director/notification \
'{
  "object_name": "mail_host",
  "object_type": "apply",
  "imports": "d_mail_host",
  "apply_to": "host",
  "period": "Always",
  "states": [ "Critical" ],
  "users": [ "John" ],
  "assign_filter": "host.name=%22testhost%22"
}'
```

And sure, we can create a notification (apply) object also for services,
simply by setting the `apply_to` to *service* instead of *host* and either
specifying the right `command` or importing the host/service notification
template. 

```sh
./director_curl.sh POST director/notification \
'{
  "object_name": "mail_service",
  "object_type": "apply",
  "imports": "d_mail_service",
  "apply_to": "service",
  "command": "mail-service-notification",
  "users": [ "John" ],
  "period": "Always",
  "states": [ "Warning", "Critical" ],
  "assign_filter": "host.name=%22testhost%22"
}'
```


If you are not getting any notification per e-mail, first step to troubleshoot
is probably checking whetther your MTA is working properly. You can test this
by running the following command on a terminal and checking your inbox
afterwards:

```sh
echo "Test from Icinga" | mail -s "Test Subject" YOUR.EMAIL@example.com
```

If that works, try to check if the Icinga2 *mail-service-notification.sh*
script, which requires a lot of parameters, but you just need to specify
your own e-mail (the rest doesn't really matter for this test):

```sh
/etc/icinga2/scripts/mail-service-notification.sh -d 10.10.10 -l TESTHOST -n TESTHOST -e 'TEST SERVICE' -u 'TEST SERVICE DISPLAY NAME' -o 'TEST OUTPUT' -t problem -s OK -r YOUR.EMAIL@example.com
```


Host with icinga2 agent
=======================

Setting up the `checks` on the Icinga2 agent of a remote host on the 
traditional Icinga2 config files used to require defining a dedicated endpoint
 and zone for the host.

Director has a couple of convenient host parameters that will generate those
for us.

Here is how we could update our sample host object to include Icinga2 agent
services:

```sh
./director_curl.sh POST director/host?name=testhost \
'{
  "has_agent": true,
  "master_should_connect": true,
  "accept_config": true
}'
```

The `has_agent` parameter is self explanatory. The `master_should_connect` is
for example, to enable that our Icinga2 server triggers the *Check now* from
the web GUI. The `accep_config` one is to allow the Icinga2 server to send
configurations to the host via REST API.


Now, we just need to define some services that run on the remote host.

For that, we will create another service template called `agent_service`,
similar to the `generic-service` one, but configured to run on the remote
machine by default:


./director_curl.sh POST director/service \
'{
  "object_name": "agent_service",
  "object_type": "template",
  "check_interval": "5m",
  "retry_interval": "30s",
  "use_agent": true,
  "zone": "director-global"
}'

In this case, we set the `use_agent` and the `zone` parameters to specify that
it should run on the Icinga2 agent of the target, instead of on the Icinga2
server.

Aditionally, as the remote services might require more resources on the remote
host, we can relax the retrieval period, from 1 minute to 5 minutes.


And finally, create a service which imports the `agent_service` template and
checks for, i.e. the CPU usage on our test host:

./director_curl.sh POST director/service \
'{
  "object_name": "cpu",
  "object_type": "apply",
  "imports": "agent_service",
  "check_command": "load",
  "assign_filter": "host.name=%22testhost%22"
}'








```sh
```


```sh
```


```sh
```



```sh
```


