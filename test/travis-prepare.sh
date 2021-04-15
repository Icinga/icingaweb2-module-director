#!/bin/bash

set -ex

: "${DIRECTOR_TESTDB:=director_test}"

psql_cmd() {
    psql -U postgres ${DIRECTOR_TESTDB} -q -c "$@"
}

if [ "$DB" = mysql ]; then
    mysql -u root -e "DROP DATABASE IF EXISTS ${DIRECTOR_TESTDB}; CREATE DATABASE ${DIRECTOR_TESTDB};"
elif [ "$DB" = pgsql ]; then
    : "${DIRECTOR_TESTDB_USER:=director_test}"

    psql -U postgres postgres -q -c "DROP DATABASE IF EXISTS ${DIRECTOR_TESTDB};"
    psql -U postgres postgres -q -c "CREATE DATABASE ${DIRECTOR_TESTDB} WITH ENCODING 'UTF8';"
    psql_cmd "CREATE USER ${DIRECTOR_TESTDB_USER} WITH PASSWORD 'testing';"
    psql_cmd "GRANT ALL PRIVILEGES ON DATABASE ${DIRECTOR_TESTDB} TO ${DIRECTOR_TESTDB_USER};"
    psql_cmd "CREATE EXTENSION pgcrypto;"
else
    echo "Unknown database set in environment!" >&2
    env
    exit 1
fi
