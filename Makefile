VERSION=$(shell grep '<version>' appinfo/info.xml | sed -e 's/^.*<version>\(.*\)<\/version>.*$$/\1/')
app_name=richdocuments
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
sign_dir=$(build_dir)/sign
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
package_name=$(app_name)
cert_dir=$(HOME)/.owncloud/certificates
occ=$(CURDIR)/../core/occ

.PHONY: dist
dist: appinfo/info.xml
	rm -rf owncloud-collabora-online-$(VERSION)
	mkdir owncloud-collabora-online-$(VERSION)
	tar cf - *.php \
                appinfo \
                assets \
                controller \
                css \
                img \
                js \
                l10n \
                lib \
                templates \
                | ( cd owncloud-collabora-online-$(VERSION) && tar xf - )
	tar cfz owncloud-collabora-online-$(VERSION).tar.gz owncloud-collabora-online-$(VERSION)
	rm -rf owncloud-collabora-online-$(VERSION)

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
	--exclude=composer.phar \
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
