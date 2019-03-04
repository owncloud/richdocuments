extra_dirs=assets
extra_files=admin.php settings.php

# Include standard app makefile targets provided by core
include ../../build/rules/help.mk
include ../../build/rules/dist.mk
include ../../build/rules/test-php.mk
include ../../build/rules/clean.mk
