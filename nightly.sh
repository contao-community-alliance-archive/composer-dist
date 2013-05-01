#!/bin/bash

DIR=$(dirname $0)
cd "$DIR"

# update composer-dist
git pull

# update dependencies
php composer.phar update

# build archives
./console build --env prod
./console build --env dev
./console build --env prod-phar
./console build --env dev-phar
cp dist/*.zip ../web/dist/

