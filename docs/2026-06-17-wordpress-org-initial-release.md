# WordPress.org Initial Release - 2026-06-17

## 状态

Npcink AI Client Adapter 已完成 WordPress.org 首次发布。

- WordPress.org 插件页：https://wordpress.org/plugins/npcink-ai-client-adapter/
- SVN URL：https://plugins.svn.wordpress.org/npcink-ai-client-adapter
- 发布版本：`0.3.0`
- SVN revision：`3575131`
- SVN 提交用户：`muze233`
- SVN 提交时间：`2026-06-17 10:14:58 +0800`
- 本地 git 同步提交：`9f9b4f6 Prepare WordPress.org release assets`

本记录不保存 WordPress.org/SVN 密码或任何发布凭证。

## 命名和定位

最终采用的公开插件名：

```text
Npcink AI Client Adapter
```

该名称用于避免继续使用 `Magick` 字样，并和已发布的 Npcink 插件系列保持一致：

- `npcink-governance-core`
- `npcink-abilities-toolkit`
- `npcink-ai-client-adapter`

产品定位：

```text
OpenClaw-compatible channel for governed WordPress abilities.
```

Adapter 面向 OpenClaw 及同类 AI client，例如 Qclaw、WorkBuddy 这类 OpenClaw-like 软件。它保持薄适配层边界，只负责 AI client 接入、REST route、Abilities API 读能力路由、Governance Core proposal/preflight 路由，以及明确 allowlisted execution profile 的 post-Core 执行。

## 审核反馈处理

本轮发布前处理过的主要审核风险：

- 移除旧品牌或可能触发命名问题的 `Magick` 展示内容。
- 去掉 release 包公开面中的旧 scope/签名前缀/manifest kind：
  - `magick.*` scope 改为 `npcink.*`
  - `MAGICK-AI-ADAPTER-V1` 改为 `NPCINK-AI-CLIENT-ADAPTER-V1`
  - `magick.ai/wordpress-adapter-connection` 改为 `npcink.ai/wordpress-adapter-connection`
  - `mag_manifest_` 改为 `npcink_manifest_`
- 移除 admin handoff 中的本机开发 checkout 路径，改为 npm package 命令：
  - `@npcink/openclaw-adapter-cli@0.2.0`
- 将示例 proposal body 文件名从 `/tmp/magick-proposal.json` 改为 `/tmp/npcink-proposal.json`。
- 之前已移除 standalone approve/reject stub routes，避免 WordPress.org review 将其理解为不可用功能或 trialware：
  - `POST /proposals/{proposal_id}/approve`
  - `POST /proposals/{proposal_id}/reject`
- 将 plan ability terminology 从 allowlist/not allowed 口径改成 supported/unsupported 口径，避免 review 文案误读。

## 上架资源

上架图从 `sj/source/` 重新生成，导出到：

```text
sj/exports/wordpress-org/banner-1544x500.png
sj/exports/wordpress-org/banner-772x250.png
sj/exports/wordpress-org/icon-256x256.png
sj/exports/wordpress-org/icon-128x128.png
```

SVN 顶层 `assets/` 已发布相同文件：

```text
assets/banner-1544x500.png
assets/banner-772x250.png
assets/icon-256x256.png
assets/icon-128x128.png
```

图片内容已从旧 `Magick AI Adapter` 改为 Npcink 口径，主视觉表达为：

```text
AI Client -> Npcink Adapter -> Core / Abilities
```

## 验证记录

发布前通过的本地检查：

```bash
composer test:all
composer release:verify
composer package:release
```

`composer release:verify` 包含：

- PHP syntax checks
- static contract checks
- WordPress.org review guard
- Plugin Check release-surface scan

发布 zip：

```text
build/npcink-ai-client-adapter.zip
```

发布前额外检查过 release zip：

- 无 `.DS_Store`
- 无 `__MACOSX`
- 无 `Magick` / `magick` / `MAGICK` 公开 release 字符串
- 无旧 approve/reject disabled stub route 文案
- 无旧 allowlist/not allowed review-risk 文案

## SVN 发布步骤

本地 SVN 工作副本：

```text
/Users/muze/wporg-svn/npcink-ai-client-adapter
```

发布结构：

```text
trunk/
tags/0.3.0/
assets/
```

实际提交命令：

```bash
cd /Users/muze/wporg-svn/npcink-ai-client-adapter
/opt/homebrew/bin/svn commit --username muze233 -m "Initial release 0.3.0"
```

提交后同步确认：

```bash
/opt/homebrew/bin/svn update
/opt/homebrew/bin/svn ls https://plugins.svn.wordpress.org/npcink-ai-client-adapter/assets
/opt/homebrew/bin/svn ls https://plugins.svn.wordpress.org/npcink-ai-client-adapter/tags/0.3.0
/opt/homebrew/bin/svn ls https://plugins.svn.wordpress.org/npcink-ai-client-adapter/trunk
```

确认远端包含：

```text
assets/banner-1544x500.png
assets/banner-772x250.png
assets/icon-128x128.png
assets/icon-256x256.png
tags/0.3.0/
trunk/
```

## Git 同步

SVN 发布完成后，将本地源码和上架资源同步提交到 git：

```text
9f9b4f6 Prepare WordPress.org release assets
```

提交内容包括：

- Npcink scope、manifest kind、signature prefix 更新。
- admin local CLI handoff 改为 npm package 命令。
- packaged CLI 的签名前缀和 requested scopes 更新。
- WordPress.org banner/icon source 与导出图更新。
- 对应静态测试期望更新。

提交前确认：

```bash
composer test:all
composer release:verify
git diff --check
```

## 后续注意事项

- 发布凭证不要写入仓库、文档、commit message、shell history 片段或 issue。
- 之后每次上传 WordPress.org 前继续使用：

```bash
composer release:verify
composer package:release
```

- 如果 WordPress.org 再次发 review 邮件，按 `docs/wordpress-org-release-gate.md` 的流程处理：逐条提取 review 指出的文件和行，修完整 pattern class，并把可静态检查的问题加入本地 guard。
- Adapter 仍需保持薄层边界，不把 Core approval truth、Abilities definitions、workflow runtime、Cloud runtime、provider routing、prompt UX 放进本插件。
