<a id="Getting-started"></a>Getting started
===========================================

When new to the Director please make your first steps with a naked Icinga
environment. Director is not allowed to modify existing configuration in
`/etc/icinga2`. And while importing existing config is possible (happens for
example automagically at kickstart time), it is a pretty advanced task you
should not tackle at the early beginning.

Define a new global zone
------------------------

This zone must exist on every node directly or indirectly managed by the
Icinga Director:

```icinga2
object Zone "director-global" {
  global = true
}
```

Create an API user
------------------

```icinga2
object ApiUser "director" {
  password = "***"
  permissions = [ "config/modify", "config/query", "console", "objects/query/*", "status/query", "actions/generate-ticket" ]
  //client_cn = ""
}
```

To allow the configuration of an API user your Icinga 2 instance needs a
`zone` and an `endpoint` object for itself. If you have a clustered
setup or you are using agents you already have this. If you are using a
fresh Icinga 2 installation or a standalone setup with other ways of
checking your clients, you will have to create them.

The easiest way to set up Icinga 2 with a `zone` and `endpoint` is by
running the [Icinga 2 Setup Wizard](https://docs.icinga.com/icinga2/latest/doc/module/icinga2/chapter/distributed-monitoring#distributed-monitoring-setup-master).

Take some time to really understand how to work with Icinga Director first.


Other topics that might interest you
------------------------------------

* [Working with agents](24-Working-with-agents.md)
* [Understanding how Icinga Director works](10-How-it-works.md)

What you should not try to start with
-------------------------------------

Director has not been built to help you with managing existing hand-crafted
configuration in /etc/icinga2. There are cases where it absolutely would
make sense to combine the Director with manual configuration. You can also
use multiple tools owning separate config packages. But these are pretty
advanced topics.


