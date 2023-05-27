#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

apt-get install postgresql-client -yqq

#export PGPASSWORD=$POSTGRES_PASSWORD

psql -h "postgres" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -w < sql/structure.sql
psql -h "postgres" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -w < sql/data_fr.sql
