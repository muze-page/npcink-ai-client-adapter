# Approve And Execute Closeout - 2026-06-17

## 背景

本次收口从 `npcink-governance-core` 的批准策略改造延伸到
`npcink-ai-client-adapter`。目标是在 Adapter 侧完成
`approve-and-execute` 的最终执行安全收口：Adapter 可以继续作为
OpenClaw 面向本地客户端的一次性执行入口，但最终 WordPress 写入必须严格绑定
Core approval 和 Core commit-preflight 返回的执行交接。

Adapter 仍保持薄通道边界：

- 不拥有 Core proposal、approval、audit truth；
- 不成为 Abilities Toolkit ability registry；
- 不引入 workflow runtime、MCP runtime、Agent Gateway catalog；
- 不成为通用 final write executor；
- 只在明确 execution profile、Core approval、Core commit-preflight 和显式执行
  handoff 全部满足时执行最终写入。

## 已完成内容

PR:

```text
https://github.com/muze-page/npcink-ai-client-adapter/pull/2
```

PR 状态：

```text
OPEN
Ready for review
mergeStateStatus=CLEAN
```

本次 Adapter 分支：

```text
codex/approve-and-execute
```

本地提交链：

```text
976b1f1 Tighten adapter execution handoff validation
6362d97 Bind Core preflight to signed local clients
```

远端 PR 分支通过 GitHub API 上传，远端 commit SHA 与本地不同，但最终 tree 与
本地一致。远端 PR head:

```text
9032d765586bd5cae0633d2d43bd601aef2b41e1
```

## 行为收口

Adapter 最终执行前现在要求 Core commit-preflight 返回合法
`execution_handoff`，并失败关闭校验以下字段：

- `executor=adapter_after_core_preflight`
- `execution_surface=wp_abilities_rest`
- `core_proxy_execute=false`
- `commit_execution=false`
- `proposal_id` 与执行请求一致
- `correlation_id` 与 commit-preflight 一致
- `ability_id` 匹配 proposal ability 或 `write_actions[]` target ability
- `approved_input_hash` 匹配当前 proposal input
- `policy_version=core-preflight-v1`
- `expires_at` 存在且未过期
- 可选 `site_url`、`home_url`、`blog_id` 与当前站点一致
- 可选 `signed_client_fingerprint` / `client_key_fingerprint` 与当前签名本地客户端一致

缓存的 Adapter commit-preflight handoff 与直接执行路径复用同一套绑定校验，
避免诊断预检和最终执行路径产生不一致。

## Signed Client Fingerprint 收口

Adapter 会在可信 Core app-token 请求中转发当前签名本地客户端 fingerprint：

```text
x-npcink-adapter-signed-client-fingerprint
x-npcink-adapter-client-key-fingerprint
```

当 Core 在 preflight 或 sensitive read authorization context 中返回
`signed_client_fingerprint` 或兼容别名 `client_key_fingerprint` 时，Adapter 会
与当前签名客户端进行 fail-closed 校验。

这部分保持 Core/Adapter 交叉契约：Core 负责签发 authorization/preflight context，
Adapter 负责验证 context 是否绑定当前请求客户端。

## 文档与测试

同步更新：

- `includes/Rest/Controller.php`
- `docs/openclaw-adapter-contract.md`
- `docs/architecture/adapter-boundary.md`
- `docs/2026-06-17-adapter-release-acceptance.md`
- `tests/run.php`
- `languages/npcink-ai-client-adapter.pot`
- `languages/npcink-ai-client-adapter-zh_CN.po`
- `languages/npcink-ai-client-adapter-zh_CN.mo`

静态契约增加了对新 helper、错误码和 fingerprint/expiry 绑定点的覆盖。

## 验证结果

已通过：

```bash
composer test:all
```

已通过：

```bash
WP_CLI=/tmp/wp-cli.phar \
WP_CLI_MYSQL_SOCKET="$HOME/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock" \
composer smoke:wp
```

说明：普通 `composer smoke:wp` 曾失败一次，原因是系统 `wp` binary 没有注入
Local MySQL socket，报本地数据库连接失败。使用仓库支持的 `WP_CLI` phar 与
`WP_CLI_MYSQL_SOCKET` 参数后，WordPress smoke 通过。

Smoke 覆盖了多条 `approve-and-execute` 真写入路径、duplicate execution 拒绝、
commit-preflight handoff 缓存、Core proposal 状态读取、Core app token proposal
创建与 commit-preflight、provider log correlation 等路径。

## GitHub 推送说明

`git push` 通过 HTTPS 多次失败：

```text
Error in the HTTP2 framing layer
Failed to connect to github.com port 443
```

SSH 到 GitHub 可认证，但当前 key 对目标仓库不可用：

```text
Repository not found.
```

因此最终使用 GitHub API 上传等价提交链并创建 PR。由于 GitHub API 重建 commit 时
会规范化本地作者邮箱格式，远端 commit SHA 与本地 commit SHA 不一致，但 tree
hash 已确认一致。

随后执行了本地同步：

```bash
git reset --keep origin/codex/approve-and-execute
```

同步前核对结果：

```text
local tree  = d769746c1b0b32f4da940b20a3f067fbe9a6c2d0
remote tree = d769746c1b0b32f4da940b20a3f067fbe9a6c2d0
git diff HEAD origin/codex/approve-and-execute = empty
```

同步后本地分支与远端 PR 分支对齐，不再有 ahead/behind 噪音。

## 当前结论

本轮 Adapter approve-and-execute 收口可以停止在 PR review 阶段。

除非 CI 或 reviewer 反馈问题，不建议继续扩大范围。下一步应聚焦：

1. 先合 Core 侧 handoff / approval contract 改造；
2. 再合 Adapter PR；
3. 合并后用同一套 `composer test:all` 与 WP smoke 作为回归门；
4. 如果继续做能力扩展，应保持 Adapter 只做 channel + explicit profile execution，
   不增加通用写入执行器。

