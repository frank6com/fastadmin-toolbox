# FastAdmin Tools FA工具箱

这是为 FastAdmin 开发的一个工具集合单控制器工具箱，提供了各种实用功能，帮助开发者更高效地快速完成 FastAdmin 的基础配置。

## 已实现功能

- **本地插件安装**：支持离线部署，直接上传 FastAdmin 标准插件压缩包进行安装，无需官方身份验证。

## 使用说明

1. 将 `Tools.php` 文件放置在 `application/admin/controller/` 目录下。
2. 在后台管理地址后追加 `/tools/install` 路径访问即可使用本地插件安装功能。