#!/bin/sh

# Copy the dumb plugin into the test site
echo 'Copying the dumb plugin one'
rsync -a tests/_data/dumb-plugin-one ~/Volumes/wordpress_tests/wp-content/plugins/
echo 'Copying the dumb plugin two'
rsync -a tests/_data/dumb-plugin-two ~/Volumes/wordpress_tests/wp-content/plugins/

# Copy the library into the dumb plugin
echo 'Copying the library into the dumb plugin one'
rsync -a ./ ~/Volumes/wordpress_tests/wp-content/plugins/dumb-plugin-one/vendor/publishpress/wordpress-version-notices/ --exclude .git
echo 'Copying the library into the dumb plugin two'
rsync -a ./ ~/Volumes/wordpress_tests/wp-content/plugins/dumb-plugin-two/vendor/publishpress/wordpress-version-notices/ --exclude .git
