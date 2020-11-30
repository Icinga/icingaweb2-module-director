<a id="Working-with-agents"></a>Working with Agents and Config Zones
====================================================================

Working with Icinga 2 Agents can be quite tricky, as each Agent needs
its own Endpoint and Zone definition, correct parent, peering host and
log settings. There may always be reasons for a completely custom-made
configuration. However, I'd **strongly suggest** using the **Director-
assisted** variant. It will save you a lot of headaches.


Preparation
-----------

Agent settings are not available for modification directly on a host
object. This requires you to create an "Icinga Agent" template. You
could name it exactly like that; it's important to use meaningful names
for your templates.

![Create an Agent template](screenshot/director/24-agents/2401_agent_template.png)

As long as you're not using Satellite nodes, a single Agent zone is all
you need. Otherwise, you should create one Agent template per satellite
zone. If you want to move an Agent to a specific zone, just assign it
the correct template and you're all done.


Usage
-----

Well, create a host, choose an Agent template, that's it:

![Create an Agent-based host](screenshot/director/24-agents/2402_create_agent_based_host.png)

Once you import the "Icinga Agent" template, you'll see a new "Agent" tab.
It tries to assist you with the initial Agent setup by showing a sample
config:

![Agent instructions 1](screenshot/director/24-agents/2403_show_agent_instructions_1.png)

![Agent instructions 2](screenshot/director/24-agents/2404_show_agent_instructions_2.png)

The preview shows that the Icinga Director would deploy multiple objects
for your newly created host:

![Agent preview](screenshot/director/24-agents/2405_agent_preview.png)


Create Agent-based services
---------------------------

Similar game for services that should run on your Agents. First, create a
template with a meaningful name. Then, define that Services inheriting from
this template should run on your Agents.

![Agent-based service](screenshot/director/24-agents/2406_agent_based_service.png)

Please do not set a cluster zone, as this would rarely be necessary.
Agent-based services will always be deployed to their Agent's zone by
default. All you need to do now for services that should be executed
on your Agents is importing that template:

![Agent-based load check](screenshot/director/24-agents/2407_create_agent_based_load_check.png)

Config preview shows that everything works as expected:

![Agent-based service preview](screenshot/director/24-agents/2409_agent_based_service_rendered_for_host.png)

It's perfectly valid to assign services to host templates. Look how the
generated config differs now:

![Agent-based service assigned to host template](screenshot/director/24-agents/2410_agent_based_service_rendered_for_host_template.png)

While services added to a host template are implicitly rendered as
assign rules, you could of course also use your `Agent-based service`
template in custom apply rules:

![Agent-based service applied](screenshot/director/24-agents/2411_assign_agent_based_service.png)



