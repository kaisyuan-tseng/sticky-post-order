# Sticky Post Order — 规范化审计报告

审计对象：`wp-content/plugins/sticky-order/`（独立插件，审计时版本 1.0.0，整改后 2.0.0）
审计框架：`wordpress-plugin-skill`（WordPress Plugin Handbook × WPCS），风格权威 = WordPress Coding Standards（本项目决定）
审计日期：2026-11-17
结论：10 项问题全部整改完毕。**本插件与 post-translations 的本质差异：前缀 `sticky_order_`（12 字母）本就合规，无需改前缀**；2.0.0 的破坏性来自「对外契约重命名 + i18n 默认文案来源切换」，已按用户决策做硬切（无数据迁移、无 REST 别名）。

## 一、问题分级清单（审计时状态 → 整改结果）

| 级别 | 编号 | 问题 | 位置（审计时） | 整改结果 |
|---|---|---|---|---|
| 🔴 高 | P1 | i18n 不完整：header 缺 `Domain Path`；声明了 text domain 却无 `load_plugin_textdomain()`、无 languages 目录；11 个默认文案键写死中文；REST schema description 写死英文 | `sticky-order.php:10,91-103`；`rest.php:32,37` | header 补 `Domain Path` + `License URI`；挂 `init` 加载 textdomain；11 键改英文 gettext；新增 POT/PO/MO；REST description 用 `__()`。中文站靠 `sticky-order-zh_CN.mo` 保留中文体验 |
| 🔴 高 | P2 | 缺 `uninstall.php`，option `sticky_order_queues` 删除插件后残留 | 插件根目录 | 新增 `uninstall.php`：删默认名 + 1.0.0 旧名 + `sticky_order_%` 前缀模糊兜底；不碰核心 `sticky_posts` |
| 🔴 高 | P3 | 缺 `LICENSE.txt`（header/readme 声明 GPL-2.0-or-later 但无随附全文） | 插件根目录 | 复制 GPL-2.0 全文 `LICENSE.txt`（340 行） |
| 🟡 中 | P4 | readme 无 `== Frequently Asked Questions ==` 与 `== Changelog ==` | `readme.txt` | 补两节；Stable tag → 2.0.0；FAQ 说明停用不丢数据、默认语言、分组配置、2.0.0 数据迁移 |
| 🟡 中 | P5 | **对外契约需按用户决策硬切重命名**（前缀已合规，但 REST/option 名一并规范化） | `rest.php:30,35`；`sticky-order.php:73`；`readme.txt`；headless 前端 | `sticky_order_rank`→`sticky_order_position`、`sticky_order_group`→`sticky_order_queue`、option `sticky_order_queues`→`sticky_order_queue_map`；对外方法 `rank_of`/`group_of`→`position_of`/`queue_of`。见第三节契约变更表 |
| ⚪ 低 | P6 | 部分方法缺 WPCS 标准 docblock | `rest.php:19,23`；`admin.php:18,24,55,123`；`queue.php:171,179` | 补全 docblock（含 `@param`/`@return`） |
| ⚪ 低 | P7 | 一处非 Yoda 条件 | `admin.php:41` `$hook !== self::$hook` | 改为 `self::$hook !== $hook` |
| ⚪ 低 | P8 | `$_POST['sticky_order']` 未 `wp_unslash` | `admin.php:63` | 加 `wp_unslash`（对数组递归）并加 PHPCS 豁免注释说明 |
| ⚪ 低 | P9 | 三个 `define` 无 `defined()` 守卫 | `sticky-order.php:20-22` | 各加 `if ( ! defined( … ) )` 守卫 |
| ⚪ 低 | P10 | `admin.js` 用 `var` | `admin.js:8` | 改 `const`；无用户可见文案，无需 JS i18n / Jed JSON |

## 二、已合规项（审计确认，本次未改动）

| 项 | 证据 | 结论 |
|---|---|---|
| 全局符号唯一前缀 ≥4 字母 | `sticky_order_`（函数/hook）、`STICKY_ORDER_`（常量），12–13 字母 | **PASS，无需整改**——与 post-translations（2 字母被迫硬切）关键差异 |
| ABSPATH 守卫 | 4 个 PHP 文件统一 `if ( ! defined( 'ABSPATH' ) ) { exit; }` | PASS |
| 数组语法 / 缩进 | 全仓 `array()` 长语法 + tab 缩进 | PASS |
| Yoda 条件 | 除 P7 一处外均为常量在左 | 已随 P7 统一 |
| 输出转义 | `esc_html`/`esc_attr`/`esc_url`/`wp_kses_post`/`esc_html__` | PASS |
| nonce + capability | `check_admin_referer` + `wp_nonce_field` + `current_user_can`（默认 `edit_others_posts`） | PASS |
| REST 只读设计 | `register_rest_field` 两个只读字段，`readonly=>true`，无自定义路由故无需 `permission_callback` | PASS |
| 输入净化 | `intval` + `array_unique` + 仅接受声明过的分组 key | PASS，另加 `wp_unslash`（P8） |
| 无手写 SQL / 无 $wpdb 写业务数据 | 全部走 WP API；uninstall 的清理查询已 `prepare` + `esc_like` | PASS |
| 无短码 / CPT / 分类法注册 / 内联资源 | — | PASS |
| 版本一致性 | PHP header / readme Stable tag / POT / 构建脚本均为 2.0.0 | 已整改并保持 |

## 三、对外契约变更表（本次是「变」，与 post-translations 的「前缀硬切」不同）

| 契约 | 1.0.0 | 2.0.0 | 性质 |
|---|---|---|---|
| REST 字段 | `sticky_order_rank` | `sticky_order_position` | 重命名，硬切无别名 |
| REST 字段 | `sticky_order_group` | `sticky_order_queue` | 重命名，硬切无别名 |
| 内部取值方法 | `rank_of` / `group_of` | `position_of` / `queue_of` | 随字段重命名 |
| option | `sticky_order_queues` | `sticky_order_queue_map` | 重命名，旧数据不迁移 |
| 内部缓存键 | `ranks` / `groups` | 保持不变 | 内部实现，非对外契约；保持以缩小回归面 |

**保持不变（前端/站点配置无需改）**：
- 7 个 filter 名：`sticky_order_groups` / `sticky_order_group_taxonomy` / `sticky_order_post_types` / `sticky_order_option_name` / `sticky_order_capability` / `sticky_order_menu_parent` / `sticky_order_labels`
- CSS 类名 `sticky-order-*`、菜单 slug `sticky-order`、表单 name `sticky_order[...]`、nonce action `sticky_order_save`
- MU 配置 `wp-content/mu-plugins/sticky-order-config.php` 无需改动（filter 名不变）

**headless 前端必须同步的 3 处**（前端源码不在本仓库，需另行处理）：
1. `src/lib/wordpress.ts` 的 `CATEGORY_BINDING` 中字段映射：`sticky_order_rank` → `sticky_order_position`，`sticky_order_group` → `sticky_order_queue`
2. `src/lib/resources-source.ts` 的 `headerFeatured` / `featuredRank` 读取点同步改名
3. 重新构建静态输出（option 改名后存量排序不迁移，首次发布回到「按日期」顺序，需在后台重排一次）

## 四、WordPress.org 18 条 guideline 审查

| # | 条目 | 结论 | 说明 |
|---|---|---|---|
| 1 | 明确且实质的功能 | PASS | 置顶队列机制完整 |
| 2 | GPL 兼容许可证 | ✅ 已整改 | 补 `LICENSE.txt`（GPL-2.0 全文） |
| 3 | 禁 trialware / 付费墙 | PASS | 无 |
| 4 | 不未经同意跟踪 | PASS | 无埋点、无外部请求 |
| 5 | 不运行时下发可执行代码 / 不混淆 | PASS | 无 eval/base64/远程代码 |
| 6 | 不捆绑核心已打包库 | PASS | 复用核心 `jquery-ui-sortable` |
| 7 | 全局符号唯一前缀 ≥4 字母 | **PASS（本次无需整改）** | `sticky_order_`/`STICKY_ORDER_` 12–13 字母，已合规 |
| 8 | nonce + capability 校验 | PASS | 见已合规项 |
| 9 | 输入净化 / 输出转义 | ✅ 已加固 | 原有 esc_* + 新增 `wp_unslash` |
| 10 | 数据库用 WP API / prepare | ✅ 已整改 | uninstall 清理查询 `prepare` + `esc_like`，不碰核心 `sticky_posts` |
| 11 | 代码可读 / 结构清晰 | ✅ 已整改 | docblock 补全 |
| 12 | i18n 完整 / text domain = slug | ✅ 已整改 | `Domain Path` + `init` 加载 + POT/PO/MO + REST i18n；domain `sticky-order` = slug |
| 13 | readme 规范 | ✅ 已整改 | Stable tag 2.0.0、FAQ、Changelog、License URI |
| 14 | 无 affiliate / 垃圾推广 | PASS | 无 |
| 15 | 商标 | 附条件 | `sticky-post-order` 为描述性通用词，风险低；提交前需在 .org 目录复核 slug 未被占用（工程判断，非法律意见） |
| 16 | 描述 / 截图不误导 | PASS | 描述与实现一致 |
| 17 | 无调试输出 / PHP notice | 待运行时复测 | `php -l` 通过；PHPCS 安全规则通过；本环境无 WP 运行时，建议 `WP_DEBUG` 下人工走一遍 |
| 18 | 数据持久化与卸载清晰 | ✅ 已整改 | 补 `uninstall.php`；FAQ 说明停用不丢数据 |

## 五、用户决策项（本次规范化）

1. **规范化深度**：完整套餐（uninstall / LICENSE / readme / header / docblock / i18n / AUDIT）
2. **分发目标**：对齐 WordPress.org 完整性（推定；readme 已按 .org 规范补齐）
3. **前缀**：保持不变（已合规，用户未选换前缀）
4. **硬切范围**：REST 字段与 option 名一并重命名（用户明确选择）
5. **新命名**：`position` / `queue` / `queue_map`（用户确认）
6. **存量数据**：不迁移（硬切）；旧 option 在卸载时清理
7. **默认文案语言**：源码英文 msgid + zh_CN 语言包；保留 `sticky_order_labels` filter 覆盖

## 六、遗留提醒

1. **前端同步是发布前置条件**：headless 前端必须改第三节 3 处消费点，否则队列顺序失效、回退日期排序。前端仓库不在本工作区，本次未代改。
2. **部署顺序**：插件与 `languages/` 必须同批部署，否则中文站后台会短暂显示英文。`load_plugin_textdomain` 已挂 `init`。
3. **MU 配置的中文 intro 保留在站点侧**：`sticky-order-config.php` 覆盖的 `intro` 含本站前端事实，未进通用 POT；filter 名未变，无需同步改；站点若切换语言需自行处理该覆盖文案。
4. **自定义 option 名的兜底边界**：若某站点通过 `sticky_order_option_name` 改成完全不含 `sticky_order_` 前缀的名字，uninstall 的模糊兜底会失效，需手动清理。
5. **回滚**：快照 tag `pre-normalization-1.0.0`（commit `fbd58bb`），分支 `refactor/normalization-2.0.0`；`git reset --hard pre-normalization-1.0.0` 可回到整改前。
6. **运行时验证**：`WP_DEBUG` 下分别走中/英文站点的后台页、保存流程、REST 两字段返回、卸载清理。
