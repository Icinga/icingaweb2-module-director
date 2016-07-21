<a id="Fields-example-interfaces-array"></a>Working with fields - interfaces array example
==============================================

This example wants to show you how to make use of the `Array` data type
when creating fields for custom variables. First, please got to the `Dashboard`
and choose the `Define data fields` dashlet:

![Dashboard - Define data fields](screenshot/director/14_fields-for-interfaces/141_define_datafields.png)

Then create a new data field and select `Array` as its data type:

![Define data field - Array](screenshot/director/14_fields-for-interfaces/142_add_datafield.png)

Then create a new `Host template` (or use an existing one):

![Define host template](screenshot/director/14_fields-for-interfaces/143_add_host_template.png)

Now add your formerly created data field to your template:

![Add field to template](screenshot/director/14_fields-for-interfaces/144_add_template_field.png)

That's it, now you are ready to create your first corresponding host. Once
you add your formerly created template, a new form field for your custom
variable will show up:

![Create host with given field](screenshot/director/14_fields-for-interfaces/145_create_host.png)

Have a look at the config preview, it will show you how your `Array`-based
custom variable will look like once deployed:

![Host config preview with Array](screenshot/director/14_fields-for-interfaces/146_config_preview.png)
