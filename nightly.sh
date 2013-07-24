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
cp target/*.zip ../web/dist/

