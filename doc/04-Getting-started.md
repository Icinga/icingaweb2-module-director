Preparing your Icinga 2 environment for the Director
====================================================

Create an API user
------------------

```icinga2
object ApiUser "director" {
  password = "***"
  permissions = [ "*" ]
  //client_cn = ""
}
```

Start with a new, empty Icinga setup. Director is not allowed to modify
existing configuration in `/etc/icinga2`, and while importing existing
config is possible (happens for examply automacigally at kickstart time)
this is an advanced task you should not tackle at the early beginning.

Take some time to really understand work with Icinga Director first.

Working with Agents and Config Zones
====================================

Hint: Large: max packet size
