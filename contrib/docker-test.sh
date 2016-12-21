#!/bin/bash

MYSQL_CONTAINER=icingaweb2_director_mysql

echo "Starting MySQL container..."
docker run -d \
    -e MYSQL_ROOT_PASSWORD=onlyforadmin \
    -e MYSQL_DATABASE=icingaweb2 \
    -e MYSQL_USER=icingaweb2 \
    -e MYSQL_PASSWORD=rosebud \
    --name "$MYSQL_CONTAINER" \
    mariadb >/dev/null

echo "Running tests..."
docker run --rm -i \
    --link "$MYSQL_CONTAINER":mysql \
    -v `pwd`:/app \
    -e DIRECTOR_TESTDB_RES="Director MySQL TestDB" \
    -e DIRECTOR_TESTDB_HOST="mysql" \
    -e DIRECTOR_TESTDB_USER="icingaweb2" \
    -e DIRECTOR_TESTDB_PASSWORD="rosebud" \
    -e DIRECTOR_TESTDB="icingaweb2" \
    lazyfrosch/icingaweb2-test:5.6 \
    sh - \
<<EOF
    set -ex
    cd /app
    composer install
    {
        set +x
        count=0
        while ! nc -w 1 -z mysql 3306
        do
            echo "Waiting for MySQL to get ready..."
            sleep 2
            : \$((count++))
            if [ \$count -gt 10 ]; then
                echo "Waiting for MySQL timed out!"
                exit 2
            fi
        done
    }
    ret=0
    composer exec phpunit --verbose || ret=\$?
    #composer exec phpcs application/ library/ test/php/ *.php || ret=\$?
    exit \$ret
EOF

echo "Stopping MySQL container..."
docker kill "$MYSQL_CONTAINER"
docker rm "$MYSQL_CONTAINER"
