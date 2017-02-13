# Make sure icinga2 is installed and running

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

warn() {
  echo "$1" >&2
}

echo -n "check: icinga2 installed - "; if icinga2 --version &>/dev/null ; then echo "OK" ; else fail "FAIL, install icinga2 !"; exit 2; fi

[ "$BASH_VERSION" ] || fail "This is a Bash script"

RHEL_SYSCONFIG="/etc/sysconfig/icinga2"
DEB_SYSCONFIG="/usr/lib/icinga2/icinga2"


if [ -f "$RHEL_SYSCONFIG" ]; then
  ICINGA2_SYSCONFIG_FILE="$RHEL_SYSCONFIG"
elif [ -f "$DEB_SYSCONFIG" ]; then
  ICINGA2_SYSCONFIG_FILE="$DEB_SYSCONFIG"
else
  echo "ERROR: couldn't find your Icinga2 sysconfig file"
fi

. "$ICINGA2_SYSCONFIG_FILE"
[ "$ICINGA2_USER" ] || fail "\$ICINGA2_USER has not been defined"
ICINGA2_CONF_DIR=$(dirname "$ICINGA2_CONFIG_FILE")
ICINGA2_SYSCONF_DIR=$(dirname "$ICINGA2_CONF_DIR")
ICINGA2_INSTALL_PREFIX=$(dirname $(dirname "$DAEMON"))
ICINGA2_CA_DIR="${ICINGA2_STATE_DIR}/lib/icinga2/ca"
ICINGA2_SSL_DIR="${ICINGA2_CONF_DIR}/pki"
ICINGA2_CA_PORT="5665"

. "${ICINGA2_INSTALL_PREFIX}/lib/icinga2/prepare-dirs" "${ICINGA2_SYSCONFIG_FILE}"

if ! [ -d $ICINGA2_SSL_DIR ]; then mkdir $ICINGA2_SSL_DIR; fi
chown $ICINGA2_USER $ICINGA2_SSL_DIR

if [ -f  "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.crt" ]; then
  warn "ERROR: a certificate for '${ICINGA2_NODENAME}' already exists"
  warn "Please remove ${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.??? in case you want a"
  warn "new certificate to be generated and signed by ${ICINGA2_CA_NODE}"
  exit 1
fi

"$DAEMON" pki new-cert --cn "${ICINGA2_NODENAME}" \
  --cert "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.crt" \
  --csr "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.csr" \
  --key "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.key"

"$DAEMON" pki save-cert \
  --host "${ICINGA2_CA_NODE}" \
  --port "${ICINGA2_CA_PORT}" \
  --key "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.key" \
  --trustedcert "${ICINGA2_SSL_DIR}/trusted-master.crt"

"$DAEMON" pki request \
  --host "${ICINGA2_CA_NODE}" \
  --port "${ICINGA2_CA_PORT}" \
  --ticket "${ICINGA2_CA_TICKET}" \
  --key "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.key" \
  --cert "${ICINGA2_SSL_DIR}/${ICINGA2_NODENAME}.crt" \
  --trustedcert "${ICINGA2_SSL_DIR}/trusted-master.crt" \
  --ca "${ICINGA2_SSL_DIR}/ca.crt"


# Write Config Files
CONF_ICINGA2=`cat << EOF
/** Icinga 2 Config - proposed by Icinga Director */

include "constants.conf"
include "zones.conf"
include "features-enabled/*.conf"

include <itl>
include <plugins>
include <plugins-contrib>
EOF
`
ZONES_ICINGA2=`cat << EOF
/** Icinga 2 Config - proposed by Icinga Director */

// TODO: improve establish connection handling
object Endpoint "${ICINGA2_NODENAME}" {}
object Endpoint "${ICINGA2_CA_NODE}" {}
object Zone "${ICINGA2_PARENT_ZONE}" {
  endpoints = [ "$ICINGA2_PARENT_ENDPOINTS" ]
  // TODO: all endpoints in master zone
}

object Zone "director-global" { global = true }

object Zone "${ICINGA2_NODENAME}" {
  parent = "${ICINGA2_PARENT_ZONE}"
  endpoints = [ "$ICINGA2_NODENAME" ]
}
EOF
`

API_ICINGA2=`cat << EOF
/** Icinga 2 Config - proposed by Icinga Director */

object ApiListener "api" {
  cert_path = SysconfDir + "/icinga2/pki/${ICINGA2_NODENAME}.crt"
  key_path = SysconfDir + "/icinga2/pki/${ICINGA2_NODENAME}.key"
  ca_path = SysconfDir + "/icinga2/pki/ca.crt"
  accept_commands = true
  accept_config = true
}
EOF
`

/usr/bin/printf "%b" "$CONF_ICINGA2" > $ICINGA2_CONF_DIR/icinga2.conf
/usr/bin/printf "%b" "$ZONES_ICINGA2" > $ICINGA2_CONF_DIR/zones.conf
/usr/bin/printf "%b" "$API_ICINGA2" > $ICINGA2_CONF_DIR/features-available/api.conf

icinga2 feature enable api

echo "Please restart icinga2!"
