#!/bin/bash

set -ex

psql_cmd() {
    psql -U postgres ${DIRECTOR_TESTDB} -q -c "$@"
}

if [ "$DB" = mysql ]; then
    mysql -u root -e "CREATE DATABASE ${DIRECTOR_TESTDB};"
elif [ "$DB" = pgsql ]; then
    psql -U postgres postgres -q -c "CREATE DATABASE ${DIRECTOR_TESTDB} WITH ENCODING 'UTF8';"
    psql_cmd "CREATE USER ${DIRECTOR_TESTDB_USER} WITH PASSWORD 'testing';"
    psql_cmd "GRANT ALL PRIVILEGES ON DATABASE ${DIRECTOR_TESTDB} TO ${DIRECTOR_TESTDB_USER};"
    psql_cmd "CREATE EXTENSION pgcrypto;"
else
    echo "Unknown database set in environment!" >&2
    env
    exit 1
fi
