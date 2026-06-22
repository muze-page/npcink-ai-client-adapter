# WordPress.org zh_CN PTE Request Draft

Post target:

https://make.wordpress.org/polyglots/

Suggested tags:

`#editor-requests #zh_CN #plugins`

Suggested title:

PTE Request for Npcink AI Client Adapter

Post body:

```text
Hello Polyglots team,

I am the plugin author for Npcink AI Client Adapter:

https://wordpress.org/plugins/npcink-ai-client-adapter/

I would like to request Project Translation Editor access for Chinese (China),
locale zh_CN, for this plugin.

Plugin translation project:

https://translate.wordpress.org/projects/wp-plugins/npcink-ai-client-adapter/

Requested PTE:

@muze233

Reason:

The plugin already ships a reviewed Simplified Chinese translation in its
release package, and the WordPress.org translation project currently shows
Chinese (China) as 0% current while translated strings are waiting for review.
I would like to maintain the zh_CN translations directly for future releases so
Chinese users can see the localized plugin listing and receive approved
language packs through WordPress.org.

Prepared translation files:

- Stable:
  sj/wporg/npcink-ai-client-adapter-stable-zh_CN-glotpress-import.po
- Development:
  sj/wporg/npcink-ai-client-adapter-dev-zh_CN-glotpress-import.po

Both files were generated from the current WordPress.org GlotPress exports and
validated locally with msgfmt.

Thank you.
```

## Local Verification

Current WordPress.org status checked on 2026-06-21:

```text
Chinese (China): 0% current, 198 waiting/fuzzy
Stable export: 0 translated messages, 296 untranslated messages
Stable Readme export: 0 translated messages, 54 untranslated messages
```

Submission status on 2026-06-21:

```text
Stable runtime imported: 296 waiting strings
Development runtime imported: 296 waiting strings
Stable Readme imported: 54 waiting strings
Project overview after import: Chinese (China) 0% current, 646 waiting/fuzzy
PTE request submitted for review: https://make.wordpress.org/polyglots/?p=67851
```

The PTE request post is pending review. The current account could not edit the
pending post title after submission; WordPress.org redirected the edit URL back
to the public profile page.

Generated import files:

```bash
msgmerge --no-fuzzy-matching --quiet \
  languages/npcink-ai-client-adapter-zh_CN.po \
  /tmp/npcink-ai-client-adapter-wporg-zh_CN.po \
  -o sj/wporg/npcink-ai-client-adapter-stable-zh_CN-glotpress-import.po

msgmerge --no-fuzzy-matching --quiet \
  languages/npcink-ai-client-adapter-zh_CN.po \
  /tmp/npcink-ai-client-adapter-wporg-dev-zh_CN.po \
  -o sj/wporg/npcink-ai-client-adapter-dev-zh_CN-glotpress-import.po
```

Validation:

```text
296 translated messages.
```

Import URL after login/PTE approval:

```text
https://translate.wordpress.org/projects/wp-plugins/npcink-ai-client-adapter/stable/zh-cn/default/import-translations/
https://translate.wordpress.org/projects/wp-plugins/npcink-ai-client-adapter/dev/zh-cn/default/import-translations/
```
