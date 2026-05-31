# Bilingual Publishing Notes

## English

Magick AI Adapter uses the `magick-ai-adapter` text domain in runtime PHP
strings, so it is prepared for WordPress translation workflows.

For WordPress.org publishing, use `listing-copy-en.md` as the primary plugin
directory copy. Use `listing-copy-zh.md` for Chinese launch posts,
documentation, marketplace-adjacent pages, or as the source for future Chinese
translation work.

If bundled translation files are added later, use a standard `languages/`
directory and keep generated `.pot`, `.po`, and `.mo` files separate from this
`sj` publishing workspace.

Recommended release flow:

1. Keep source code strings in English.
2. Keep all runtime strings wrapped with the `magick-ai-adapter` text domain.
3. Generate a POT file before release if bundled translations are needed.
4. Translate Chinese strings through the WordPress.org translation workflow or a
   project-owned `zh_CN` translation file.
5. Keep `sj/` for listing copy, image prompts, and release artwork only.

## Chinese

Magick AI Adapter 的 PHP 运行时字符串使用 `magick-ai-adapter` text domain，因此
已经具备接入 WordPress 翻译流程的基础。

发布到 WordPress.org 时，建议使用 `listing-copy-en.md` 作为插件目录主文案。
`listing-copy-zh.md` 用于中文发布文章、中文文档、国内渠道页面，或作为未来中文
翻译工作的源稿。

如果后续需要随插件包内置翻译文件，建议使用标准 `languages/` 目录，并将生成的
`.pot`、`.po`、`.mo` 文件和当前 `sj` 发布素材工作区分开。

推荐发布流程：

1. 源代码字符串继续保持英文。
2. 所有运行时字符串继续使用 `magick-ai-adapter` text domain。
3. 如果需要内置翻译，在发布前生成 POT 文件。
4. 中文翻译可以走 WordPress.org 翻译流程，也可以维护项目自己的 `zh_CN` 翻译文件。
5. `sj/` 只用于上架文案、图片提示词和发布素材。
