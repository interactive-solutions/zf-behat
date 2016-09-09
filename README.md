# InteractiveSolutions/Zf-behat

## Description
A set of predefined step definitions to handle the most common cases
when testing a REST API through Zend Framework.

## Setup
Add it to your `behat.yml` file as follows:

```
default:
  extensions:
    InteractiveSolutions\ZfBehat:
      # configurable parameters
```

The available configurable parameters for this module are:
- `config_file` - path to your autoload configuration file for your api (default `config/application.config.php`).
- `api_url` - base url of your api (default `http://localhost`).
- `mailcatcher_url` - the url to your mailcatcher server (default `http://mailcatcher:1080`).

Enable the module `InteractiveSolutions\ZfBehat` in your autoload config file.

## Available contexts
The module contains a bunch of different steps for various types of testing,
all of which can be seen below. 

*Please see each individual context for further reference*.

### ApiContext
This context contains all step definitions for various api calls.

### BernardContext
Context containing basic steps for testing an application using bernard
to run background tasks.

### DatabaseContext
Manages the Doctrine entity manager. Contains a single step that should be
run as a background in each feature file (`Given a clean database`). This step
drops and rebuilds the entire database, allowing a clean database before each scenario.

### EntityFixtureContext
Contains various steps for setting up a scenario by creating entities.

### MailcatcherContext
Manages mail testing using the specified `mailcatcher_url`. Useful for testing
if activation emails (and similar) are sent.

### ZfrOAuthContext
Contains steps needed for zfr-oauth authentication/authorization.

## Configuring default parameters
Paste the `interactive-solutions.zf-behat.global.php.dist` into your config.

`UserOptions` are used to specify default parameters for users in the steps
defined in the *ZfrOAuthContext*.

```php
UserOptions::class => [
    //The user entity class to use
    'userEntityClass' => UserEntity::class,
    'defaultUserProperties' => [
        'firstName' => 'First',
        'lastName'  => 'Last',
        // password field is automatically bcrypted when entity is generated
        'password'  => '12345',
        'createdAt' => new DateTime(),
    ],
],
```

`EntityOptions` are used to specify default parameters for entities created
and manages through the *EntityFixtureContext* and *ApiContext*.

```php
EntityOptions::class => [
    // fill in the entity keys and their corresponding route/default values
    'entities' => [
        'match' => [
            // aliases are used to provide a better flow in your scenarios
            'aliases' => [
                'matches',
            ],
            'entity'  => MatchEntity::class,
            'route'  => 'matches'
            'defaultProperties' => [
                'type'        => 'SinglePlayer',
                'winner'      => 1,
                'winnerScore' => 1337,
                'finishedAt'  => new DateTime(),
            ]
        ]
    ]
]
```

Things to note with `EntityOptions`
- route is automatically prefixed with `/`, so `matches` becomes `/matches`
- aliases are used to create an alias for a configured entity
- defaultProperties are also used as default parameters when sending api requests

## License
Copyright (c) 2016 Interactive Solutions Bodama AB

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
