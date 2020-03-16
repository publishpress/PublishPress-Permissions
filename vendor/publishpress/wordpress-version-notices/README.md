# WordPress Version Notices


## Description

Library for displaying ads for Pro plugins in WordPress.

## Installation

```shell script
$ composer update --no-dev
```

## Integrating into a plugin

Due to a conflict in the tests we didn't register the include file in the Composer's autoload for now. You can manually require it and add a filter for the settings.

```php
if (!defined('PP_VERSION_NOTICES_LOADED')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'publishpress' . DIRECTORY_SEPARATOR
        . 'wordpress-version-notices' . DIRECTORY_SEPARATOR . 'includes.php';
}

add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
    $settings['dumb-plugin-one'] = [
        'message' => 'You\'re using Dumb Plugin One Free. Please, %supgrade to pro%s.',
        'link'    => 'http://example.com/upgrade',
        'screens' => [
            [
                'base'      => 'edit',
                'id'        => 'edit-post',
                'post_type' => 'post',
            ],
        ]
    ];

    return $settings;
});
```

### Params

* message: Type the respective message for the banner, adding the button text between '%s'. This string will be used as format for `sprint` .
* link: The full link for the button
* screen: An array of screen parameters used to decide what page should display the banner. Each item of the array can be a boolean or an array with a key-value array specifying the required params from the screen (WP_Screen) object. You can bypass the library's filter algorithm adding a `true` item to it. This allows you to create your own function to check the screen returning a boolean value. 

## Testing

Create a new WordPress installation dedicated for testing.

Make sure to copy the file `.env.testing.dist` as `.env.testing` and update the variables according to your environment.

Install the dependencies using composer:

```shell script
$ composer install
``` 

or 

```shell script
$ composer update
```

Run the script:

```shell script
$ bin/test.sh
```

The scripts were implemented for *nix systems. Not adapted for Windows yet.

## License

License: [GPLv3 or later](http://www.gnu.org/licenses/gpl-3.0.html)
