# Local mail plugin for Moodle

This plugin allows users to send messages to each other, using an
interface and features similar to webmail clients. Messages are tied
to courses, so users can only contact other participants in courses
the user is enrolled in. Reading/sending of messages is done through a
new item in the navigation block called "My Mail".

Project page: https://gitlab.com/reskity/moodle-local_mail



## Installation

Unpack archive inside `/path/to/moodle/local/mail`

For general instructions on installing plugins see:
https://docs.moodle.org/401/en/Installing_plugins

## Developement

### Svelte

The client side components are written using [Svelte](https://svelte.dev).

To use the Svelte developement server you need to:

1. Set this setting in `config.php`:
   ```
   $CFG->local_mail_devserver = 'http://localhost:5173';
   ```

2. Start the developement server with:
   ```
   cd local/mail/svelte
   npm install
   npm run dev
   ```

To build the code for production:
```
cd local/mail/svelte
npm install
npm run build
```

The production code is stored in `local/mail/build`.

### Unit tests

See: https://moodledev.io/general/development/tools/phpunit

Initialize test environment:
```
php admin/tool/phpunit/cli/init.php
php admin/tool/phpunit/cli/util.php --buildcomponentconfigs
```

Run unit tests:
```
vendor/bin/phpunit -c local/mail
```

Run unit tests and generate code coverage report:
```
php -dpcov.enabled=1 vendor/bin/phpunit -c local/mail \
    --coverage-html=local/mail/coverage
```

## Test data generator

This script generates random fake messages amongst users for testing.

WARNING: The script deletes all existing mail data.

```
php local/mail/cli/generate.php
```

## Credits

Maintainers:

- Marc Català <reskit@gmail.com>
- Albert Gasset <albertgasset@fsfe.org>

Contributors:

- Daniel Barnett
- Manuel Cagigas
- Russell Smith

## Copyright

© 2012-2014 Institut Obert de Catalunya <https://ioc.gencat.cat>

© 2014-2022 Marc Català <reskit@gmail.com>

© 2016-2018 Albert Gasset <albertgasset@fsfe.org>

© 2023 SEIDOR <https://www.seidor.com>

# License

This plugin is distributed under the terms of the GNU General Public License,
version 3 or later.

See the [LICENSES/GPL-3.0-or-later.txt](LICENSES/GPL-3.0-or-later.txt) file for details.
