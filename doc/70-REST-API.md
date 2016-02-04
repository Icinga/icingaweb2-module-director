The Icinga Director REST API
============================

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

Many REST APIs include version strings in like /v1/ their URLs, Icinga Director
doesn't. We will try hard to not break compatibility with future versions.
Sure, sooner or later we also might be forced to introduce some kind of
versioning. But who knows?

As a developer you can trust us to not remove any existing REST url or any
provided property. However, you must always be ready to accept new properties.

URL scheme and supported methods
--------------------------------

We support GET, POST, PUT and DELETE. 

| Method | Meaning
| ------ | ------------------------------------------------------------
| GET    | Read / fetch data. Not allowed to run operations with the potential to cause any harm
| POST   | Trigger actions, create or modify objects. Can also be used to partially modify objects
| PUT    | Creates or replaces objects, cannot be used to modify single object properties
| DELETE | Remove a specific object

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

Should I use HTTPS?
-------------------

Sure, absolutely, no doubt. There is no, absolutely no reason to NOT use
HTTPS these days. Especially not for a configuration tool allowing you to
configure check commands that are going to be executed on all your servers.

Icinga Objects
--------------

### Special parameters

#### Resolve object properties

In case you add the `resolve` parameter to your URL, all inherited object
properties will be resolved. Such a URL could look as follows:

    director/host?name=hostname.example.com&resolve


#### Retrieve all properties

TODO: adjust the code to fix this, current implementation has `withNull`

Per default properties with `null` value are skipped when shipping a result.
You can influence this behavior with the properties parameter. Just append
`properties=ALL` to your URL:

    director/host?name=hostname.example.com&properties=all


#### Retrieve only specific properties

The `properties` parameter also allows you to specify a list of specific
properties. In that case, only the given properties will be returned, even
when they have no (`null`) value:

    director/host?name=hostname.example.com&properties=object_name,address,vars


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

Trigger actions
---------------
You can of course also use the API to trigger specific actions. Deploying the configuration is as simple as issueing `POST director/config/deploy`.

TODO
----

Return Last-Modified und ETag header?
 -> If-Modified-Since -> mtime?
 -> If-Unmodified-Since -> mtime


 SHA1 sum as ETag? For PUT and DELETE:
 -> If-Match -> SHA1 sum as ETag?!
 -> If-None-Match -> SHA1 sum as ETag?!


  304, 412

