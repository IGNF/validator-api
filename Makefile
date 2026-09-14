PHP_CS_RULES=@Symfony
PHP_MD_RULES=./phpmd.xml

test: vendor
	# see SYMFONY_DEPRECATIONS_HELPER in phpunit.xml.dist
	rm -rf var/log/test.deprecations.log
	APP_ENV=test XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: check-rules
check-rules:
	@echo "-- Checking coding rules using phpmd (see @SuppressWarning to bypass control)"
	vendor/bin/phpmd src text $(PHP_MD_RULES)
	@echo "-- Checking coding rules using phpstan"
	vendor/bin/phpstan analyse -c phpstan.neon --error-format=raw

.PHONY: fix-style
fix-style:
	@echo "-- Fixing coding style using php-cs-fixer..."
	vendor/bin/php-cs-fixer fix src --rules $(PHP_CS_RULES) --using-cache=no
	vendor/bin/php-cs-fixer fix tests --rules $(PHP_CS_RULES) --using-cache=no

.PHONY: check-style
check-style:
	@echo "-- Checking coding style using php-cs-fixer (run 'make fix-style' if it fails)"
	vendor/bin/php-cs-fixer fix src --rules $(PHP_CS_RULES) -v --dry-run --diff --using-cache=no
	vendor/bin/php-cs-fixer fix tests --rules $(PHP_CS_RULES) -v --dry-run --diff --using-cache=no

.PHONY: vendor
vendor:
	composer install

.PHONY: clean
clean:
	rm -rf vendor
	rm -rf var
	rm -rf output
	rm -f *.log
	rm -f *.lock
	rm -f package-lock.json
	rm -f .php_cs.cache
	rm -rf output
	rm -rf node_modules
	rm -rf .scannerwork
	rm -rf sonar-scanner
