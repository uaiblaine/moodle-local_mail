# Local mail plugin for Moodle

This plugin allows users to send messages to each other, using an
interface and features similar to webmail clients. Messages are tied
to courses, so users can only contact other participants in courses
the user is enrolled in. Reading/sending of messages is done through a
new item in the navigation block called "My Mail".

Project page: https://gitlab.com/reskity/moodle-local_mail

## Authors

- Marc Català <reskit@gmail.com>
- Albert Gasset <albertgasset@fsfe.org>

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

## Copyright

Copyright © 2012,2013 Institut Obert de Catalunya

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
