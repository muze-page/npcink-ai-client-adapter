# WordPress.org 上架文案草稿 - 中文

## 插件名称

Magick AI Adapter

## 简短描述

面向 Magick AI Core 治理和 WordPress Abilities API 路由的薄 OpenClaw 适配器。

## 标签建议

ai, governance, automation, rest-api, abilities

## 插件介绍

Magick AI Adapter 为 OpenClaw 提供一个 WordPress REST namespace，用于连接
Magick AI Core 治理层和 WordPress Abilities API 路由。

它读取 Core capability guidance，通过 WordPress Abilities API 运行已批准的
direct-read abilities，为写入或破坏性操作创建 Core proposals，并通过 Core
编排一个用户触发的 approve-and-execute 路径。

Adapter 有意保持很薄。它是 channel layer，不是 ability layer、governance
layer、cloud connector、workflow runtime、MCP server、model client，也不是通用
final write executor。

在产品化 OpenClaw 配置中，OpenClaw 连接 Adapter。Magick AI Core 仍然是
proposal storage、approval、commit preflight 和 audit 的治理后端。Magick AI
Abilities 和其他 providers 仍然负责 ability definitions、callbacks、schemas 和
permissions。

## 核心功能

- 为 OpenClaw 提供一个 WordPress REST namespace。
- 暴露 Adapter health、help、capability、read、proposal 和 handoff routes。
- 在 direct-read ability execution 前读取 Core governance guidance。
- 通过 WordPress Abilities API 路由 direct-read abilities。
- 将受治理的写入请求转发到 Magick AI Core proposal routes。
- 保持 Core approval、commit-preflight 和 audit 边界。
- 为 OpenClaw 创建一次性 WordPress Application Password 和非 secret connection manifest。
- 保持 Adapter 很薄，不变成 ability registry、workflow runtime、MCP server 或通用写入执行器。

## 适合谁使用

- 需要把 OpenClaw 连接到 Magick AI 站点的 WordPress 管理员。
- 需要一个 WordPress REST 入口的 OpenClaw 环境。
- 使用 Core 作为治理层、使用 Abilities API providers 作为执行合约层的 host setup。
- 希望清晰分离 channel、governance、ability、cloud layers 的开发者。

## 环境要求

- WordPress 7.0 或更高版本。
- PHP 8.0 或更高版本。
- Magick AI Core 用于治理。
- 一个 WordPress Abilities API provider，例如 Magick AI Abilities，用于能力定义和 callbacks。

## 系列插件边界

在 Magick AI 系列插件中：

- Magick AI Abilities 负责能力定义和 ability callback。
- Magick AI Core 负责治理、审批、preflight、audit。
- Magick AI Adapter 负责 OpenClaw 通道适配。
- Magick AI Cloud Addon 负责链接云端服务。

这个分层让 Adapter 专注于连接和路由，让 Core 保持治理真相来源，让 Abilities
保持能力合约层。
