# WordPress.org 0.3.2 发布归档

Date: 2026-06-21
Status: released

本记录归纳 0.3.2 代码 release 之后，为 WordPress.org 插件页正式更新所做的
界面文案、中文翻译、上架素材、依赖声明、截图和 SVN 发布工作。

本记录不保存 WordPress.org/SVN 密码、cookie、token 或任何发布凭证。

## 发布结果

- WordPress.org 插件页：
  `https://wordpress.org/plugins/npcink-ai-client-adapter/`
- WordPress.org SVN：
  `https://plugins.svn.wordpress.org/npcink-ai-client-adapter`
- 发布版本：`0.3.2`
- SVN revision：`3580629`
- SVN 提交信息：`Release 0.3.2`
- 本地 git 当前发布提交：
  `26ec7cc Refresh WordPress.org banner assets`

发布后远端确认：

```text
trunk/readme.txt:
Requires Plugins: npcink-abilities-toolkit, npcink-governance-core
Stable tag: 0.3.2

tags/0.3.2/npcink-ai-client-adapter.php:
Version: 0.3.2
Requires Plugins: npcink-abilities-toolkit, npcink-governance-core
```

## 本轮处理的用户反馈

### 插件命名和中文显示

插件公开名保持：

```text
Npcink AI Client Adapter
```

后台页面标题采用中文语境下更直接的产品名：

```text
客户端适配器
```

原因：

- WordPress.org、插件头、readme、slug 需要稳定使用英文品牌名；
- WordPress 后台中文界面中，`客户端适配器` 更适合终端用户理解；
- `Npcink AI 客户端适配器` 容易让插件列表和后台页面出现中英混排噪音；
- Adapter 仍然是薄 AI client channel layer，不是 Core、Abilities、Cloud 或工作流运行时。

插件列表中的 `Settings` 动作链接已翻译为 `设置`。

### Admin 页面文案

原文案：

```text
Connect OpenClaw to this WordPress site through the adapter REST interface.
```

调整为：

```text
Connect this WordPress site to OpenClaw or other local AI clients.
```

中文：

```text
将此 WordPress 站点连接到 OpenClaw 或其他本地 AI 客户端。
```

原因：

- 产品真实方向是让 WordPress 站点和 OpenClaw-like 本地 AI 客户端建立连接；
- 不应把用户理解引向“OpenClaw 被连接到 WordPress”这一单向技术表达；
- `REST interface` 属于实现细节，不适合放在普通 admin 页面主说明里。

### 移除开发说明

后台页面底部的英文开发提示被移除：

```text
Developer route details and local testing notes are documented in
docs/admin-developer-reference.md.
```

原因：

- 这是开发者文档导航，不是插件使用界面的一部分；
- WordPress.org 用户进入插件设置页时，需要的是连接状态、配对方式、设备管理；
- 开发路由和本地测试说明应保留在 repo 文档中，不应干扰产品界面。

### UI 对齐和设备表格

处理过的后台 UI 问题：

- 顶部 summary row 垂直居中；
- `Authorized devices` 改为 `Active devices`；
- `Secure key-pair connection` 改为 `Secure key pairing`；
- 设备列表区分有效设备和已撤销设备；
- `Device ID`、状态 badge、撤销按钮的显示更清晰；
- 低频技术内容从默认页面中收敛。

中文翻译和 POT/PO/MO 文件已同步。

## WordPress.org 上架页内容

为 WordPress.org 插件页准备并同步了：

- `readme.txt`
- `sj/listing-copy-en.md`
- `sj/listing-copy-zh.md`
- FAQ
- Screenshots 描述
- 安装步骤
- 依赖声明

核心定位：

```text
Npcink AI Client Adapter connects local AI clients, such as OpenClaw-compatible
tools, to a WordPress site through a focused REST adapter.
```

中文定位：

```text
Npcink AI Client Adapter 通过一个聚焦的 REST 适配器，将 OpenClaw 兼容工具等本地
AI 客户端连接到 WordPress 站点。
```

## 依赖链路

发布前明确 Adapter 的安装依赖：

```text
Requires Plugins: npcink-abilities-toolkit, npcink-governance-core
```

原因：

- `npcink-abilities-toolkit` 提供 WordPress Abilities API 能力定义和回调；
- `npcink-governance-core` 提供 proposal、approval、preflight、audit truth；
- Adapter 本身只做 AI client 通道适配；
- WordPress.org 用户需要可安装、可启用、可获取的完整依赖链路。

本轮已确认 Toolkit/Core 是 WordPress.org 上架插件，并把 Core 加入 Adapter 的
插件头、readme 和本地静态测试。

## 上架素材和截图

源图位置：

```text
sj/source/banner-source.png
sj/source/icon-source.png
```

WordPress.org 导出资源：

```text
sj/exports/wordpress-org/banner-1544x500.png
sj/exports/wordpress-org/banner-772x250.png
sj/exports/wordpress-org/icon-256x256.png
sj/exports/wordpress-org/icon-128x128.png
sj/exports/wordpress-org/screenshot-1.png
sj/exports/wordpress-org/screenshot-2.png
sj/exports/wordpress-org/screenshot-3.png
```

远端 SVN `assets/` 已确认包含：

```text
banner-1544x500.png
banner-772x250.png
icon-128x128.png
icon-256x256.png
screenshot-1.png
screenshot-2.png
screenshot-3.png
```

截图通过本地 WordPress 开发站点生成，过程中使用了临时管理员、临时站点 URL、
临时 demo key records 和临时 PHP server；发布前已清理：

- 删除临时管理员；
- 删除临时 `npcink_openclaw_adapter_client_keys` option；
- 恢复 `home` / `siteurl`；
- 停止临时 PHP server；
- 恢复本地插件启用状态。

## Banner 修复

发布前发现两张导出 banner 有问题：

```text
sj/exports/wordpress-org/banner-772x250.png
sj/exports/wordpress-org/banner-1544x500.png
```

问题表现：

- 标题后面出现浅色矩形块；
- 该色块遮挡了右侧插画中的显示器/图标区域；
- 源图 `sj/source/banner-source.png` 没有此问题。

根因：

- 源图标题中生成的是 `Npcink Ai Client Adapter`；
- 之前导出时为了把 `Ai` 修为 `AI`，使用了“覆盖旧标题区域 + 重写标题”的方式；
- 覆盖区域过宽，导致浅色遮罩压住插画。

修复方式：

- 不再整块覆盖标题区域；
- 以 `banner-source.png` 为底，只做 `Ai` 到 `AI` 的局部像素修正；
- 重新导出 `1544x500` 和 `772x250` 两个 WordPress.org 尺寸。

对应 git 提交：

```text
26ec7cc Refresh WordPress.org banner assets
```

## 本地验证

SVN 发布前完成：

```bash
composer test:all
composer plugin-check:release
composer package:release
composer smoke:package-install
```

结果：

- 静态 contract 检查通过；
- Plugin Check release scan 无错误；
- release zip 重新生成；
- package install smoke 通过；
- release zip 中确认：
  - `Stable tag: 0.3.2`
  - `Version: 0.3.2`
  - `Requires Plugins: npcink-abilities-toolkit, npcink-governance-core`

本地 release zip：

```text
build/npcink-ai-client-adapter.zip
```

## SVN 发布步骤

使用临时 SVN 工作副本：

```text
/tmp/npcink-ai-client-adapter-wporg.*
```

流程：

1. 检出：

```bash
/opt/homebrew/bin/svn checkout \
  https://plugins.svn.wordpress.org/npcink-ai-client-adapter \
  /tmp/npcink-ai-client-adapter-wporg.*
```

2. 用 `build/npcink-ai-client-adapter/` 覆盖 SVN `trunk/`。
3. 新建并填充 `tags/0.3.2/`。
4. 用 `sj/exports/wordpress-org/` 更新 SVN 顶层 `assets/`。
5. `svn add --force` 新文件，并删除 trunk 中遗留的旧入口：

```text
trunk/npcink-openclaw-adapter.php
```

6. 提交：

```bash
/opt/homebrew/bin/svn commit --non-interactive -m "Release 0.3.2"
```

提交成功：

```text
Committed revision 3580629.
```

## 相关 git 提交

本轮 WordPress.org 发布相关提交：

```text
e57e15a Polish adapter connection admin surface
42cd493 Prepare WordPress.org listing assets
438a9ba Declare governance core dependency
26ec7cc Refresh WordPress.org banner assets
```

这些提交覆盖：

- admin surface 文案、翻译、对齐、设备列表优化；
- WordPress.org listing copy、FAQ、screenshots 描述；
- banner/icon/screenshot 导出资源；
- Toolkit/Core 依赖声明；
- banner 遮挡问题修复。

## 后续注意事项

- WordPress.org 插件页、banner、截图和 readme 可能有缓存延迟，SVN 已发布不代表页面立即刷新。
- 后续每次发布前继续跑：

```bash
composer test:all
composer plugin-check:release
composer package:release
composer smoke:package-install
```

- 如果继续更新 WordPress.org assets，优先从 `sj/source/` 生成，不要用大面积遮罩修字。
- 如果源图文字生成错误，优先回到源图重做，或只做最小局部修正。
- Adapter 仍需保持薄层边界：
  - 不拥有 ability definitions；
  - 不拥有 Core proposal/approval/audit truth；
  - 不新增 generic final write executor；
  - 不拥有 workflow runtime；
  - 不拥有 provider/model/prompt execution；
  - 不拥有 Cloud settings、Cloud routes 或 Cloud execution truth。
