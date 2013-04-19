#!/bin/bash

cd $(dirname $0)

# switch to composer directory
cd ../composer || exit 1

# cleanup
rm -r cache vendor composer.lock composer.phar

# install composer.phar
curl -sS https://getcomposer.org/installer | php || exit 1

# install dependencies
php composer.phar install || exit 1

# change directory up
cd ../ || exit 1

# pack archive (yes it is wanted to not save symlinks as symlinks, because ER2 cannot handle symlinks)
zip -r dist/contao-composer.zip composer/composer.json composer/composer.lock composer/vendor system/modules || exit 1
