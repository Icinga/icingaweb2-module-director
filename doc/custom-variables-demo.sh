#!/usr/bin/env bash
# =============================================================================
# Icinga Director — Custom Variable Support Demo
# =============================================================================
# This script demonstrates the new structured custom variable support via the
# Director REST API. It covers all supported types, the apply-for rule pattern,
# and the datafields migration command.
#
# Prerequisites:
#   - Icinga Director running at BASE_URL with the schema migration applied
#     (schema/mysql-migrations/upgrade_192.sql)
#   - Custom property schemas already created in the UI or via migration
#     (Icinga Director → Custom Variables → Create Custom Variable)
#   - jq installed (optional, for pretty-printing responses)
# =============================================================================

BASE_URL="http://localhost/icingaweb2"
CREDS="icingaadmin:icinga"
CURL="curl -k -s -u $CREDS -H 'Accept: application/json' -H 'Content-Type: application/json'"

echo "================================================================="
echo " Icinga Director — Custom Variable Support Demo"
echo "================================================================="

# ---------------------------------------------------------------------------
# SCENARIO 1: Host with scalar variables (string, number, boolean)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 1: Scalar custom variables on a host ---"
echo "Types: string, number, boolean"
echo
echo "Expected config fragment:"
cat <<'CONF'
  vars.environment       = "production"
  vars.max_check_retries = 3
  vars.agent_enabled     = true
CONF

echo
echo "REST API call:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=linux-host' \
  -d '{
    "environment":       "production",
    "max_check_retries": 3,
    "agent_enabled":     true
  }'
CMD

# Uncomment to run against a live instance:
# curl -k -u "$CREDS" \
#   -H 'Accept: application/json' -H 'Content-Type: application/json' \
#   -X PUT "$BASE_URL/director/host/variables?name=linux-server-01" \
#   -d '{"environment":"production","max_check_retries":3,"agent_enabled":true}'


# ---------------------------------------------------------------------------
# SCENARIO 2: Host with a dynamic-array (contact_groups)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 2: Dynamic-array custom variable ---"
echo "Variable: contact_groups (dynamic-array of strings)"
echo
echo "Expected config fragment:"
cat <<'CONF'
  vars.contact_groups = ["noc", "linux-ops", "on-call-primary"]
CONF

echo
echo "REST API call:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=h4' \
  -d '{
      "environment":       "production",
      "max_check_retries": 3,
      "agent_enabled":     true,
      "http_onredirect": false,
      "new_linux_var": "foo bar",
      "contact_groups": ["noc", "linux-ops", "on-call-primary"],
      "mysql_conn": {
        "host":     "db-primary.internal",
        "port":     "3306",
        "user":     "icinga_monitor",
        "password": "s3cr3t",
        "database": "app_production"
      },
      "disk_checks": {
        "root":   { "disk_partition": "/",          "disk_wfree": "20%", "disk_cfree": "10%" },
        "data":   { "disk_partition": "/data",       "disk_wfree": "15%", "disk_cfree": "5%"  },
        "backup": { "disk_partition": "/mnt/backup", "disk_wfree": "10%", "disk_cfree": "5%"  }
      }
  }'
CMD


# ---------------------------------------------------------------------------
# SCENARIO 3: Host with a fixed-dictionary (mysql connection parameters)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 3: Fixed-dictionary custom variable ---"
echo "Variable: mysql_conn (fixed-dictionary with pre-defined keys)"
echo
echo "Expected config fragment:"
cat <<'CONF'
  vars.mysql_conn = {
      host     = "db-primary.internal"
      port     = "3306"
      user     = "icinga_monitor"
      password = "s3cr3t"
      database = "app_production"
  }
CONF

echo
echo "REST API call:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=linux-server-01' \
  -d '{
    "mysql_conn": {
      "host":     "db-primary.internal",
      "port":     "3306",
      "user":     "icinga_monitor",
      "password": "s3cr3t",
      "database": "app_production"
    }
  }'
CMD


# ---------------------------------------------------------------------------
# SCENARIO 4: Host with a dynamic-dictionary (disk_checks) + apply-for rule
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 4: Dynamic-dictionary + apply-for rule ---"
echo "Variable: disk_checks (dynamic-dictionary; keys added per host)"
echo
echo "Step 4a — Set disk_checks on host 'linux-server-01':"
echo
echo "Expected config fragment:"
cat <<'CONF'
  vars.disk_checks += {
      "root" = {
          disk_partition = "/"
          disk_wfree     = "20%"
          disk_cfree     = "10%"
      }
      "data" = {
          disk_partition = "/data"
          disk_wfree     = "15%"
          disk_cfree     = "5%"
      }
      "backup" = {
          disk_partition = "/mnt/backup"
          disk_wfree     = "10%"
          disk_cfree     = "5%"
      }
  }
CONF

echo
echo "REST API call:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=linux-server-01' \
  -d '{
    "disk_checks": {
      "root":   { "disk_partition": "/",          "disk_wfree": "20%", "disk_cfree": "10%" },
      "data":   { "disk_partition": "/data",       "disk_wfree": "15%", "disk_cfree": "5%"  },
      "backup": { "disk_partition": "/mnt/backup", "disk_wfree": "10%", "disk_cfree": "5%"  }
    }
  }'
CMD

echo
echo "Step 4b — Apply-for rule (configured in the Director UI):"
cat <<'CONF'
  Apply for: disk_checks
  Service name pattern: disk - $key$
  check_command: disk

  Custom variables in the apply rule:
    disk_partitions → $value.disk_partition$
    disk_wfree      → $value.disk_wfree$
    disk_cfree      → $value.disk_cfree$

  Hint: available $value.*$ fields are shown below the Custom Variables
        section in the apply-rule form.
CONF

echo
echo "Step 4c — Generated Icinga 2 config after deploy:"
cat <<'CONF'
  apply Service "disk - " for (key => value in host.vars.disk_checks) {
      check_command        = "disk"
      vars.disk_partitions = value.disk_partition
      vars.disk_wfree      = value.disk_wfree
      vars.disk_cfree      = value.disk_cfree
      assign where host.vars.disk_checks
  }
CONF

echo
echo "Result: three services created on linux-server-01:"
echo "  disk - root   → checks /          warn=20% crit=10%"
echo "  disk - data   → checks /data       warn=15% crit=5%"
echo "  disk - backup → checks /mnt/backup warn=10% crit=5%"


# ---------------------------------------------------------------------------
# SCENARIO 5: Dynamic-array apply-for rule (http_vhosts_list)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 5: Dynamic-array apply-for rule ---"
echo "Variable: http_vhosts_list (dynamic-array of strings)"
echo
echo "Set http_vhosts_list on host 'web-server-01':"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/host/variables?name=web-host' \
  -d '{
    "http_vhosts_list": [
      "www.example.com",
      "api.example.com",
      "status.example.com"
    ]
  }'
CMD

echo
echo "Generated Icinga 2 config after deploy:"
cat <<'CONF'
  apply Service "http - " for (value in host.vars.http_vhosts_list) {
      check_command    = "http"
      vars.http_address = value
      vars.http_port    = 443
      vars.http_uri     = "/"
      assign where host.vars.http_vhosts_list
  }
CONF

echo
echo "Result: three services on web-server-01:"
echo "  http - www.example.com"
echo "  http - api.example.com"
echo "  http - status.example.com"


# ---------------------------------------------------------------------------
# SCENARIO 6: Other object types (service, user, notification, command)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 6: Custom variables on other object types ---"

echo
echo "Service (individual) — http check parameters:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/service/variables?host=linux-server-01&name=http%20-%20www.example.com' \
  -d '{
    "http_address": "www.example.com",
    "http_port":    443,
    "http_uri":     "/health",
    "ssl_verify":   true,
    "http_expect":  ["HTTP/1.1 200", "HTTP/1.0 200"]
  }'
CMD

echo
echo "User — notification preferences:"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/user/variables?name=test' \
  -d '{
    "username":      "abc123def456"
  }'
CMD

echo
echo "Command — SSH check parameters (includes fixed-array):"
cat <<'CMD'
curl -k -u 'icingaadmin:icinga' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -X PUT 'http://localhost/icingaweb2/director/command/variables?name=check_by_ssh' \
  -d '{
    "by_ssh_logname":   "monitoring",
    "by_ssh_port":      22,
    "by_ssh_quiet":     false,
    "by_ssh_arguments": ["-w", "20", "-c", "10"]
  }'
CMD


# ---------------------------------------------------------------------------
# SCENARIO 7: Datafields migration (CLI)
# ---------------------------------------------------------------------------
echo
echo "--- Scenario 7: Migrate existing data fields to custom properties ---"
echo
echo "Preview what would be migrated (no DB changes):"
cat <<'CMD'
icingacli director migrate datafields --dry-run --verbose
CMD

echo
echo "Run the migration:"
cat <<'CMD'
icingacli director migrate datafields --verbose
CMD

echo
cat <<'INFO'
Fields eligible for migration:
  - Data type: String, Number, Boolean, Array, or Datalist
  - No data category (category_id IS NULL)
  - No duplicate varname
  - Not protected (visibility != hidden)
  - No existing custom property with the same key_name

After migration, the fields appear as custom properties in director_property
and their template assignments are reflected in icinga_<type>_property tables.
INFO


# ---------------------------------------------------------------------------
echo
echo "================================================================="
echo " Demo complete."
echo " See doc/Dictionary-Support-Changes.md for full documentation."
echo "================================================================="
