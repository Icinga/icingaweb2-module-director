# Optional columns in Director lists

The host and service **template list** and the main host and service **object
list** accept a comma-separated `add_columns` URL parameter. Columns are only
added for that request: the default list layout is unchanged.

For example:

- `/director/hosts/templates?add_columns=check_command,check_interval`
- `/director/services/templates?add_columns=check_command,max_check_attempts`
- `/director/hosts?add_columns=check_command,zone`
- `/director/services?add_columns=check_command,enable_notifications`

The current allowlist is `display_name`, `check_command`, `check_period`,
`check_interval`, `retry_interval`, `max_check_attempts`, `zone`,
`command_endpoint`, `enable_active_checks`, `enable_notifications` and
`notes`. Host lists additionally support `address` and `address6`. A
maximum of ten distinct extra columns may be selected.

These are **directly configured core values**, not fully resolved Icinga 2
properties. A blank cell can mean that a value is inherited from a template.
`check_command`, `check_period`, `zone` and `command_endpoint` show the
corresponding object's name, not its database ID. Intervals are displayed in
Director's stored seconds format. Selectable columns are fixed server-side:
unsupported names, arbitrary SQL and sensitive fields such as `api_key` are
rejected.

The feature does not yet include configurable custom variables (such as
`vars.cluster`), a UI selector, template tree rendering or object lists inside
configuration branches. The latter continue to use their existing layout;
a request specifying `add_columns` in such a branch returns an explicit
unsupported-operation error. The REST API and JSON export output are unchanged.
