build: 7.3.build

publish: 7.3.publish

%.build:
	@docker build --build-arg PHP_VERSION="$*" -t registry.ddbuild.io/apm-integrations-testing/handmade/php-laravel:$* .

%.shell: %.build
	@docker-compose run --rm $* bash

%.publish: %.build
	@docker push registry.ddbuild.io/apm-integrations-testing/handmade/php-laravel:$*
