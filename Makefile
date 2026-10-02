test: vendor
	# see SYMFONY_DEPRECATIONS_HELPER in phpunit.xml.dist
	rm -rf var/log/test.deprecations.log
	# test database (validator_api_test, see when@test in config/packages/doctrine.yaml)
	APP_ENV=test php bin/console doctrine:database:create --if-not-exists
	APP_ENV=test XDEBUG_MODE=coverage vendor/bin/phpunit \
		--coverage-clover var/data/output/coverage.xml \
		--coverage-html var/data/output/coverage/

.PHONY: check-rules
check-rules:
	@echo "-- Checking coding rules using phpstan"
	vendor/bin/phpstan analyse -c phpstan.neon --error-format=raw

.PHONY: fix-style
fix-style:
	@echo "-- Fixing coding style using php-cs-fixer..."
	vendor/bin/php-cs-fixer fix

.PHONY: check-style
check-style:
	@echo "-- Checking coding style using php-cs-fixer (run 'make fix-style' if it fails)"
	vendor/bin/php-cs-fixer fix -v --dry-run --diff

.PHONY: vendor
vendor:
	composer install

.PHONY: clean
clean:
	# note : composer.lock, symfony.lock and package-lock.json are versioned (not removed)
	rm -rf vendor
	rm -rf var
	rm -rf node_modules
	rm -rf public/build public/vendor public/css public/font public/img
	rm -f *.log
	rm -f .php-cs-fixer.cache .phpunit.result.cache
