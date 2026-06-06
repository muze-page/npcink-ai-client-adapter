# Bilingual Publishing Notes

## English

Npcink OpenClaw Adapter uses the `npcink-openclaw-adapter` text domain in runtime PHP
strings, ships a generated `languages/npcink-openclaw-adapter.pot` template,
and includes a bundled Simplified Chinese translation for local/private installs.

For WordPress.org publishing, use `listing-copy-en.md` as the primary plugin
directory copy. Use `listing-copy-zh.md` for Chinese launch posts,
documentation, marketplace-adjacent pages, or as the source for future Chinese
translation work.

The current repository state intentionally stops at one bundled `zh_CN`
translation. Do not add eight bundled `.po`/`.mo` language files until the plugin
display name, slug, text domain, REST namespace, and CLI command naming are
frozen.

Recommended release flow:

1. Keep source code strings in English.
2. Keep all runtime strings wrapped with the `npcink-openclaw-adapter` text domain.
3. Run `composer i18n:pot` before release to refresh `languages/npcink-openclaw-adapter.pot`.
4. Keep `languages/npcink-openclaw-adapter-zh_CN.po` and `.mo` in sync for
   Chinese local/private installs.
5. Keep generated translation templates and future runtime translations under
   `languages/`.
6. Keep `sj/` for listing copy, image prompts, and release artwork only.

## Chinese

Npcink OpenClaw Adapter 的 PHP 运行时字符串使用 `npcink-openclaw-adapter` text domain，并且
已经提供 `languages/npcink-openclaw-adapter.pot` 模板和内置简体中文翻译，用于接入
WordPress 翻译流程以及本地/私有安装场景。

发布到 WordPress.org 时，建议使用 `listing-copy-en.md` 作为插件目录主文案。
`listing-copy-zh.md` 用于中文发布文章、中文文档、国内渠道页面，或作为未来中文
翻译工作的源稿。

当前仓库刻意只内置一份 `zh_CN` 翻译，不急着加入八国语言 `.po`/`.mo`。等插件展示名、
slug、text domain、REST namespace 和 CLI command 的命名都冻结后，再投入更多语言成品翻译。

推荐发布流程：

1. 源代码字符串继续保持英文。
2. 所有运行时字符串继续使用 `npcink-openclaw-adapter` text domain。
3. 发布前运行 `composer i18n:pot` 刷新 `languages/npcink-openclaw-adapter.pot`。
4. 为中文本地/私有安装维护 `languages/npcink-openclaw-adapter-zh_CN.po` 和 `.mo`。
5. 生成的翻译模板和未来运行时翻译文件放在 `languages/`。
6. `sj/` 只用于上架文案、图片提示词和发布素材。
