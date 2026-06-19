# 2026-06-10 Release Notes Draft

## Npcink AI Client Adapter

This release renames the public WordPress plugin identity from Npcink OpenClaw
Adapter to Npcink AI Client Adapter.

### Highlights

- Public plugin name: `Npcink AI Client Adapter`.
- Main plugin file: `npcink-ai-client-adapter.php`.
- Text domain: `npcink-ai-client-adapter`.
- Release package root: `npcink-ai-client-adapter/`.
- Existing Adapter REST clients remain compatible with the stable namespace:
  `npcink-openclaw-adapter/v1`.
- The legacy `npcink-openclaw-adapter.php` file remains in the package as a
  no-header bootstrap for stale active plugin paths during upgrade.

### Governance Boundary

This release does not change the Adapter's product boundary. The Adapter remains
a thin channel layer:

- read ability execution is routed through WordPress Abilities API;
- proposal and commit-preflight requests are routed to Npcink Governance Core;
- final writes are available only through explicit post-Core execution profiles;
- `core_proxy_execute=false` and `commit_execution=false` remain expected
  boundary controls.

### Compatibility Notes

Installations should use the new plugin directory:

```text
wp-content/plugins/npcink-ai-client-adapter/
```

After upgrading from older local symlink installs, remove stale active plugin
entries or duplicate plugin directories for:

```text
npcink-openclaw-adapter/
```

The legacy PHP file is intentionally not a second WordPress plugin header. It is
only a compatibility bootstrap.

### Verification Completed

Adapter verification completed on the local release surface:

```bash
composer test:all
composer validate --no-check-publish
composer release:verify
composer package:release
composer smoke:wp
```

The release zip was generated at:

```text
build/npcink-ai-client-adapter.zip
```

Package inspection confirmed both entry files are present:

```text
npcink-ai-client-adapter/npcink-ai-client-adapter.php
npcink-ai-client-adapter/npcink-openclaw-adapter.php
```

Local WordPress smoke confirmed:

- `npcink-ai-client-adapter` is the active Adapter plugin slug;
- stale `npcink-openclaw-adapter` active plugin entries were removed;
- authenticated Adapter status returns `ready`;
- `/npcink-openclaw-adapter/v1` routes still work after the rename.

### Package Install Acceptance

The release zip was installed on the local target WordPress site as an extracted
package directory, not as the development symlink. Acceptance confirmed:

- WordPress loaded `npcink-ai-client-adapter` from the packaged plugin root;
- the package contained both the renamed main file and legacy bootstrap;
- Adapter CLI status returned `ready` while the package was installed;
- the full WordPress smoke flow completed successfully against the packaged
  install;
- the local development symlink was restored after package acceptance.

### Operator Checklist

1. Install or update using `build/npcink-ai-client-adapter.zip`.
2. Confirm only `npcink-ai-client-adapter` is active in WordPress.
3. Confirm there is no duplicate active entry for `npcink-openclaw-adapter`.
4. Run Adapter CLI status against the target profile.
5. Run one governed proposal smoke before production use.
