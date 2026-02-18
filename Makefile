PLUGIN_NAME := cbgjvisibility
PLUGIN_TYPE := system
PLUGIN_MANIFEST_XML := cbgjvisibility.xml
PLUGIN_UPDATE_XML := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME).update.xml
INSTALL_DIR := installation

PLUGIN_VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(PLUGIN_MANIFEST_XML))

ZIP_VERSION := $(subst .,-,$(PLUGIN_VERSION))
ZIP_NAME := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)-v$(ZIP_VERSION).zip
ZIP_PATH := $(INSTALL_DIR)/$(ZIP_NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO ?= joomla_plg_cbgjvisibility
GITHUB_REF ?= $(PLUGIN_VERSION)

PLUGIN_FILES := cbgjvisibility.xml LICENSE services/ src/ language/ media/

.PHONY: dist info clean

info:
	@echo "Plugin:          plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)"
	@echo "Plugin version:  $(PLUGIN_VERSION)"
	@echo "Source:          $(PLUGIN_FILES)"
	@echo "Package output:  $(ZIP_PATH)"
	@echo "Manifest output: $(PLUGIN_UPDATE_XML)"

dist: $(ZIP_PATH)
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(ZIP_PATH)" || shasum -a 256 "$(ZIP_PATH)") | awk '{print $$1}' )"; \
	DOWNLOAD_URL="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(GITHUB_REF)/$(ZIP_NAME)"; \
	awk -v version="$(PLUGIN_VERSION)" -v url="$$DOWNLOAD_URL" -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PLUGIN_UPDATE_XML)" > "$(PLUGIN_UPDATE_XML).tmp" && mv "$(PLUGIN_UPDATE_XML).tmp" "$(PLUGIN_UPDATE_XML)"
	@echo "Updated $(PLUGIN_UPDATE_XML)"

$(ZIP_PATH):
	@mkdir -p "$(INSTALL_DIR)"
	@rm -f "$(ZIP_PATH)"
	@zip -qr -X "$(ZIP_PATH)" $(PLUGIN_FILES) -x "*.DS_Store" -x "*/.DS_Store"
	@echo "Built $(ZIP_PATH)"

clean:
	@rm -f "$(ZIP_PATH)"
