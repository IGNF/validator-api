compile-app:
	composer update
	yarn install
	yarn encore dev

compile-app-prod:
	composer update --no-dev
	yarn install --production
	yarn encore production --progress

run-tests:
	php bin/phpunit

travis: compile-app run-tests

deploy:
	echo "TODO: deploying app"

docker-compose-up:
	chmod +x ./docker-config/docker-compose-up.sh
	./docker-config/docker-compose-up.sh