## create the user network - what if already created ??
docker network create TB-NETWORK
## remove container name from the embedded DNS server
docker rm TB-MYSQLMGR

docker run --name=TB-MYSQLSERVER --network TB-NETWORK \
--mount type=bind,src=/Users/scuric/go/testbed/db/my.cnf,dst=/et/my.cnf \
--mount type=bind,src=/Users/scuric/go/testbed/db/data,dst=/var/lib/mysql \
--mount type=bind,src=/Users/scuric/go/testbed/db/scripts/,dst=/docker-entrypoint-initdb.d/ \
--env MYSQL_DATABASE=tbdb \
--env MYSQL_ROOT_HOST=% \
--env MYSQL_ALLOW_EMPTY_PASSWORD=true \
--env MYSQL_USER=scuric \
--env MYSQL_PASSWORD="" \
-d mysql/mysql-server:latest 
