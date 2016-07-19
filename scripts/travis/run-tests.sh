#!/bin/bash

if [ -n "$COVERAGE" ]
then
	# Run the network tests in parallel to populate the cache
	CACHE_PRELOAD=1 phpunit --group needs-network tests/Plugins/MediaEmbed/ParserTest.php > /dev/null &

	# Wake up the remote minifier in case it's idling
	curl -I http://s9e-textformatter.rhcloud.com/ 2>/dev/null >/dev/null &

	composer require phpunit/phpunit
	phpdbg -qrr vendor/bin/phpunit --exclude-group needs-js --coverage-clover /tmp/clover.xml
else
	phpunit --exclude-group needs-network
fi