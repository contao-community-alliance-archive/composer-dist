Contao composer distribution package builder
============================================

This builder create a pre-packed archive of the contao composer integration.
The archive can be used to install composer just by copying into the contao installation.

The builder can create a `prod` archive, just for production usage.
Or a `dev` archive, including vcs informations.

Nightly build
-------------

* regular usage: http://legacy-packages-via.contao-community-alliance.org/target/contao-composer.zip
* development usage (including vcs information): http://legacy-packages-via.contao-community-alliance.org/target/contao-composer-dev.zip

Commands
--------

Build `prod` archive: `./console build` or `./console build --env prod`

Build `dev` archive: `./console build --env dev`

Cleanup: `./console clean` or `./console clean --env prod` or `./console clean --env dev`

Custom env
----------

To create you custom environment copy `prod` or `dev` and modify it for your purpose.
If your environment name contains `dev` e.g. `customdev`, then vcs informations are included.
