Working with Agents and Config Zones
====================================

Working with Icinga 2 Agents can be quite tricky, as each Agent needs
it's own Endpoint and Zone definition, correct parent, peering host and
log settings. There may always be reasons for a completely custom-made
configuration. I'd however strongly suggest to give the Director-assisted
variant at least a try first. It might safe you a lot of headaches.

Preparation
-----------

Agent settings are not available for modification directly on a host
object. This requires you to create an "Icinga Agent" template. You
could name it exactly like that, it's important to use meaningful names
for your templates.

As long as you're not using Satellite nodes a single Agent zone is all
you need. Otherwise you should create one Agent template per satellite
zone. If you want to move an Agent to a specific zone just assign it
the correct template and you're all done.


Usage
-----

Once you import the "Icinga Agent" template you'll see a new "Agent" tab.
It tries to assist you with the initial Agent setup by showing a sample
config
