#!/bin/bash

# This generates and signs your required certificates. Please do not
# forget to install the Icinga 2 package and your desired monitoring
# plugins first.

# Config from Director
#ICINGA2_NODENAME='@ICINGA2_NODENAME@'
#ICINGA2_CA_TICKET='@ICINGA2_CA_TICKET@'
#ICINGA2_PARENT_ZONE='@ICINGA2_PARENT_ZONE@'
#ICINGA2_PARENT_ENDPOINTS='@ICINGA2_PARENT_ENDPOINTS@'
#ICINGA2_CA_NODE='@ICINGA2_CA_NODE@'
#ICINGA2_GLOBAL_ZONES='@ICINGA2_GLOBAL_ZONES@'

# Internal defaults
: "${ICINGA2_OSFAMILY:=}"
: "${ICINGA2_HOSTNAME:="$(hostname -f)"}"
: "${ICINGA2_NODENAME:="${ICINGA2_HOSTNAME}"}"
: "${ICINGA2_CA_NODE:=}"
: "${ICINGA2_CA_PORT:=5665}"
: "${ICINGA2_CA_TICKET:=}"
: "${ICINGA2_PARENT_ZONE:=master}"
: "${ICINGA2_PARENT_ENDPOINTS:=()}"
: "${ICINGA2_GLOBAL_ZONES:=director-global}"
: "${ICINGA2_DRYRUN:=}"
: "${ICINGA2_UPDATE_CONFIG:=}"

# Helper functions
fail() {
  echo "ERROR: $1" >&2
  exit 1
}

warn() {
  echo "WARNING: $1" >&2
}

info() {
  echo "INFO: $1" >&2
}

check_command() {
  command -v "$@" &>/dev/null
}

install_config() {
  if [ -e "$1" ] && [ ! -e "${1}.orig" ]; then
    info "Creating a backup at ${1}.orig"
    cp "$1" "${1}.orig"
  fi
  echo "Writing config to ${1}"
  echo "$2" > "${1}"
}

[ "$BASH_VERSION" ] || fail "This is a Bash script"

errors=
for key in NODENAME CA_NODE CA_PORT CA_TICKET PARENT_ZONE PARENT_ENDPOINTS; do
  var="ICINGA2_${key}"
  if [ -z "${!var}" ]; then
    warn "The variable $var needs to be configured!"
    errors+=1
  fi
done
[ -z "$errors" ] || exit 1

# Detect osfamily
if [ -n "$ICINGA2_OSFAMILY" ]; then
  info "Assuming supplied osfamily $ICINGA2_OSFAMILY"
elif check_command rpm && ! check_command dpkg; then
  info "This should be a RedHat system"
  if [ -e /etc/sysconfig/icinga2 ]; then
    # shellcheck disable=SC1091
    . /etc/sysconfig/icinga2
  fi
  ICINGA2_OSFAMILY=redhat
elif check_command dpkg; then
  info "This should be a Debian system"
  if [ -e /etc/default/icinga2 ]; then
    # shellcheck disable=SC1091
    . /etc/default/icinga2
  fi
  ICINGA2_OSFAMILY=debian
elif check_command apk; then
  info "This should be a Alpine system"
  if [ -e /etc/icinga2/icinga2.sysconfig ]; then
    # shellcheck disable=SC1091
    . /etc/icinga2/icinga2.sysconfig
  fi
  ICINGA2_OSFAMILY=alpine
else
  fail "Could not determine your os type!"
fi

# internal defaults
: "${ICINGA2_CONFIG_FILE:=/etc/icinga2/icinga2.conf}"
: "${ICINGA2_CONFIGDIR:="$(dirname "$ICINGA2_CONFIG_FILE")"}"
: "${ICINGA2_DATADIR:=/var/lib/icinga2}"
: "${ICINGA2_SSLDIR_OLD:="${ICINGA2_CONFIGDIR}"/pki}"
: "${ICINGA2_SSLDIR_NEW:="${ICINGA2_DATADIR}"/certs}"
: "${ICINGA2_SSLDIR:=}"
: "${ICINGA2_BIN:=icinga2}"

case "$ICINGA2_OSFAMILY" in
debian)
  : "${ICINGA2_USER:=nagios}"
  : "${ICINGA2_GROUP:=nagios}"
  ;;
redhat)
  : "${ICINGA2_USER:=icinga}"
  : "${ICINGA2_GROUP:=icinga}"
  ;;
alpine)
  : "${ICINGA2_USER:=icinga}"
  : "${ICINGA2_GROUP:=icinga}"
  ;;
*)
  fail "Unknown osfamily '$ICINGA2_OSFAMILY'!"
  ;;
esac

icinga_version() {
  "$ICINGA2_BIN" --version 2>/dev/null | grep -oPi '\(version: [rv]?\K\d+\.\d+\.\d+[^\)]*'
}

version() {
   echo "$@" | awk -F. '{ printf("%03d%03d%03d\n", $1,$2,$3); }'
}

# Make sure icinga2 is installed and running
echo -n "check: icinga2 installed - "
if version=$(icinga_version); then
  echo "OK: $version"
else
  fail "You need to install icinga2!"
fi

if [ -z "${ICINGA2_SSLDIR}" ]; then
  if [ -f "${ICINGA2_SSLDIR_OLD}/${ICINGA2_NODENAME}.crt" ]; then
    info "Using old SSL directory: ${ICINGA2_SSLDIR_OLD}"
    info "Because you already have a certificate in ${ICINGA2_SSLDIR_OLD}/${ICINGA2_NODENAME}.crt"
    ICINGA2_SSLDIR="${ICINGA2_SSLDIR_OLD}"
  elif [ $(version $version) -gt $(version 2.8) ]; then
    info "Using new SSL directory: ${ICINGA2_SSLDIR_NEW}"
    ICINGA2_SSLDIR="${ICINGA2_SSLDIR_NEW}"
  else
    info "Using old SSL directory: ${ICINGA2_SSLDIR_OLD}"
    ICINGA2_SSLDIR="${ICINGA2_SSLDIR_OLD}"
  fi
fi

if [ ! -d "$ICINGA2_SSLDIR" ]; then
  mkdir "$ICINGA2_SSLDIR"
  chown "$ICINGA2_USER.$ICINGA2_GROUP" "$ICINGA2_SSLDIR"
fi

if [ -f "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.crt" ]; then
  warn "ERROR: a certificate for '${ICINGA2_NODENAME}' already exists"
  warn "Please remove ${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.??? in case you want a"
  warn "new certificate to be generated and signed by ${ICINGA2_CA_NODE}"

  if [ -z "${ICINGA2_UPDATE_CONFIG}" ] && [ -z "${ICINGA2_DRYRUN}" ]; then
    warn "Aborting here, you can can call the script like this to just update config:"
    info " ICINGA2_UPDATE_CONFIG=1 $0"
    exit 1
  fi
elif [ -z "${ICINGA2_DRYRUN}" ]; then
  if ! "$ICINGA2_BIN" pki new-cert --cn "${ICINGA2_NODENAME}" \
    --cert "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.crt" \
    --csr "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.csr" \
    --key "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.key"
  then fail "Could not create self signed certificate!"
  fi

  if ! "$ICINGA2_BIN" pki save-cert \
    --host "${ICINGA2_CA_NODE}" \
    --port "${ICINGA2_CA_PORT}" \
    --key "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.key" \
    --trustedcert "${ICINGA2_SSLDIR}/trusted-master.crt"
  then fail "Could not retrieve trusted certificate from host ${ICINGA2_CA_NODE}"
  fi

  if ! "$ICINGA2_BIN" pki request \
    --host "${ICINGA2_CA_NODE}" \
    --port "${ICINGA2_CA_PORT}" \
    --ticket "${ICINGA2_CA_TICKET}" \
    --key "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.key" \
    --cert "${ICINGA2_SSLDIR}/${ICINGA2_NODENAME}.crt" \
    --trustedcert "${ICINGA2_SSLDIR}/trusted-master.crt" \
    --ca "${ICINGA2_SSLDIR}/ca.crt"
  then fail "Could not retrieve final certificate from host ${ICINGA2_CA_NODE}"
  fi
else
  info "Would create certificates under ${ICINGA2_SSLDIR}, but in dry-run!"
fi

# Prepare Config Files
content_config=$(cat << EOF
/** Icinga 2 Config - proposed by Icinga Director */

include "constants.conf"

$([ "${ICINGA2_HOSTNAME}" != "${ICINGA2_NODENAME}" ] || echo '// ')const NodeName = "${ICINGA2_NODENAME}"

include "zones.conf"
include "features-enabled/*.conf"

include <itl>
include <plugins>
include <plugins-contrib>
include <manubulon>
include <windows-plugins>
include <nscp>
EOF
)

endpoint_list=''
for item in "${ICINGA2_PARENT_ENDPOINTS[@]}"; do
  endpoint=$(echo "$item" | cut -d, -f1)
  endpoint_list+="\"${endpoint}\", "
done

content_zones=$(cat << EOF
/** Icinga 2 Config - proposed by Icinga Director */

object Endpoint "${ICINGA2_NODENAME}" {}

object Zone "${ICINGA2_NODENAME}" {
  parent = "${ICINGA2_PARENT_ZONE}"
  endpoints = [ "${ICINGA2_NODENAME}" ]
}

object Zone "${ICINGA2_PARENT_ZONE}" {
  endpoints = [ ${endpoint_list%, } ]
}
EOF
)

for item in "${ICINGA2_PARENT_ENDPOINTS[@]}"; do
  endpoint=$(echo "$item" | cut -d, -f1)
  host=$(echo "$item" | cut -s -d, -f2)

  content_zones+=$(cat << EOF

object Endpoint "${endpoint}" {
$([ -n "$host" ] && echo "  host = \"${host}\"" || echo "  //host = \"${endpoint}\"")
}
EOF
)
done

for zone in "${ICINGA2_GLOBAL_ZONES[@]}"; do
  content_zones+=$(cat << EOF

object Zone "${zone}" {
  global = true
}
EOF
)
done

content_api="/** Icinga 2 Config - proposed by Icinga Director */

object ApiListener \"api\" {"

if [ "${ICINGA2_SSLDIR}" = "${ICINGA2_SSLDIR_OLD}" ]; then
content_api+="
  cert_path = SysconfDir + \"/icinga2/pki/${ICINGA2_NODENAME}.crt\"
  key_path = SysconfDir + \"/icinga2/pki/${ICINGA2_NODENAME}.key\"
  ca_path = SysconfDir + \"/icinga2/pki/ca.crt\"
"
fi
content_api+="
  accept_commands = true
  accept_config = true
}
"

if [ -z "${ICINGA2_DRYRUN}" ]; then
  install_config "$ICINGA2_CONFIGDIR"/icinga2.conf "$content_config"
  install_config "$ICINGA2_CONFIGDIR"/zones.conf "$content_zones"
  install_config "$ICINGA2_CONFIGDIR"/features-available/api.conf "$content_api"

  "$ICINGA2_BIN" feature enable api

  "$ICINGA2_BIN" daemon -C

  echo "Please restart icinga2:"
  case "$ICINGA2_OSFAMILY" in
  debian)
    echo "  systemctl restart icinga2"
    ;;
  redhat)
    echo "  systemctl restart icinga2"
    ;;
  alpine)
    echo "  rc-service icinga2 restart"
    ;;
  *)
    fail "Unknown osfamily '$ICINGA2_OSFAMILY'!"
    ;;
  esac
else
  output_code() {
    sed 's/^/    /m' <<<"$1"
  }
  echo "### $ICINGA2_CONFIGDIR"/icinga2.conf
  echo
  output_code "$content_config"
  echo
  echo "### $ICINGA2_CONFIGDIR"/zones.conf
  echo
  output_code "$content_zones"
  echo
  echo "### $ICINGA2_CONFIGDIR"/features-available/api.conf
  echo
  output_code "$content_api"
fi
