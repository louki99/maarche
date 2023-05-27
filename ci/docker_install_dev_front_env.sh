#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

curl -sL https://deb.nodesource.com/setup_18.x | bash - > /dev/null
apt-get install -y nodejs
npm install -g n
n 18
node -v
npm -v