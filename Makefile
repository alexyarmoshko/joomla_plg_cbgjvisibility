# Reproducible packaging for plg_system_cbgjvisibility: the ZIP bytes depend only on the
# packaged files and one timestamp, never on the machine that built it, so a release can be
# rebuilt from its tag and still hash to the sha256 its update descriptor claims.
# Derived from a shared Makefile template for Joomla extensions; the workflow and its
# rationale are in README.md, section "Build".

SHELL := /bin/sh

# The install name: plg_<type>_<name>, mod_<name>, com_<name>, tpl_<name>, lib_<name>.
EXT_NAME := plg_system_cbgjvisibility
MANIFEST := cbgjvisibility.xml
GITHUB_OWNER := alexyarmoshko
GITHUB_REPO := joomla_plg_cbgjvisibility

# One explicit list of what ships. Never a directory.
PACKAGE_FILES := \
	LICENSE \
	$(MANIFEST) \
	services/provider.php \
	src/Extension/Cbgjvisibility.php \
	src/Field/SanitizationTestField.php \
	language/en-GB/plg_system_cbgjvisibility.ini \
	language/en-GB/plg_system_cbgjvisibility.sys.ini \
	media/js/sanitization-test.js

JZIP ?= tools/jzip.php
ZIP_LEVEL ?= 9
INSTALL_DIR ?= installation
BUILD_DIR ?= build

RELEASE_NOTES ?= docs/RELEASE.md
UPDATE_TEMPLATE := $(EXT_NAME).update.xml
SHA256_PLACEHOLDER := 0000000000000000000000000000000000000000000000000000000000000000

# `override` is load-bearing. The tag, the ZIP name and the download URL all derive
# from this, and deriving it from the manifest instead of typing it is the whole point
# of `release`. Without `override`, `make release VERSION=x` tags a version the manifest
# never declared - and refusing to package it afterwards does not remove the tag.
override VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(MANIFEST))
ZIP_NAME := $(EXT_NAME)-v$(subst .,-,$(VERSION)).zip
RELEASE_STAGE := $(BUILD_DIR)/release
DEV_STAGE := $(BUILD_DIR)/dev
RELEASE_ZIP := $(INSTALL_DIR)/release/$(ZIP_NAME)
DEV_ZIP := $(INSTALL_DIR)/dev/$(ZIP_NAME)
UPDATE_ARTIFACT := $(INSTALL_DIR)/release/$(UPDATE_TEMPLATE)
DOWNLOAD_URL := https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(VERSION)/$(ZIP_NAME)

# No Composer here: the plugin has no dependencies and no automated harness. These are the
# repository's own gates, and `release` runs them before it tags - so they are `:=`, not `?=`:
# an environment variable of the same name must not be able to replace them silently. `:=`
# expands immediately, so they must stay below the variables they reference.
DEPS_CMD := @echo "No dependencies to install."
TEST_CMD := echo "No automated test harness in this repository - release verification is manual."
# $(JZIP) is linted although it does not ship: it is the packager, and a syntax error in it
# would otherwise surface only after `release` has created the tag.
LINT_CMD := set -e; \
	for f in $(filter %.php,$(PACKAGE_FILES)) $(JZIP); do out="$$(php -l "$$f" 2>&1)" || { printf '%s\n' "$$out"; exit 1; }; done; \
	for f in $(MANIFEST) $(UPDATE_TEMPLATE); do \
		php -r 'libxml_use_internal_errors(true); if (simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "FAIL: malformed XML in $$argv[1]\n"); exit(1); }' "$$f" || exit 1; \
	done; \
	echo "lint: ok"

RELEASE_LOCKED := ZIP_LEVEL JZIP PACKAGE_FILES EXT_NAME MANIFEST GITHUB_OWNER GITHUB_REPO RELEASE_NOTES \
	ZIP_NAME DOWNLOAD_URL UPDATE_TEMPLATE SHA256_PLACEHOLDER
release_overrides = $(strip $(foreach v,$(RELEASE_LOCKED),$(if $(filter command environment,$(firstword $(origin $(v)))),$(v))))

sha256 = $$( { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$(1)"; else shasum -a 256 "$(1)"; fi; } | awk '{print $$1}' | grep -Ex '[0-9a-f]{64}' )

# Package staging directory $(2) into $(1), stamping every entry with epoch $(3).
define package
	@set -e; \
	for f in $(PACKAGE_FILES); do \
		if [ ! -f "$(2)/$$f" ]; then \
			echo "FAIL: $$f is in PACKAGE_FILES but not in $(2)/ - the staged tree is incomplete"; \
			exit 1; \
		fi; \
	done; \
	mkdir -p "$(dir $(1))"; \
	archive="$$(cd "$(dir $(1))" && pwd)/$$(basename "$(1)").part"; stamp="$(3)"; \
	rm -f "$(1).part"; \
	if [ -f "$(JZIP)" ]; then \
		php "$(JZIP)" --level=$(ZIP_LEVEL) "$$archive" "$$stamp" "$(2)" $(PACKAGE_FILES) >/dev/null; \
		how="jzip level $(ZIP_LEVEL)"; \
	else \
		echo "WARNING: jzip not found at $(JZIP) - falling back to zip." >&2; \
		echo "WARNING: the package is NOT reproducible - zip stores this machine's mtimes and host metadata." >&2; \
		( cd "$(2)" && zip -q -X -$(ZIP_LEVEL) "$$archive" $(PACKAGE_FILES) ); \
		how="zip fallback"; \
	fi; \
	mv "$(1).part" "$(1)"; \
	sum="$(call sha256,$(1))"; \
	echo "$(1): $(words $(PACKAGE_FILES)) entries, $$(wc -c < "$(1)") bytes, $$how, sha256 $$sum"
endef

.PHONY: info deps test lint release dist_release dist_dev update_manifest clean

info:
	@echo "Extension:       $(EXT_NAME)"
	@echo "Version:         $(VERSION)"
	@echo "Source:          $(PACKAGE_FILES)"
	@echo "Release package: $(RELEASE_ZIP)"
	@echo "Dev package:     $(DEV_ZIP)"
	@echo "Update template: $(UPDATE_TEMPLATE)"
	@echo "Update artifact: $(UPDATE_ARTIFACT)"
	@echo "Download URL:    $(DOWNLOAD_URL)"
	@echo "Packager:        $(JZIP) (level $(ZIP_LEVEL))"

deps:
	$(DEPS_CMD)

test:
	$(TEST_CMD)

lint:
	$(LINT_CMD)

release:
	@# The locked variables decide the tag's notes check and, later, the published bytes;
	@# refuse them here too, so an override cannot get as far as creating the tag.
	@if [ -n "$(release_overrides)" ]; then \
		echo "FAIL: $(release_overrides) set from the command line or environment - unset them before tagging"; \
		exit 1; \
	fi
	@set -e; \
	dirty="$$(git status --porcelain --untracked-files=normal)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - commit or stash before tagging"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: tag $(VERSION) already exists - bump <version> in $(MANIFEST)"; \
		exit 1; \
	fi
	@if ! awk -v v="$(VERSION)" '/^## /{ if ($$2==v) found=1 } END{ exit !found }' "$(RELEASE_NOTES)"; then \
		echo "FAIL: $(RELEASE_NOTES) has no '## $(VERSION)' heading - write the release notes before tagging"; \
		exit 1; \
	fi
	@# update_manifest checks these too, but only after the tag exists; a template left at the
	@# old version would then need the tag moved or deleted to fix.
	@if [ "$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(UPDATE_TEMPLATE)")" != "$(VERSION)" ]; then \
		echo "FAIL: $(UPDATE_TEMPLATE) declares a version other than $(VERSION) - bump it before tagging"; \
		exit 1; \
	fi
	@if [ "$$(grep -oF "<sha256>$(SHA256_PLACEHOLDER)</sha256>" "$(UPDATE_TEMPLATE)" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_TEMPLATE) is not a template - <sha256> must be the $(SHA256_PLACEHOLDER) placeholder"; \
		exit 1; \
	fi
	$(MAKE) test
	$(MAKE) lint
	@# -a -m: tag.gpgsign=true makes a bare `git tag` open an editor for the message, which
	@# aborts when make runs non-interactively. Signing itself still follows git config.
	git tag -a -m "Release $(VERSION)" "$(VERSION)"
	@echo "Tagged $(VERSION) - next: make dist_release"

dist_release:
	@if ! git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: no tag $(VERSION) - run 'make release' first"; \
		exit 1; \
	fi
	@# Only the payload files come from the tag. PACKAGE_FILES, the packager, the
	@# compression level and the descriptor template are all read from the checkout,
	@# so the archive is reproducible from the tag only when the checkout IS the tag.
	@set -e; \
	dirty="$$(git status --porcelain --untracked-files=normal)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - a release must be built from a clean checkout of $(VERSION)"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi; \
	head="$$(git rev-parse HEAD)"; \
	tagged="$$(git rev-parse "$(VERSION)^{commit}")"; \
	if [ "$$head" != "$$tagged" ]; then \
		echo "FAIL: HEAD is not tag $(VERSION)"; \
		echo "      HEAD $$head"; \
		echo "      tag  $$tagged"; \
		echo "      building here would use this checkout's build inputs against the tag's payload,"; \
		echo "      producing bytes a clean checkout of $(VERSION) cannot reproduce. Check the tag out."; \
		exit 1; \
	fi
	@# Same reasoning for the variables that decide the published bytes or the descriptor. `?=`
	@# lets an exported environment variable through, and `make -e` lets one override `:=`, so
	@# environment origins are refused as well as the command line.
	@if [ -n "$(release_overrides)" ]; then \
		echo "FAIL: $(release_overrides) set from the command line or environment."; \
		echo "      These decide the published bytes and descriptor and must come from the tagged"; \
		echo "      Makefile. Unset them, or change them in the repository instead."; \
		exit 1; \
	fi
	@# The package macro degrades to `zip` when the packager is missing. Tolerable for a
	@# dev package, never for a published one: it would produce a non-reproducible archive
	@# and then stamp a descriptor claiming its checksum.
	@if [ ! -f "$(JZIP)" ]; then \
		echo "FAIL: $(JZIP) is missing - a release package must come from the deterministic"; \
		echo "      packager, never the zip fallback. Restore it before building."; \
		exit 1; \
	fi
	@rm -rf "$(RELEASE_STAGE)"
	@mkdir -p "$(RELEASE_STAGE)"
	@git -c core.autocrlf=false archive "$(VERSION)" | tar -x -C "$(RELEASE_STAGE)"
	@set -e; \
	exported="$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(RELEASE_STAGE)/$(MANIFEST)")"; \
	if [ "$$exported" != "$(VERSION)" ]; then \
		echo "FAIL: tag $(VERSION) exports a manifest declaring $$exported"; \
		echo "      the ZIP name, download URL and checksum all come from the WORKING TREE manifest,"; \
		echo "      so packaging this would publish one version under another's name - move the tag"; \
		echo "      or fix <version> before rebuilding."; \
		exit 1; \
	fi
	$(call package,$(RELEASE_ZIP),$(RELEASE_STAGE),$$(git log -1 --format=%ct "$(VERSION)"))
	@rm -rf "$(RELEASE_STAGE)"
	$(MAKE) update_manifest

dist_dev:
	@rm -rf "$(DEV_STAGE)"
	@mkdir -p "$(DEV_STAGE)"
	@set -e; \
	for f in $(PACKAGE_FILES); do \
		mkdir -p "$(DEV_STAGE)/$$(dirname "$$f")"; \
		cp "$$f" "$(DEV_STAGE)/$$f"; \
	done
	@sed '/<updateservers>/,/<\/updateservers>/d' "$(DEV_STAGE)/$(MANIFEST)" > "$(DEV_STAGE)/manifest.tmp" \
		&& mv "$(DEV_STAGE)/manifest.tmp" "$(DEV_STAGE)/$(MANIFEST)"
	@# grep exits 2 on an unreadable file, which an `if` cannot tell from "no match" -
	@# an assertion that silently passes when it could not check is worse than none.
	@set -e; \
	rc=0; grep -q "updateservers" "$(DEV_STAGE)/$(MANIFEST)" || rc=$$?; \
	if [ "$$rc" -eq 0 ]; then \
		echo "FAIL: <updateservers> survived in the dev manifest"; \
		exit 1; \
	elif [ "$$rc" -gt 1 ]; then \
		echo "FAIL: cannot read $(DEV_STAGE)/$(MANIFEST) to confirm <updateservers> was stripped"; \
		exit 1; \
	fi
	@# The sed range deletes to end of file if <updateservers> ever sits on one line.
	@php -r 'libxml_use_internal_errors(true); if (simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "FAIL: stripping <updateservers> left a malformed $$argv[1]\n"); exit(1); }' "$(DEV_STAGE)/$(MANIFEST)"
	$(call package,$(DEV_ZIP),$(DEV_STAGE),$$(git log -1 --format=%ct))
	@rm -rf "$(DEV_STAGE)"

update_manifest:
	@set -e; \
	if [ "$$(grep -oF "<sha256>$(SHA256_PLACEHOLDER)</sha256>" "$(UPDATE_TEMPLATE)" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_TEMPLATE) is not a template - <sha256> must be the $(SHA256_PLACEHOLDER) placeholder"; \
		exit 1; \
	fi; \
	SHA256="$(call sha256,$(RELEASE_ZIP))"; \
	mkdir -p "$(dir $(UPDATE_ARTIFACT))"; \
	awk -v url="$(DOWNLOAD_URL)" -v sha="$$SHA256" '{ \
		if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(UPDATE_TEMPLATE)" > "$(UPDATE_ARTIFACT).part"; \
	if [ "$$(grep -oF "<sha256>$$SHA256</sha256>" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) does not carry exactly one <sha256> for $(RELEASE_ZIP)"; \
		exit 1; \
	fi; \
	if [ "$$(grep -oF '<downloadurl type="full" format="zip">$(DOWNLOAD_URL)</downloadurl>' "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) does not carry exactly one <downloadurl> for $(VERSION)"; \
		exit 1; \
	fi; \
	if [ "$$(grep -oF "<sha256>" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ] \
		|| [ "$$(grep -oF "<downloadurl" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) carries a second <sha256> or <downloadurl> this recipe did not write"; \
		exit 1; \
	fi; \
	if [ "$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(UPDATE_ARTIFACT).part")" != "$(VERSION)" ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) declares a version other than $(VERSION)"; \
		exit 1; \
	fi; \
	mv "$(UPDATE_ARTIFACT).part" "$(UPDATE_ARTIFACT)"
	@echo "Wrote $(UPDATE_ARTIFACT)"

clean:
	@rm -rf "$(BUILD_DIR)" "$(INSTALL_DIR)/release" "$(INSTALL_DIR)/dev"
