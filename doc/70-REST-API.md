<a id="REST-API"></a>The Icinga Director REST API
=================================================

Introduction
------------

Icinga Director has been designed with a REST API in mind. Most URLs you
can access with your browser will also act as valid REST url endpoints.

Base Headers
------------
All your requests MUST have a valid accept header. The only acceptable
variant right now is `application/json`, so please always append a header
as follows to your requests:

    Accept: application/json


Authentication
--------------
Please use HTTP authentication and any valid Icinga Web 2 user, granted
enough permissions to accomplish the desired actions. The restrictions
and permissions that have been assigned to web users will also be enforced
for API users. In addition, the permission `director/api` is required for
any API access.

Versioning
----------

There are no version strings so far in the Director URLs. We will try hard
to not break compatibility with future versions. Sure, sooner or later we
also might be forced to introduce some kind of versioning. But who knows?

As a developer you can trust us to not remove any existing REST url or any
provided property. However, you must always be ready to accept new properties.

URL scheme and supported methods
--------------------------------

We support GET, POST, PUT and DELETE.

| Method | Meaning                                                             |
|--------|---------------------------------------------------------------------|
| GET    | Read / fetch data. Not allowed to run operations with the potential |
|        | to cause any harm                                                   |
| POST   | Trigger actions, create or modify objects. Can also be used to      |
|        | partially modify objects                                            |
| PUT    | Creates or replaces objects, cannot be used to modify single object |
|        | properties                                                          |
| DELETE | Remove a specific object                                            |

TODO: more examples showing the difference between POST and PUT

POST director/host
 gives 201 on success
GET director/host?name=hostname.example.com
PUT director/host?name=hostname.example.com
 gives 200 ok on success and 304 not modified on no change
DELETE director/host?name=hostname.example.com
 gives 200 on success


First example request with CURL
-------------------------------

```sh
curl -H 'Accept: application/json' \
     -u 'username:password' \
     'https://icinga.example.com/icingaweb2/director/host?name=hostname.example.com'
```

### CURL helper script

A script like the following makes it easy to play around with curl:

```sh
METHOD=$1
URL=$2
BODY="$3"
USERNAME="demo"
PASSWORD="***"
test -z "$PASSWORD" || USERNAME="$USERNAME:$PASSWORD"

test -z "$BODY" && curl -u "$USERNAME" \
  -i https://icingaweb/icingaweb/$URL \
  -H 'Accept: application/json' \
  -X $METHOD

test -z "$BODY" || curl -u "$USERNAME" \
  -i https://icingaweb/icingaweb/$URL \
  -H 'Accept: application/json' \
  -X $METHOD \
  -d "$BODY"

echo
```

It can be used as follows:

```sh
director-curl GET director/host?name=localhost

director-curl POST director/host '{"object_name": "host2", "... }'
```


Should I use HTTPS?
-------------------

Sure, absolutely, no doubt. There is no, absolutely no reason to NOT use
HTTPS these days. Especially not for a configuration tool allowing you to
configure check commands that are going to be executed on all your servers.

Icinga Objects
--------------

### Special parameters

| Parameter      | Description                                                 |
|----------------|-------------------------------------------------------------|
| resolved       | Resolve all inherited properties and show a flat object     |
| withNull       | Retrieve default (null) properties also                     |
| withServices   | Show services attached to a host. `resolved` and `withNull` |
|                | are applied for services too                                |
| allowOverrides | Set variable overrides for virtual Services                 |

#### Resolve object properties

In case you add the `resolved` parameter to your URL, all inherited object
properties will be resolved. Such a URL could look as follows:

    director/host?name=hostname.example.com&resolved


#### Retrieve default (null) properties also

Per default properties with `null` value are skipped when shipping a result.
You can influence this behavior with the `properties` parameter. Just append
`&withNull` to your URL:

    director/host?name=hostname.example.com&withNull


#### Fetch host with it's services

This is what the `withServices` parameter exists:

    director/host?name=hostname.example.com&withServices


#### Retrieve only specific properties

The `properties` parameter also allows you to specify a list of specific
properties. In that case, only the given properties will be returned, even
when they have no (`null`) value:

    director/host?name=hostname.example.com&properties=object_name,address,vars


#### Override vars for inherited/applied Services

Enabling `allowOverrides` allows you to let Director figure out, whether your
modified Custom Variables need to be applied to a specific individual Service,
or whether setting Overrides at Host level is the way to go.

     POST director/service?name=Uptime&host=hostname.example.com&allowOverrices

```json
{ "vars.uptime_warning": 300 }
```

In case `Uptime` is an Apply Rule, calling this without `allowOverrides` will
trigger a 404 response. Please note that when modifying the Host object, the
body for response 200 will show the Host object, as that's the one that has
been modified.

### Example

GET director/host?name=pe2015.example.com
```json
{
  "address": "127.0.0.3",
  "check_command": null,
  "check_interval": null,
  "display_name": "pe2015 (example.com)",
  "enable_active_checks": null,
  "flapping_threshold": null,
  "groups": [ ],
  "imports": [
    "generic-host"
  ],
  "retry_interval": null,
  "vars": {
    "facts": {
      "aio_agent_build": "1.2.5",
      "aio_agent_version": "1.2.5",
      "architecture": "amd64",
      "augeas": {
        "version": "1.4.0"
      },
   ...
}
```

director/host?name=pe2015.example.com&resolved
```json
{
    "address": "127.0.0.3",
    "check_command": "tom_ping",
    "check_interval": "60",
    "display_name": "pe2015 (example.com)",
    "enable_active_checks": true,
    "groups": [ ],
    "imports": [
      "generic-host"
    ],
    "retry_interval": "10",
    "vars": {
      "facts": {
        "aio_agent_build": "1.2.5",
        "aio_agent_version": "1.2.5",
        "architecture": "amd64",
        "augeas": {
          "version": "1.4.0"
        },
     ...
}
```

JSON is pretty-printed per default, at least for PHP >= 5.4

Error handling
--------------

Director tries hard to return meaningful output and error codes:
```
HTTP/1.1 400 Bad Request
Server: Apache
Content-Length: 46
Connection: close
Content-Type: application/json
```

```json
{
    "error": "Invalid JSON: Syntax error"
}
```

Trigger actions
---------------
You can of course also use the API to trigger specific actions. Deploying the configuration is as simple as issueing:

    POST director/config/deploy

More
----

Currently we do not handle Last-Modified und ETag headers. This would involve some work, but could be a cool feature. Let us know your ideas!


Sample scenario
---------------

Let's show you how the REST API works with a couple of practical examples:

### Create a new host

```
POST director/host
```

```json
{
  "object_name": "apitest",
  "object_type": "object",
  "address": "127.0.0.1",
  "vars": {
    "location": "Berlin"
  }
}
```
#### Response
```
HTTP/1.1 201 Created
Date: Tue, 01 Mar 2016 04:43:55 GMT
Server: Apache
Content-Length: 140
Content-Type: application/json
```

```json
{
    "address": "127.0.0.1",
    "object_name": "apitest",
    "object_type": "object",
    "vars": {
        "location": "Berlin"
    }
}
```

The most important part of the response is the response code: `201`, a resource has been created. Just for fun, let's fire the same request again. The answer obviously changes:

```
HTTP/1.1 500 Internal Server Error
Date: Tue, 01 Mar 2016 04:45:04 GMT
Server: Apache
Content-Length: 60
Connection: close
Content-Type: application/json
```

```json
{
    "error": "Trying to recreate icinga_host (apitest)"
}
```

So, let's update this host. To work with existing objects, you must ship their `name` in the URL:

    POST director/host?name=apitest

```json
{
  "object_name": "apitest",
  "object_type": "object",
  "address": "127.0.0.1",
  "vars": {
    "location": "Berlin"
  }
}
```

Same body, so no change:
```
HTTP/1.1 304 Not Modified
Date: Tue, 01 Mar 2016 04:45:33 GMT
Server: Apache
```

So let's now try to really change something:

    POST director/host?name=apitest

```json
{"address": "127.0.0.2", "vars.event": "Icinga CAMP" }
```

We get status `200`, changes have been applied:

```
HTTP/1.1 200 OK
Date: Tue, 01 Mar 2016 04:46:25 GMT
Server: Apache
Content-Length: 172
Content-Type: application/json
```

```json
{
    "address": "127.0.0.2",
    "object_name": "apitest",
    "object_type": "object",
    "vars": {
        "location": "Berlin",
        "event": "Icinga CAMP"
    }
}
```

The response always returns the full object on modification. This way you can immediately investigate the merged result. As you can see, `POST` requests only touch the parameters you passed - the rest remains untouched.

One more example to prove this:

```
POST director/host?name=apitest
```

```json
{"address": "127.0.0.2", "vars.event": "Icinga CAMP" }
```

No modification, you get a `304`. HTTP standards strongly discourage shipping a body in this case:
```
HTTP/1.1 304 Not Modified
Date: Tue, 01 Mar 2016 04:52:05 GMT
Server: Apache
```

As you might have noted, we only changed single properties in the vars dictionary. Now lets override the whole dictionary:

```
POST director/host?name=apitest
```

```json
{"address": "127.0.0.2", "vars": { "event": [ "Icinga", "Camp" ] } }
```

The response shows that this works as expected:

```
HTTP/1.1 200 OK
Date: Tue, 01 Mar 2016 04:52:33 GMT
Server: Apache
Content-Length: 181
Content-Type: application/json
```

```json
{
    "address": "127.0.0.2",
    "object_name": "apitest",
    "object_type": "object",
    "vars": {
        "event": [
            "Icinga",
            "Camp"
        ]
    }
}
```

If merging properties is not what you want, `PUT` comes to the rescue:

    PUT director/host?name=apitest

```
{ "vars": { "event": [ "Icinga", "Camp" ] }
```

All other properties vanished, all but name and type:
```
HTTP/1.1 200 OK
Date: Tue, 01 Mar 2016 04:54:33 GMT
Server: Apache
Content-Length: 153
Content-Type: application/json
```

```json
{
    "object_name": "apitest",
    "object_type": "object",
    "vars": {
        "event": [
            "Icinga",
            "Camp"
        ]
    }
}
```

Let's put "nothing":

    PUT director/host?name=apitest

```json
{}
```

Works as expected:

```
HTTP/1.1 200 OK
Date: Tue, 01 Mar 2016 04:57:35 GMT
Server: Apache
Content-Length: 62
Content-Type: application/json
```

```json
{
    "object_name": "apitest",
    "object_type": "object"
}
```

Of course, `PUT` also supports `304`, you can check this by sending the same request again.

Now let's try to cheat:

    KILL director/host?name=apitest

```
HTTP/1.1 400 Bad Request
Date: Tue, 01 Mar 2016 04:54:07 GMT
Server: Apache
Content-Length: 43
Connection: close
Content-Type: application/json
```

```json
{
    "error": "Unsupported method KILL"
}
```

Ok, no way. So let's use the correct method:

    DELETE director/host?name=apitest

```
HTTP/1.1 200 OK
Date: Tue, 01 Mar 2016 05:59:22 GMT
Server: Apache
Content-Length: 109
Content-Type: application/json
```

```json
{
    "imports": [
        "generic-host"
    ],
    "object_name": "apitest",
    "object_type": "object"
}
```

### Service Apply Rules

Please note that Service Apply Rule names are not unique in Icinga 2. They are
not real objects, they are creating other objects in a loop. This makes it
impossible to distinct them by name. Therefore, a dedicated REST API endpoint
`director/serviceapplyrules` ships all Service Apply Rules combined with their
internal ID. This ID can then be used to modify or delete a Rule via
`director/service`.

### Deployment Status
In case you want to fetch the information about the deployments status, 
you can call the following API:

    GET director/config/deployment-status

```
HTTP/1.1 200 OK
Date: Wed, 07 Oct 2020 13:14:33 GMT
Server: Apache
Content-Type: application/json
```

```json
{
    "active_configuration": {
        "stage_name": "b191211d-05cb-4679-842b-c45170b96421",
        "config": "617b9cbad9e141cfc3f4cb636ec684bd60073be1",
        "activity": "028b3a19ca7457f5fc9dbb5e4ea527eaf61616a2"
    }
}
```
This throws a 500 in case Icinga isn't reachable. 
In case there is no active stage name related to the Director, active_configuration 
is set to null.

Another possibility is to pass a list of checksums to fetch the status of 
specific deployments and (activity log) activities.
Following, you can see an example of how to do it:

    GET director/config/deployment-status?config_checksums=617b9cbad9e141cfc3f4cb636ec684bd60073be2,
    617b9cbad9e141cfc3f4cb636ec684bd60073be1&activity_log_checksums=617b9cbad9e141cfc3f4cb636ec684bd60073be1,
    028b3a19ca7457f5fc9dbb5e4ea527eaf61616a2
    
```json
{
    "active_configuration": {
        "stage_name": "b191211d-05cb-4679-842b-c45170b96421",
        "config": "617b9cbad9e141cfc3f4cb636ec684bd60073be1",
        "activity": "028b3a19ca7457f5fc9dbb5e4ea527eaf61616a2"
    },
    "configs": {
        "617b9cbad9e141cfc3f4cb636ec684bd60073be2": "deployed",
        "617b9cbad9e141cfc3f4cb636ec684bd60073be1": "active"
    },
    "activities": {
        "617b9cbad9e141cfc3f4cb636ec684bd60073be1": "undeployed",
        "028b3a19ca7457f5fc9dbb5e4ea527eaf61616a2": "active"
    }
}
```
The list of possible status is: 
* `active`: whether this configuration is currently active
* `deployed`: whether this configuration has ever been deployed
* `failed`: whether the deployment of this configuration has failed
* `undeployed`: whether this configuration has been rendered, but not yet deployed
* `unknown`: whether no configurations have been found for this checksum

### Agent Tickets

The Director is very helpful when it goes to manage your Icinga Agents. In
case you want to fetch tickets through the API, please do as follows:

    GET director/host/ticket?name=apitest

```
HTTP/1.1 200 OK
Date: Thu, 07 Apr 2016 22:19:24 GMT
Server: Apache
Content-Length: 43
Content-Type: application/json
```

```json
"5de9883080e03278039bce57e4fbdbe8fd262c40"
```

Please expect an error in case the host does not exist or has not been
configured to be an Icinga Agent.

### Self Service API

#### Theory of operation

Icinga Director offers a Self Service API, allowing new Icinga nodes to register
themselves. No credentials are required, authentication is based on API keys.
There are two types of such keys:

* Host Template API keys
* Host Object API keys

Template keys basically grant the permission to:

* Create a new host based on that template
* Specify name and address properties for that host

This is a one-time operation and allows one to claim ownership of a specific host.
Now, there are two possible scenarios:

* The host already exists
* The host is not known to Icinga Director

In case the host already exists, Director will check whether it's API key matches
the given one. [..]

#### Request processing for Host registration

A new node will `POST` to `self-service/register-host`, with two parameters in
the URL:

* `name`: it's desired object name, usually the FQDN
* `key`: a valid Host Template API key

In it's body it is allowed to specify a specific set of properties. At the time
of this writing, these are:

* `display_name`
* `address`
* `address6`

Director will validate the `key` and load the corresponding *Host Template*. In
case no such is found, the request is rejected. Then it checks whether a *Host*
with the given `name` exists. In case it does, the request is rejected unless:

* It inherits the loaded *Host Template*
* It already has an API key

If these conditions match, the request is processed. The following sketch roughly shows the decision tree (AFTER the key has been
validated):

```
                               +-----------------------------+
    +--------------+           | * Validate given properties |
    | Host exists? | -- NO --> | * Create new host object    |-----------+
    +--------------+           | * Return new Host API key   |           |
           |                   +-----------------------------+           |
          YES                                                            |
           |                                                             |
           v                          +-----------------------------+    |
   +----------------------+           | * Validate given properties |    |
   | Host has an API key? | -- NO --> | * Apply eventual changes    |----+
   +----------------------+           | * Return new Host API key   |    |
           |                          +-----------------------------+    |
          YES                                                            |
           |                                         +-------------------+
           v                                         |
   +--------------------+                            v
   | Reject the request |                +---------------------+
   +--------------------+                | Client persists the |
                                         | new Host API key    |
                                         +---------------------+
```
