PHP_CS_RULES=@Symfony
PHP_MD_RULES=./phpmd.xml

compile-app:
	composer update
	yarn install
	yarn encore dev

compile-app-prod:
	composer update --no-dev
	yarn install --production
	yarn encore production --progress

run-tests:
	vendor/bin/simple-phpunit

ci: compile-app run-tests

deploy:
	echo "TODO: deploying app"

check-rules:
	@echo "-- Checking coding rules using phpmd (see @SuppressWarning to bypass control)"
	vendor/bin/phpmd src text $(PHP_MD_RULES)
	@echo "-- Checking coding rules using phpstan"
	vendor/bin/phpstan analyse -c phpstan.neon --error-format=raw

fix-style:
	@echo "-- Fixing coding style using php-cs-fixer..."
	vendor/bin/php-cs-fixer fix src --rules $(PHP_CS_RULES) --using-cache=no
	vendor/bin/php-cs-fixer fix tests --rules $(PHP_CS_RULES) --using-cache=no

check-style:
	@echo "-- Checking coding style using php-cs-fixer (run 'make fix-style' if it fails)"
	vendor/bin/php-cs-fixer fix src --rules $(PHP_CS_RULES) -v --dry-run --diff --using-cache=no
	vendor/bin/php-cs-fixer fix tests --rules $(PHP_CS_RULES) -v --dry-run --diff --using-cache=no

clean:
	rm -rf vendor
	rm -rf var
	rm -rf output
	rm -f *.log
	rm -f *.lock
	rm -f package-lock.json
	rm -f .php_cs.cache
	rm -rf public/build
	rm -rf node_modules
	rm -rf .scannerwork
	rm -rf sonar-scanner
