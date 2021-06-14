<a id="Live-modification"></a>Live Modification
===============================================

This chapter introduces the *Live Modification* functionality and describes how
it works.

*Live Modification* can be enabled by ticking the dedicated flag in  the 
`Configuration -> Modules -> Director -> Configuration` page.

Feature overview
----------------

*Live Modification* manages in real-time the monitoring objects defined by the
user without the necessity of a manual deployment.

At object creation/update/deletion time, the Director decides if changes are 
eligible for the live modification. There are a few object types and properties
that are not yet covered by this feature and still require a manual deployment.
In those infrequent cases, however, the user will be notified that changes need
to be deployed by hand.

If changes are supported, they are queued in a pending state, then the 
Director Daemon will take care of applying the modifications through the 
Icinga2 API.

Activity Log
------------

All the real-time changes are tracked as usual in the 
`Director -> Activity Log` page.
Each Activity Log entry has a `Live Modification` field with the following 
possible values:

- **disabled**: if the feature is not enabled in the current installation
- **scheduled**: when changes are applied in the Director, but not yet 
  propagated to Icinga2
- **succeeded**: for all the changes already applied in monitoring 
  configuration
- **failed**: when changes are supported by the feature, but an error occurred 
  during the propagation
- **impossible**: if changes are not supported by the feature

A blue badge counter will be visible in sidebar if live modifications are still
pending on the system.
