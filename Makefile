SHELL := /bin/bash

COMPOSER_BIN := $(shell command -v composer 2> /dev/null)
ifndef COMPOSER_BIN
    $(error composer is not available on your system, please install composer)
endif

NPM := $(shell command -v npm 2> /dev/null)
ifndef NPM
    $(error npm is not available on your system, please install npm)
endif

NODE_PREFIX=$(shell pwd)

app_name=richdocuments
project_dir=$(CURDIR)/../$(app_name)
doc_files=README.md
src_dirs=appinfo assets css img js l10n lib templates
src_files=admin.php settings.php
all_src=$(src_dirs) $(src_files) $(doc_files)
build_dir=$(CURDIR)/build
dist_dir=$(build_dir)/dist
sign_dir=$(build_dir)/sign
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
package_name=$(app_name)
cert_dir=$(HOME)/.owncloud/certificates
occ=$(CURDIR)/../core/occ

# composer
composer_deps=vendor
acceptance_test_deps=vendor-bin/behat/vendor

appstore:
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=.git \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=l10n/.tx \
	--exclude=l10n/no-php \
	--exclude=Makefile \
	--exclude=nbproject \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=vendor/bin \
	$(project_dir) $(sign_dir)
	@echo "Signing…"
	$(occ) integrity:sign-app --privateKey=$(cert_dir)/$(app_name).key --certificate=$(cert_dir)/$(app_name).crt --path=$(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name).tar.gz -C $(sign_dir) $(app_name)

# signing
occ=$(CURDIR)/../../occ
private_key=$(HOME)/.owncloud/certificates/$(app_name).key
certificate=$(HOME)/.owncloud/certificates/$(app_name).crt
sign=$(occ) integrity:sign-app --privateKey="$(private_key)" --certificate="$(certificate)"
sign_skip_msg="Skipping signing, either no key and certificate found in $(private_key) and $(certificate) or occ can not be found at $(occ)"
ifneq (,$(wildcard $(private_key)))
ifneq (,$(wildcard $(certificate)))
ifneq (,$(wildcard $(occ)))
	CAN_SIGN=true
endif
endif
endif

# bin file definitions
PHPUNIT=php -d zend.enable_gc=0  "$(PWD)/../../lib/composer/bin/phpunit"
PHPUNITDBG=phpdbg -qrr -d memory_limit=4096M -d zend.enable_gc=0 "$(PWD)/../../lib/composer/bin/phpunit"
PHP_CS_FIXER=php -d zend.enable_gc=0 vendor-bin/owncloud-codestyle/vendor/bin/php-cs-fixer
PHP_CODESNIFFER=vendor-bin/php_codesniffer/vendor/bin/phpcs
PHAN=php -d zend.enable_gc=0 vendor-bin/phan/vendor/bin/phan
PHPSTAN=php -d zend.enable_gc=0 vendor-bin/phpstan/vendor/bin/phpstan
BEHAT_BIN=vendor-bin/behat/vendor/bin/behat

.DEFAULT_GOAL := help

# start with displaying help
help: ## Show this help message
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//' | sed -e 's/  */ /' | column -t -s :

#
# Node dependencies
#
$(nodejs_deps): package.json
	$(NPM) install --prefix $(NODE_PREFIX) && touch $@

$(BOWER): $(nodejs_deps)
$(JSDOC): $(nodejs_deps)

$(bower_deps): $(BOWER)
	$(BOWER) install && touch $@

#
# dist
#
$(dist_dir)/$(app_name): $(composer_deps) $(bower_deps)
	rm -Rf $@; mkdir -p $@
	cp -R $(all_src) $@

ifdef CAN_SIGN
	$(sign) --path="$(dist_dir)/$(app_name)"
else
	@echo $(sign_skip_msg)
endif
	tar -czf $(dist_dir)/$(app_name).tar.gz -C $(dist_dir) $(app_name)

.PHONY: dist
dist: $(dist_dir)/$(app_name)

.PHONY: clean-dist
clean-dist:
	rm -Rf $(dist_dir)

.PHONY: clean-build
clean-build:
	rm -Rf $(build_dir)

.PHONY: clean-deps
clean-deps:
	rm -Rf $(nodejs_deps) $(bower_deps)
	rm -Rf vendor
	rm -Rf vendor-bin/**/vendor vendor-bin/**/composer.lock

.PHONY: clean
clean: clean-deps clean-dist clean-build

##-------------
## Tests
##-------------

.PHONY: test-php-unit
test-php-unit: ## Run php unit tests
test-php-unit: vendor/bin/phpunit
	$(PHPUNIT) --configuration ./phpunit.xml --testsuite unit

.PHONY: test-php-unit-dbg
test-php-unit-dbg: ## Run php unit tests using phpdbg
test-php-unit-dbg: vendor/bin/phpunit
	$(PHPUNITDBG) --configuration ./phpunit.xml --testsuite unit

.PHONY: test-php-style
test-php-style: ## Run php-cs-fixer and check owncloud code-style
test-php-style: vendor-bin/owncloud-codestyle/vendor vendor-bin/php_codesniffer/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes --dry-run
	$(PHP_CODESNIFFER) --runtime-set ignore_warnings_on_exit --standard=phpcs.xml tests/acceptance

.PHONY: test-php-style-fix
test-php-style-fix: ## Run php-cs-fixer and fix code style issues
test-php-style-fix: vendor-bin/owncloud-codestyle/vendor
	$(PHP_CS_FIXER) fix -v --diff --diff-format udiff --allow-risky yes

.PHONY: test-php-phan
test-php-phan: ## Run phan
test-php-phan: vendor-bin/phan/vendor
	$(PHAN) --config-file .phan/config.php --require-config-exists --allow-polyfill-parser

.PHONY: test-php-phpstan
test-php-phpstan: ## Run phpstan
test-php-phpstan: vendor-bin/phpstan/vendor
	$(PHPSTAN) analyse --memory-limit=4G --configuration=./phpstan.neon --no-progress --level=5 appinfo lib

.PHONY: test-acceptance-api
test-acceptance-api: ## Run API acceptance tests
test-acceptance-api: $(acceptance_test_deps)
	BEHAT_BIN=$(BEHAT_BIN) ../../tests/acceptance/run.sh --remote --type api

.PHONY: test-acceptance-webui
test-acceptance-webui: ## Run webUI acceptance tests
test-acceptance-webui: $(acceptance_test_deps)
	BEHAT_BIN=$(BEHAT_BIN) ../../tests/acceptance/run.sh --remote --type webUI

#
# Dependency management
#--------------------------------------

composer.lock: composer.json
	@echo composer.lock is not up to date.

vendor: composer.lock
	composer install --no-dev

vendor/bin/phpunit: composer.lock
	composer install

vendor/bamarni/composer-bin-plugin: composer.lock
	composer install

vendor-bin/owncloud-codestyle/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/owncloud-codestyle/composer.lock
	composer bin owncloud-codestyle install --no-progress

vendor-bin/owncloud-codestyle/composer.lock: vendor-bin/owncloud-codestyle/composer.json
	@echo owncloud-codestyle composer.lock is not up to date.

vendor-bin/php_codesniffer/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/php_codesniffer/composer.lock
	composer bin php_codesniffer install --no-progress

vendor-bin/php_codesniffer/composer.lock: vendor-bin/php_codesniffer/composer.json
	@echo php_codesniffer composer.lock is not up to date.

vendor-bin/phan/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/phan/composer.lock
	composer bin phan install --no-progress

vendor-bin/phan/composer.lock: vendor-bin/phan/composer.json
	@echo phan composer.lock is not up to date.

vendor-bin/phpstan/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/phpstan/composer.lock
	composer bin phpstan install --no-progress

vendor-bin/phpstan/composer.lock: vendor-bin/phpstan/composer.json
	@echo phpstan composer.lock is not up to date.

vendor-bin/behat/vendor: vendor/bamarni/composer-bin-plugin vendor-bin/behat/composer.lock
	composer bin behat install --no-progress

vendor-bin/behat/composer.lock: vendor-bin/behat/composer.json
	@echo behat composer.lock is not up to date.
