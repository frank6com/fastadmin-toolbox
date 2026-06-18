<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;
use think\Exception;
use think\addons\AddonException;
use think\addons\Service;
use think\Cache;
use fast\Random;
use think\Db;

/**
 * FastAdmin 开发工具箱
 *
 * 仅在 app_debug=true 且超级管理员登录时可用。
 * 提供开发辅助工具、插件本地安装、PHP 环境信息等功能。
 */
class Toolbox extends Backend
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    protected $debug = false;

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('app_debug')) {
            $this->error('工具箱仅在调试模式下可用，请开启 app_debug');
        }

        if (!$this->auth->isSuperAdmin()) {
            $this->error(__('Access is allowed only to the super management group'));
        }
    }

    /**
     * 统一渲染
     *
     * @param string $title   页面标题
     * @param string $content 核心 HTML
     * @param string $scripts JS 代码（不含 script 标签）
     * @param string $styles  内联样式
     * @param bool   $showBack 是否显示返回按钮
     * @param string $backUrl  返回链接
     */
    protected function renderPage($title, $content, $scripts = '', $styles = '', $showBack = false, $backUrl = '')
    {
        $viewConfig = $this->view->config;
        $viewConfig['jsname'] = '';
        $this->view->config = $viewConfig;
        $this->assign('config', $viewConfig);

        $assignConfig = [
            'toolbox_index_url'     => url('index'),
            'toolbox_installer_url' => url('installer'),
            'toolbox_phpinfo_url'   => url('phpinfo'),
            'addon_testdata_url'    => url('addon/testdata'),
            'toolbox_watcher_url'   => url('addonWatcher'),
        ];
        $this->assignconfig($assignConfig);

        $this->assign('title', $title);

        $backHtml = '';
        if ($showBack) {
            $backUrl = $backUrl ?: url('index');
            $backHtml = '<a href="' . $backUrl . '" class="btn btn-default back-btn btn-addtabs" style="margin-bottom:15px;"><i class="fa fa-arrow-left"></i> 工具箱首页</a>';
        }

        $lay = <<<HTML
            <style>
                /* 顶部 Hero */
                .tb-hero { text-align:center; padding:40px; }
                .tb-hero h2 { margin:0 0 10px; font-size:22px; font-weight:bold; color:#333; letter-spacing:0.5px; }
                .tb-hero h2 i { font-size:24px; }
                .tb-hero-sub { margin:0; color:#b0b0b0; font-size:12px; letter-spacing:1px; }
            </style>
            <div class="toolbox-layout">
                {$backHtml}
                <div class="toolbox-content">
                    {$content}
                </div>
                <div class="toolbox-footer" style="text-align:center;color:#999;margin-top:30px;padding:15px 0;border-top:1px solid #eee;">
                    FastAdmin Toolbox · FA开发工具箱
                </div>
            </div>
        HTML;

        $html = $this->display($lay);

        if ($styles) {
            $html = str_replace('</head>', "<style>{$styles}</style></head>", $html);
        }

        if ($scripts) {
            $scriptTag = <<<'SCRIPT'
                <script>
                (function checkReady() {
                    if (typeof jQuery !== "undefined" && typeof Layer !== "undefined") {
                        (function($, Layer) {
                SCRIPT;
                            $scriptTag .= $scripts;
                            $scriptTag .= <<<'SCRIPT'
                        })(jQuery, Layer);
                    } else {
                        setTimeout(checkReady, 50);
                    }
                })();
                </script>
            SCRIPT;
            $html = str_replace('</body>', $scriptTag . '</body>', $html);
        }

        return $html;
    }

    /**
     * 模板复用渲染
     * 
     * 
     */
    protected function renderTpl($title, $desc = '', $tplPath, $prepend = '', $append = '', $scripts = '', $styles = '', $showBack = true, $backUrl = '')
    {
        $viewConfig = $this->view->config;
        $viewConfig['jsname'] = '';
        $this->view->config = $viewConfig;
        $this->assign('config', $viewConfig);

        $assignConfig = [
            'toolbox_index_url'     => url('index'),
            'toolbox_installer_url' => url('installer'),
            'toolbox_phpinfo_url'   => url('phpinfo'),
            'addon_testdata_url'    => url('addon/testdata'),
        ];
        $addons = get_addon_list();
        foreach ($addons as $k => &$v) {
            $config = get_addon_config($v['name']);
            $v['config'] = $config ? 1 : 0;
            $v['url'] = str_replace($this->request->server('SCRIPT_NAME'), '', $v['url']);
        }
        $this->assignconfig(['addons' => $addons, 'api_url' => config('fastadmin.api_url'), 'faversion' => config('fastadmin.version'), 'domain' => request()->host(true)]);
        $this->assignconfig($assignConfig);

        $this->assign('title', $title);
        // $this->view->engine->layout(false);
        $backUrl = url('index');
        $backHtml = '<a href="' . $backUrl . '" class="btn btn-default back-btn btn-addtabs" style="margin-bottom:15px;"><i class="fa fa-arrow-left"></i> 工具箱首页</a>';
        $header = <<<HTML
            <style>
                /* 顶部 Hero */
                .tb-hero { text-align:center; padding:40px; }
                .tb-hero h2 { margin:0 0 10px; font-size:22px; font-weight:bold; color:#333; letter-spacing:0.5px; }
                .tb-hero h2 i { font-size:24px; }
                .tb-hero-sub { margin:0; color:#b0b0b0; font-size:12px; letter-spacing:1px; }
            </style>
            <div class="tb-hero">
                <h2><i class="fa fa-upload" style="color:#18bc9c;"></i> {$title}</h2>
                <p class="tb-hero-sub">{$desc}</p>
            </div>
        HTML;
        $html =  $this->fetch($tplPath);
        if ($scripts) {
            $scriptTag = <<<'SCRIPT'
                <script>
                (function checkReady() {
                    if (typeof jQuery !== "undefined" && typeof Layer !== "undefined") {
                        (function($, Layer) {
                SCRIPT;
                            $scriptTag .= $scripts;
                            $scriptTag .= <<<'SCRIPT'
                        })(jQuery, Layer);
                    } else {
                        setTimeout(checkReady, 50);
                    }
                })();
                </script>
            SCRIPT;
            $html = str_replace('</body>', $styles . $scriptTag . '</body>', $html);
        }
        $html = str_replace('<div class="content">', '<div class="content">' . ($showBack ? $backHtml : '') . $header . $prepend, $html);
        $html = $this->appendBeforeContentEnd($html, $append);

        return $this->display($html);
    }

    /**
     * 工具箱首页
     */
    public function index()
    {
        // -------- 工具集（按分类） --------
        $tools = [
            '系统运维' => [
                [
                    'icon'   => 'fa-info-circle',
                    'title'  => '系统环境信息',
                    'desc'   => '查看 PHP、ThinkPHP、FastAdmin 版本及关键扩展状态等服务器环境详情。',
                    'url'    => url('phpinfo'),
                    'status' => 'ready',
                    'color'  => '#18bc9c',
                ],
                [
                    'icon'   => 'fa-file-text-o',
                    'title'  => '日志查看器',
                    'desc'   => '实时查看系统运行日志、错误日志与 SQL 日志，支持下载。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#d9534f',
                ],
            ],
            '开发辅助' => [
                [
                    'icon'   => 'fa-key',
                    'title'  => '密码生成器',
                    'desc'   => '密码加密生成工具自定义盐值。',
                    'url'    => url('password'),
                    'status' => 'ready',
                    'color'  => '#337ab7',
                ],
                [
                    'icon'   => 'fa-magic',
                    'title'  => '本地插件管理器',
                    'desc'   => '可进行本地插件的手动安装、更新、卸载等管理操作，方便插件开发调试使用。',
                    'url'    => url('addons'),
                    'status' => 'ready',
                    'color'  => '#f39c12',
                ],
                [
                    'icon'   => 'fa-sitemap',
                    'title'  => '菜单管理器',
                    'desc'   => '可以方便的对菜单进行导入导出操作，方便插件开发使用。',
                    'url'    => url('rules'),
                    'status' => 'ready',
                    'color'  => '#337ab7',
                ],
                [
                    'icon'   => 'fa-eye',
                    'title'  => '插件监听器',
                    'desc'   => '可以在插件开发时监听文件变化并同步至插件目录。',
                    'url'    => url('addonwatcher'),
                    'status' => 'ready',
                    'color'  => '#8e44ad',
                ],
                [
                    'icon'   => 'fa-magic',
                    'title'  => '初始化工具',
                    'desc'   => '引导完成基础配置，方便快速上手开发或部署。',
                    'url'    => url('initwizard'),
                    'status' => 'wip',
                    'color'  => '#5bc0de',
                ],
                [
                    'icon'   => 'fa-cubes',
                    'title'  => '前端组件调试器',
                    'desc'   => '可以快速测试和调试内置的前端组件（如表格、表单、弹窗等）的功能和样式。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#e74c3c',
                ],
                [
                    'icon'   => 'fa-bar-chart',
                    'title'  => 'ECharts 可视化工具',
                    'desc'   => '基于 Apache ECharts 的在线图表设计与调试工具，支持导出配置代码。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#f39c12',
                ],
                [
                    'icon'   => 'fa-table',
                    'title'  => '表格组件调试器',
                    'desc'   => '可以快速测试和调试内置的表格组件的功能和样式，支持生成配置代码。',
                    'url'    => url('table'),
                    'status' => 'wip',
                    'color'  => '#f39c12',
                ],
                [
                    'icon'   => 'fa-cubes',
                    'title'  => '模型构建工具',
                    'desc'   => '可以方便的对模型常用功能进行快速构建',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#e74c3c',
                ],
                [
                    'icon'   => 'fa-language',
                    'title'  => '语言包编辑器',
                    'desc'   => '在线编辑多语言包文件，支持导入导出与对比。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#8e44ad',
                ],
                [
                    'icon'   => 'fa-upload',
                    'title'  => '插件安装器',
                    'desc'   => '上传 zip 包离线安装插件，自动解压识别，支持安装后导入测试数据。',
                    'url'    => url('installer'),
                    'status' => 'hidden',
                    'color'  => '#18bc9c',
                ],
                [
                    'icon'   => 'fa-th-list',
                    'title'  => '路由管理器',
                    'desc'   => '查看当前应用所有路由规则、请求方法与中间件。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#e67e22',
                ],
                [
                    'icon'   => 'fa-file-code-o',
                    'title'  => '上传检测器',
                    'desc'   => '检测上传文件的类型、大小、内容等，确保上传文件的安全性。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#d9534f',
                ],
                [
                    'icon'   => 'fa-exchange',
                    'title'  => '表前缀处理器',
                    'desc'   => '表结构导出转换表前缀，方便迁移部署到不同环境的数据库。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#5cb85c',
                ],
                [
                    'icon'   => 'fa-code',
                    'title'  => '代码片段管理器',
                    'desc'   => '管理和组织常用的代码片段，提高开发效率。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#34495e',
                ],
            ],
            '实用工具' => [
                [
                    'icon'   => 'fa-terminal',
                    'title'  => '正则表达式工具',
                    'desc'   => '正则表达式在线测试与调试，支持常用正则语法高亮与示例库。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#3c8dbc',
                ],
                [
                    'icon'   => 'fa-clock-o',
                    'title'  => '时间戳转换',
                    'desc'   => 'Unix 时间戳与日期时间的双向实时互转。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#5bc0de',
                ],
                [
                    'icon'   => 'fa-code',
                    'title'  => '编码转换',
                    'desc'   => 'URL 编解码、Base64 编解码等常用编码格式互转。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#d9534f',
                ],
                [
                    'icon'   => 'fa-exchange',
                    'title'  => 'WS 调试工具',
                    'desc'   => 'WebSocket 在线测试工具，支持发送消息、查看响应与连接状态。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#f39c12',
                ],
                [
                    'icon'   => 'fa-image',
                    'title'  => '图转Base64工具',
                    'desc'   => '将图片文件转换为Base64编码字符串，方便在HTML中直接使用。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#5cb85c',
                ],
                [
                    'icon'   => 'fa-eyedropper',
                    'title'  => '颜色工具',
                    'desc'   => '颜色选择器、HEX/RGB/HSL 互转、调色板生成。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#8e44ad',
                ],
            ],
            '插件增强' => [
                [
                    'icon'   => 'fa-check-circle-o',
                    'title'  => '依赖检测',
                    'desc'   => '检测插件依赖关系，确保插件正常运行。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#34495e',
                ]
            ]
        ];
        $docs = [
            [
                'icon'  => 'fa-file-text-o',
                'title' => 'FastAdmin 框架文档',
                'desc'  => 'FastAdmin 框架官方文档与社区资源',
                'url'   => 'https://doc.fastadmin.net/doc',
                'color' => '#18bc9c',
                'tag'   => '框架核心',
            ],
            [
                'icon'  => 'fa-file-text-o',
                'title' => 'ThinkPHP 5.0',
                'desc'  => 'ThinkPHP 5.0 完全开发手册',
                'url'   => 'https://www.kancloud.cn/manual/thinkphp5/118003',
                'color' => '#5bc0de',
                'tag'   => '框架核心',
            ],
            [
                'icon'  => 'fa-table',
                'title' => '一张图解析表格列表的功能',
                'desc'  => '一张图解析FastAdmin中的表格列表的功能',
                'url'   => 'https://ask.fastadmin.net/article/323.html',
                'color' => '#18bc9c',
                'tag'   => '教程',
            ],
            [
                'icon'  => 'fa-th',
                'title' => '一张图解析FormBuilder表单生成器',
                'desc'  => '一张图解析FastAdmin中的FormBuilder表单生成器',
                'url'   => 'https://ask.fastadmin.net/article/5567.html',
                'color' => '#18bc9c',
                'tag'   => '教程',
            ],
            [
                'icon'  => 'fa-window-maximize',
                'title' => '一张图解析弹出窗口的功能',
                'desc'  => '一张图解析FastAdmin中的弹出窗口的功能',
                'url'   => 'https://ask.fastadmin.net/article/2527.html',
                'color' => '#18bc9c',
                'tag'   => '教程',
            ],
            [
                'icon'  => 'fa-bar-chart',
                'title' => 'ECharts 示例',
                'desc'  => '数据可视化图表库文档',
                'url'   => 'https://echarts.apache.org/examples/zh/index.html',
                'color' => '#e74c3c',
                'tag'   => '图表库',
            ],
            [
                'icon'  => 'fa-code',
                'title' => 'PHP 函数参考',
                'desc'  => 'PHP 内置函数官方文档',
                'url'   => 'https://www.php.net/manual/zh/function.array-filter.php',
                'color' => '#d9534f',
                'tag'   => 'PHP函数',
            ],
            [
                'icon'  => 'fa-css3',
                'title' => 'Bootstrap 3',
                'desc'  => 'Bootstrap 3 中文文档',
                'url'   => 'https://v3.bootcss.com/',
                'color' => '#563d7c',
                'tag'   => '前端UI',
            ],
            [
                'icon'  => 'fa-code',
                'title' => 'jQuery API',
                'desc'  => 'jQuery API 文档',
                'url'   => 'https://v3.bootcss.com/css/',
                'color' => '#0769ad',
                'tag'   => 'JS库',
            ],
            [
                'icon'  => 'fa-flag',
                'title' => 'Font Awesome 4',
                'desc'  => 'Font Awesome 4 图标库',
                'url'   => 'https://fontawesome.com/v4/icons/',
                'color' => '#228b22',
                'tag'   => '图标库',
            ],
            [
                'icon'  => 'fa-book',
                'title' => 'FastAdmin 开发者文档',
                'desc'  => 'FastAdmin 开发者文档与教程',
                'url'   => 'https://doc.fastadmin.net/developer',
                'color' => '#18bc9c',
                'tag'   => '框架核心',
            ],
            [
                'icon'  => 'fa-book',
                'title' => '插件开发简明开发教程',
                'desc'  => 'FastAdmin插件开发教程之简明开发教程',
                'url'   => 'https://ask.fastadmin.net/article/324.html',
                'color' => '#18bc9c',
                'tag'   => '教程',
            ],
            [
                'icon'  => 'fa-plug',
                'title' => 'FastAdmin 插件标识检测',
                'desc'  => 'FastAdmin 应用插件标识检测',
                'url'   => 'https://www.fastadmin.net/developer/idcheck.html',
                'color' => '#18bc9c',
                'tag'   => '框架核心',
            ],
            [
                'icon'  => 'fa-table',
                'title' => 'Bootstrap Table',
                'desc'  => '表格组件文档',
                'url'   => 'https://bootstrap-table.com/docs/getting-started/introduction/',
                'color' => '#d9534f',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-window-maximize',
                'title' => 'Layer',
                'desc'  => 'Web 弹层组件文档',
                'url'   => 'https://layui.dev/docs/2/layer/',
                'color' => '#f39c12',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-sitemap',
                'title' => 'jstree 官方文档',
                'desc'  => '树形控件文档',
                'url'   => 'https://www.jstree.com/',
                'color' => '#337ab7',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-check-square-o',
                'title' => 'Nice Validator',
                'desc'  => '表单验证组件文档',
                'url'   => 'https://validator.niceue.com/docs/index.html',
                'color' => '#5cb85c',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-search',
                'title' => 'SelectPage 官方文档',
                'desc'  => '动态下拉分页选择插件文档',
                'url'   => 'https://terryz.github.io/docs-jq/select-page/docs.html',
                'color' => '#8e44ad',
                'tag'   => '组件库',
            ],
        ];


        $toolHtml = '';
        foreach ($tools as $catName => $tools) {
            $cards = '';
            foreach ($tools as $t) {
                $badge = ($t['status'] === 'ready' or $t['status'] === 'hidden')
                    ? '<span class="card-badge badge-ready">可用</span>'
                    : '<span class="card-badge badge-wip">准备中</span>';
                $href = ($t['status'] === 'ready' or $t['status'] === 'hidden')
                    ? 'href="' . $t['url'] . '"'
                    : 'href="javascript:void(0);"';
                $cls = ($t['status'] === 'ready' or $t['status'] === 'hidden') ? 'tool-card-active' : 'tool-card-disabled';
                $hidden = $t['status'] === 'hidden' ? 'hidden' : '';
                $addtabs = ($t['status'] === 'ready' or $t['status'] === 'hidden') ? 'btn-addtabs' : '';
                $cards .= <<<CARD
                    <a {$href} class="tool-card {$cls} {$hidden} {$addtabs}" title="{$t['title']}" alt="{$t['desc']}">
                        <div class="tool-card-icon" style="background:{$t['color']};">
                            <i class="fa {$t['icon']}"></i>
                        </div>
                        <div class="tool-card-body">
                            <div class="tool-card-title">{$t['title']}</div>
                            <div class="tool-card-desc">{$t['desc']}</div>
                        </div>
                        {$badge}
                    </a>
                CARD;
            }
            $toolHtml .= <<<HTML
                <div class="tb-cat-section">
                    <div class="tb-cat-title"><i class="fa fa-cogs"></i> {$catName}</div>
                    <div class="tb-cat-cards">{$cards}</div>
                </div>
            HTML;
        }

        // -------- 速查文档（客户端分页） --------
        $docsJson = json_encode($docs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pageSize = 15;
        $totalPages = ceil(count($docs) / $pageSize);
        $initDocs = array_slice($docs, 0, $pageSize);

        $this->assignconfig([
            'toolbox_docs' => $docs,
            'toolbox_doc_pagesize' => $pageSize,
            'toolbox_doc_totalpages' => $totalPages,
        ]);

        $docCards = '';
        foreach ($initDocs as $i => $d) {
            $docCards .= <<<CARD
                <a class="doc-item" href="{$d['url']}" target="_blank" data-doc-index="{$i}">
                    <div class="doc-item-left">
                        <div class="doc-item-icon" style="background:{$d['color']};">
                            <i class="fa {$d['icon']}"></i>
                        </div>
                        <div class="doc-item-info">
                            <div class="doc-item-title">{$d['title']}</div>
                            <div class="doc-item-desc">{$d['desc']}</div>
                        </div>
                    </div>
                    <span class="doc-item-tag">{$d['tag']}</span>
                </a>
            CARD;
        }

        $content = <<<HTML
            <style>
            /* ===== 首页样式 ===== */

            /* 左右两栏布局 */
            .tb-main-row { display:flex; gap:20px; align-items:flex-start; }
            .tb-tools-col { flex:0 0 66.6%; min-width:0; }
            .tb-docs-col { flex:1; min-width:270px; }

            /* 分类区块 */
            .tb-cat-section { margin-bottom:24px; }
            .tb-cat-title { font-size:13px; font-weight:600; color:#777; margin:0 0 12px 2px; padding-bottom:8px; border-bottom:2px solid #eef1f5; display:flex; align-items:center; }
            .tb-cat-title i { color:#18bc9c; margin-right:6px; font-size:14px; }
            .tb-cat-count { margin-left:auto; font-size:11px; font-weight:400; color:#bbb; }

            /* 分类内卡片网格 */
            .tb-cat-cards { display:flex; flex-wrap:wrap; gap:12px; }

            /* 工具卡片 (增强版) */
            .tool-card { display:flex; align-items:flex-start; width:calc(33.333% - 8px); min-width:210px; background:#fff; border:1px solid #e8ecf1; border-radius:8px; padding:18px 16px; text-decoration:none; color:inherit; transition:all 0.25s ease; box-sizing:border-box; position:relative; overflow:hidden; }
            .tool-card::before { content:""; position:absolute; top:0; left:0; right:0; height:3px; background:transparent; transition:background 0.25s ease; border-radius:8px 8px 0 0; }
            .tool-card-active:hover { border-color:#c8d6e5; box-shadow:0 6px 24px rgba(0,0,0,0.06); transform:translateY(-2px); cursor:pointer; }
            .tool-card-active:hover::before { background:#18bc9c; }
            .tool-card-disabled { opacity:0.5; cursor:not-allowed; filter:grayscale(30%); }
            .tool-card-icon { flex-shrink:0; width:46px; height:46px; border-radius:10px; color:#fff; text-align:center; line-height:46px; font-size:20px; margin-right:14px; box-shadow:0 3px 8px rgba(0,0,0,0.1); }
            .tool-card-body { flex:1; min-width:0; }
            .tool-card-title { font-size:14px; font-weight:600; color:#333; margin-bottom:4px; display:flex; align-items:center; }
            .tool-card-desc { font-size:12px; color:#999; line-height:1.5; display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }


            .card-badge { padding:2px 8px; font-size:10px; font-weight:500; vertical-align:middle; line-height:1.6; position: absolute; top:0; right:0; }
            .badge-ready { background:#dff0d8; color:#3c763d; }
            .badge-wip { background:#f3f3f3; color:#aaa; }

            /* 文档区 (增强版) */
            .tb-docs-section { background:#fff; border:1px solid #e8ecf1; border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.02); }
            .tb-docs-title { padding:12px 16px; font-size:14px; font-weight:600; color:#555; border-bottom:1px solid #eef1f5; background:#fafbfc; display:flex; align-items:center; }
            .tb-docs-title i { color:#18bc9c; margin-right:7px; }

            .doc-item { display:flex; align-items:center; justify-content:space-between; padding:11px 16px; border-bottom:1px solid #f4f6f8; transition:all 0.2s ease; text-decoration:none; color:inherit; }
            .doc-item:last-child { border-bottom:none; }
            .doc-item:hover { background:#f8fdfa; text-decoration:none; color:inherit; padding-left:20px; }

            .doc-item-left { display:flex; align-items:center; flex:1; min-width:0; margin-right:10px; }
            .doc-item-icon { flex-shrink:0; width:32px; height:32px; border-radius:6px; color:#fff; text-align:center; line-height:32px; font-size:14px; margin-right:10px; }
            .doc-item-info { flex:1; min-width:0; }
            .doc-item-title { font-size:12px; font-weight:600; color:#444; }
            .doc-item-desc { font-size:11px; color:#bbb; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

            .doc-item-tag { flex-shrink:0; display:inline-block; padding:2px 8px; background:#f5f6f8; border:1px solid #e8ecf1; border-radius:3px; font-size:10px; color:#999; white-space:nowrap; transition:all 0.2s; }
            .doc-item:hover .doc-item-tag { background:#18bc9c; border-color:#18bc9c; color:#fff; }

            /* 响应式 */
            @media (max-width:992px) {
                .tb-main-row { flex-direction:column; }
                .tb-tools-col { flex:1; }
                .tool-card { width:calc(50% - 6px); }
            }
            @media (max-width:600px) {
                .tool-card { width:100%; }
            }
            </style>

            <div class="tb-hero">
                <h2><i class="fa fa-wrench" style="color:#18bc9c;"></i> FastAdmin 开发工具箱</h2>
                <p class="tb-hero-sub">开发辅助工具集 · 仅调试模式下可用</p>
            </div>

            <div class="tb-main-row">
                <div class="tb-tools-col">
                    {$toolHtml}
                </div>
                <div class="tb-docs-col">
                    <div class="tb-docs-section" id="tb-docs-section">
                        <div class="tb-docs-title"><i class="fa fa-book"></i> 速查文档 <span class="tb-doc-page-info" id="tb-doc-page-info" style="margin-left:auto;font-size:11px;font-weight:400;color:#bbb;"></span></div>
                        <div id="tb-doc-list">{$docCards}</div>
                        <div id="tb-doc-pager" class="tb-doc-pager" style="display:none;padding:8px 16px;border-top:1px solid #f4f6f8;text-align:center;">
                            <button class="btn btn-xs btn-default" id="tb-doc-prev" disabled><i class="fa fa-chevron-left"></i></button>
                            <span class="tb-doc-page-num" id="tb-doc-page-num" style="margin:0 10px;font-size:12px;color:#666;"></span>
                            <button class="btn btn-xs btn-default" id="tb-doc-next"><i class="fa fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        HTML;

        $scripts = '
            (function() {
                var docs = Config.toolbox_docs || [];
                var pageSize = Config.toolbox_doc_pagesize || 5;
                var totalPages = Config.toolbox_doc_totalpages || 1;
                var currentPage = 1;

                if (totalPages <= 1) return;

                var $list = $("#tb-doc-list");
                var $pager = $("#tb-doc-pager");
                var $prev = $("#tb-doc-prev");
                var $next = $("#tb-doc-next");
                var $pageNum = $("#tb-doc-page-num");
                var $pageInfo = $("#tb-doc-page-info");

                $pager.show();

                function renderPage(p) {
                    currentPage = p;
                    var start = (p - 1) * pageSize;
                    var slice = docs.slice(start, start + pageSize);
                    var html = "";
                    for (var i = 0; i < slice.length; i++) {
                        var d = slice[i];
                        html += "<a class=\"doc-item\" href=\"" + d.url + "\" target=\"_blank\">";
                        html += "<div class=\"doc-item-left\">";
                        html += "<div class=\"doc-item-icon\" style=\"background:" + d.color + ";\"><i class=\"fa " + d.icon + "\"></i></div>";
                        html += "<div class=\"doc-item-info\">";
                        html += "<div class=\"doc-item-title\">" + d.title + "</div>";
                        html += "<div class=\"doc-item-desc\">" + d.desc + "</div>";
                        html += "</div></div>";
                        html += "<span class=\"doc-item-tag\">" + d.tag + "</span>";
                        html += "</a>";
                    }
                    $list.html(html);
                    $pageNum.text(p + " / " + totalPages);
                    $pageInfo.text("(" + docs.length + " 篇)");
                    $prev.prop("disabled", p <= 1);
                    $next.prop("disabled", p >= totalPages);
                }

                $prev.on("click", function() {
                    if (currentPage > 1) renderPage(currentPage - 1);
                });
                $next.on("click", function() {
                    if (currentPage < totalPages) renderPage(currentPage + 1);
                });

                renderPage(1);

                /* ====== Konami Code ====== */
                var SEQUENCE = ["ArrowUp","ArrowUp","ArrowDown","ArrowDown","ArrowLeft","ArrowRight","ArrowLeft","ArrowRight","b","a"];
                var idx = 0;

                document.addEventListener("keydown", function(e) {
                    var key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
                    if (key === SEQUENCE[idx]) {
                        idx++;
                        if (idx === SEQUENCE.length) $(".tb-main-row .hidden").removeClass("hidden").addClass("fade-in");
                    } else {
                        idx = 0;
                    }
                });
            })();
        ';

        return $this->renderPage('工具箱 - FastAdmin 开发工具箱', $content, $scripts, '', false);
    }

    /**
     * 密码生成器
     */
    public function password()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $salt = Random::alnum();
            $password = $this->auth->getEncryptPassword($params['password'], $salt);
            $token = $this->request->token('__token__', 'sha1');
            $this->success('生成成功', null, ['password' => $password, 'salt' => $salt, 'token' => $token]);
        }
        
        $content = <<<HTML
            <style>
            .tb-password-wrap { max-width:600px; margin:40px auto; padding:20px; background:#fff; border:1px solid #e8ecf1; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
            .tb-password-wrap h3 { text-align:center; color:#18bc9c; margin-bottom:20px; }
            .form-group { margin-bottom:16px; }
            .form-group label { display:block; font-size:13px; color:#555; margin-bottom:6px; }
            .form-group input[type="text"] { width:100%; padding:8px 12px; border:1px solid #d2d6de; border-radius:4px; transition:border-color 0.3s ease; }
            .form-group input[type="text"]:focus { border-color:#18bc9c; outline:none; }
            .btn-generate { display:block; width:100%; padding:10px 0; background:#18bc9c; color:#fff; font-size:14px; font-weight:600; border:none; border-radius:4px; cursor:pointer; transition:bg-color 0.3s ease; }
            .btn-generate:hover { background:#15a589; }
            .result { margin-top:20px; padding:12px 16px; background:#f5f6f8; border:1px solid #e8ecf1; border-radius:4px; word-break:break-all; position:relative;}
            .result-label { position:absolute; top:-10px; left:10px; background:#18bc9c;color:#fff;padding:2px 6px;font-size:10px;border-radius:3px;}
            </style>

            <div class="tb-hero">
                <h2><i class="fa fa-key" style="color:#18bc9c;"></i> 密码生成器</h2>
                <p class="tb-hero-sub">支持根据自定义字符串或随机生成数据库密码及密码盐</p>
            </div>
            <div class="tb-password-wrap">
                {:token()}
                <div class="form-group">
                    <label for="code">密码</label>
                    <input type="text" id="code" value="" placeholder="请输入密码，留空则为随机取值">
                </div>
                <div class="form-group">
                    <label for="">随机选项</label>
                    <input type="checkbox" id="include-uppercase" checked> 包含大写字母
                    <input type="checkbox" id="include-lowercase" checked> 包含小写字母
                    <input type="checkbox" id="include-numbers" checked> 包含数字
                    <input type="checkbox" id="include-symbols"> 包含特殊字符
                </div>
                <button class="btn-generate" id="btn-generate">生成</button>
                <div class="result" id="result" style="display:none;">
                    <div class="result-label">生成结果</div>
                    <div id="result-text"></div>
                </div>
            </div>
        HTML;
        $scripts = '
            (function() {
                function generatePassword(length, options) {
                    var charset = "";
                    if (options.includeUppercase) charset += "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                    if (options.includeLowercase) charset += "abcdefghijklmnopqrstuvwxyz";
                    if (options.includeNumbers) charset += "0123456789";
                    if (options.includeSymbols) charset += "!@#$%^&*()_+~`|}{[]:;?><,./-=";

                    if (!charset) return "";

                    var password = "";
                    for (var i = 0; i < length; i++) {
                        var randomIndex = Math.floor(Math.random() * charset.length);
                        password += charset[randomIndex];
                    }
                    return password;
                }

                $("#code").on("change input", function() {
                    if ($(this).val().trim()) {
                        $("#include-uppercase, #include-lowercase, #include-numbers, #include-symbols").prop("disabled", true);
                    } else {
                        $("#include-uppercase, #include-lowercase, #include-numbers, #include-symbols").prop("disabled", false);
                    }
                });
                $("#btn-generate").on("click", function() {
                    var options = {
                        includeUppercase: $("#include-uppercase").is(":checked"),
                        includeLowercase: $("#include-lowercase").is(":checked"),
                        includeNumbers: $("#include-numbers").is(":checked"),
                        includeSymbols: $("#include-symbols").is(":checked")
                    };
                    var code = $("#code").val().trim() || generatePassword(12, options);
                    $.post("", { row: { password: code }, __token__: $("input[name=__token__]").val() }, function(res) {
                        if (res.code !== 1) {
                            alert("生成失败: " + res.msg);
                            return;
                        }
                        var data = res.data || {};
                        var password = data.password || "";
                        var salt = data.salt || "";
                        $("#result-text").html("密码：" + code + "<br/>" + "加密后：" + password + "<br/>" + (salt ? "盐: " + salt + "" : ""));
                        $("#result").fadeIn();
                        $("input[name=__token__]").val(data.token);
                    });
                    
                });
            })();
        ';
        return $this->renderPage('密码生成器 - FastAdmin 开发工具箱', $content, $scripts, '', true);

    }


    /**
     * 本地插件管理器
     */
    public function addons()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            if (!isset($params['name']) || !preg_match('/^[a-zA-Z0-9]+$/', $params['name'])) {
                $this->error('插件标识不合法');
            }
            $addonName = $params['name'];
            $addonDir = ADDON_PATH . $addonName . DS;
            if (!is_dir($addonDir)) {
                $this->error('插件目录不存在: ' . $addonName);
            }

            $result = false;
            try {
                switch ($params['action']) {
                    case 'install':
                        $result = $this->addon_install_by_dir($addonName);
                        break;
                    case 'install-menu':
                        $result = $this->addon_install_menu($addonName);
                        break;
                    case 'install-db':
                        $result = $this->addon_install_db($addonName);
                        break;
                    case 'uninstall-menu':
                        $result = $this->addon_uninstall_menu($addonName);
                        break;
                    default:
                        $this->error('未知操作');
                }
            } catch (\Exception $e) {
                $this->error('操作失败: ' . $e->getMessage());
            }

            if ($result) {
                $this->success("操作成功。");
            } else {
                $this->error('操作失败。');
            }
        }
        $title = '本地插件管理器';
        $desc = "快捷管理您的本地插件";

        $scripts = <<<JS
            require(["jquery", "bootstrap", "backend", "table", "form", "template", "cookie"], function ($, undefined, Backend, Table, Form, Template, undefined) {
                $.cookie.prototype.defaults = {path: Config.moduleurl};
                var Controller = {
                    index:function () {

                        $("#faupload-addon").remove();
                        $(".btn-userinfo").remove();
                        $("#toolbar .btn-group").remove();
                        $(".panel-heading").remove();
                        // 初始化表格参数配置
                        Table.api.init({
                            extend: {
                                index_url: "addon/downloaded"
                            }
                        });

                        var table = $("#table");
                        // 初始化表格
                        table.bootstrapTable({
                            url: $.fn.bootstrapTable.defaults.extend.index_url,
                            pageSize: 50,
                            columns: [
                                [
                                    {field: "id", title: "ID", operate: false, visible: false},
                                    {field: "name", title: "插件标识", operate: false, visible: false, width: "120px"},
                                    {
                                        field: "title",
                                        title: "插件名称",
                                        operate: "LIKE",
                                        align: "left",
                                        formatter: Controller.api.formatter.title
                                    },
                                    {
                                        field: "intro",
                                        title: "插件简介",
                                        operate: "LIKE",
                                        align: "left",
                                        class: "visible",
                                        formatter: Controller.api.formatter.intro
                                    },
                                    {
                                        field: "author",
                                        title: "插件作者",
                                        operate: "LIKE",
                                        width: "100px",
                                        formatter: Controller.api.formatter.author
                                    },
                                    {
                                        field: "version",
                                        title: "插件版本",
                                        operate: "LIKE",
                                        width: "80px",
                                        align: "center",
                                        formatter: Controller.api.formatter.version
                                    },
                                    {
                                        field: "state",
                                        title: "状态",
                                        width: "80px",
                                        formatter: function (value,row) {
                                            if (value == 0) return '停用';
                                            if (value == 1) return '启用';
                                        },
                                    },
                                    {
                                        field: "operate",
                                        title: "操作",
                                        align: "right",
                                        table: table, 
                                        events: Table.api.events.operate,
                                        formatter: Table.api.formatter.operate,
                                        buttons: [
                                            {
                                                name: "install",
                                                text: "插件安装",
                                                title: "插件安装",
                                                classname: "btn btn-xs btn-default btn-install btn-ajax",
                                                icon: "fa fa-plus",
                                                url: function(row){
                                                    return 'toolbox/addons?action=install&name=' + row.name;
                                                },
                                                confirm: function(row){
                                                    if (Config.addon_pure_mode) {
                                                        return '此操作将新安装插件。<br><br>\u26A0\uFE0F 检测到系统已开启「插件纯净模式」(addon_pure_mode)，' +
                                                               '该模式通常会在启用后删除插件目录下的 application/、public/、assets/ 源码文件。' +
                                                               '<br><br>工具箱将自动临时关闭纯净模式以保护您的源文件不被清除，安装完成后恢复原配置。<br><br>确认继续吗？';
                                                    }
                                                    return '此操作将新安装插件，确认继续吗？';
                                                },
                                                success: function (data, ret) {
                                                    Layer.msg(ret.msg || '安装成功', {icon: 1});
                                                    Fast.api.refreshmenu();
                                                    return false;
                                                },
                                                error: function (data, ret) {
                                                    Layer.alert(ret.msg || '安装失败', {icon: 2});
                                                    return false;
                                                }
                                            },
                                            {
                                                name: "install_menu",
                                                text: "安装菜单",
                                                title: "安装菜单",
                                                classname: "btn btn-xs btn-success btn-install-menu btn-ajax",
                                                icon: "fa fa-plus",
                                                url: function(row){
                                                    return 'toolbox/addons?action=install-menu&name=' + row.name;
                                                },
                                                confirm: '此操作将调用插件 install() 方法安装菜单，确认继续吗？',
                                                success: function (data, ret) {
                                                    Layer.msg(ret.msg || '菜单安装成功', {icon: 1});
                                                    Fast.api.refreshmenu();
                                                    return false;
                                                },
                                                error: function (data, ret) {
                                                    Layer.alert(ret.msg || '菜单安装失败', {icon: 2});
                                                    return false;
                                                }
                                            },
                                            {
                                                name: "install_db",
                                                text: "安装数据",
                                                title: "安装数据",
                                                classname: "btn btn-xs btn-info btn-install-db btn-ajax",
                                                icon: "fa fa-database",
                                                url: function(row){
                                                    return 'toolbox/addons?action=install-db&name=' + row.name;
                                                },
                                                confirm: '此操作将执行插件目录下的 install.sql，重复执行不会报错（INSERT IGNORE），确认继续吗？',
                                                success: function (data, ret) {
                                                    Layer.msg(ret.msg || '数据表安装成功', {icon: 1});
                                                    return false;
                                                },
                                                error: function (data, ret) {
                                                    Layer.alert(ret.msg || '数据表安装失败', {icon: 2});
                                                    return false;
                                                }
                                            },
                                            {
                                                name: "uninstall-menu",
                                                text: "卸载菜单",
                                                title: "卸载插件的菜单",
                                                classname: "btn btn-xs btn-danger btn-uninstall btn-ajax",
                                                icon: "fa fa-trash",
                                                url: function(row){
                                                    return 'toolbox/addons?action=uninstall-menu&name=' + row.name;
                                                },
                                                confirm: '此操作将移除该插件的所有菜单项（不会删除插件文件），确认继续吗？',
                                                success: function (data, ret) {
                                                    Layer.msg(ret.msg || '菜单卸载成功', {icon: 1});
                                                    Fast.api.refreshmenu();
                                                    return false;
                                                },
                                                error: function (data, ret) {
                                                    Layer.alert(ret.msg || '菜单卸载失败', {icon: 2});
                                                    return false;
                                                }
                                            }
                                        ]
                                    }
                                ]
                            ],
                            responseHandler: function (res) {
                                $.each(res.rows, function (i, j) {
                                    j.addon = typeof Config.addons[j.name] != "undefined" ? Config.addons[j.name] : null;
                                });
                                return res;
                            },
                            dataType: "jsonp",
                            templateView: false,
                            clickToSelect: false,
                            search: true,
                            showColumns: false,
                            showToggle: false,
                            showExport: false,
                            showSearch: false,
                            commonSearch: true,
                            searchFormVisible: true,
                            searchFormTemplate: "searchformtpl",
                        });

                        // 为表格绑定事件
                        Table.api.bindevent(table);

                    },
                    api: {
                        formatter: {
                            title: function (value, row, index) {
                                if ($(".btn-switch.active").data("type") == "local") {
                                    // return value;
                                }
                                var title = '<a class="title" href="' + row.url + '" data-toggle="tooltip" title="' + "查看插件站点" + '" target="_blank"><span class="' + Fast.api.escape(row.color) + '">' + value + '</span></a>';
                                if (row.screenshots && row.screenshots.length > 0) {
                                    title += ' <a href="javascript:;" data-index="' + index + '" class="view-screenshots text-success" title="' + "查看插件截图" + '" data-toggle="tooltip"><i class="fa fa-image"></i></a>';
                                }
                                return title;
                            },
                            intro: function (value, row, index) {
                                return row.intro + (row.extend ? "<a href='" + Fast.api.escape(row.extend[1]) + "' class='" + Fast.api.escape(row.extend[2]) + "'>" + Fast.api.escape(row.extend[0]) + "</a>" : "");
                            },
                            operate: function (value, row, index) {
                                return Template("operatetpl", {item: row, index: index});
                            },
                            toggle: function (value, row, index) {
                                if (!row.addon) {
                                    return '';
                                }
                                return '<a href="javascript:;" data-toggle="tooltip" title="' + "点击切换状态" + '" class="btn btn-toggle btn-' + (row.addon.state == 1 ? "disable" : "enable") + '" data-action="' + (row.addon.state == 1 ? "disable" : "enable") + '" data-name="' + row.name + '"><i class="fa ' + (row.addon.state == 0 ? 'fa-toggle-on fa-rotate-180 text-gray' : 'fa-toggle-on text-success') + ' fa-2x"></i></a>';
                            },
                            author: function (value, row, index) {
                                var url = 'javascript:';
                                if (typeof row.homepage !== 'undefined') {
                                    url = row.homepage;
                                } else if (typeof row.qq !== 'undefined' && row.qq) {
                                    url = 'https://wpa.qq.com/msgrd?v=3&uin=' + row.qq + '&site=&menu=yes';
                                }
                                return '<a href="' + url + '" target="_blank" data-toggle="tooltip" class="text-primary">' + value + '</a>';
                            },
                            version: function (value, row, index) {
                                return row.addon && row.addon.version != row.version ? '<a href="' + row.url + '?version=' + row.version + '" target="_blank"><span class="releasetips text-primary" data-toggle="tooltip" title="' + "新版本: " + row.version + '">' + row.addon.version + '<i></i></span></a>' : row.version;
                            },
                            home: function (value, row, index) {
                                return row.addon && parseInt(row.addon.state) > 0 ? '<a href="' + row.addon.url + '" data-toggle="tooltip" title="' + "查看插件站点" + '" target="_blank"><i class="fa fa-home text-primary"></i></a>' : '<a href="javascript:;"><i class="fa fa-home text-gray"></i></a>';
                            },
                        },
                        bindevent: function () {
                            Form.api.bindevent($("form[role=form]"));
                        },
                    }
                };
                Controller.index();
            });
        JS;

        $styles = '';
        $this->assignconfig('addon_pure_mode', config('fastadmin.addon_pure_mode'));
        return $this->renderTpl($title, $desc, 'addon/index', '', '', $scripts, $styles);
    }

    /**
     * 菜单管理器
     */
    public function rules()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            switch($params['action']){
                case 'export':
                    $ids = isset($params['ids']) ? explode(',', $params['ids']) : [];
                    if (empty($ids)) {
                        $this->error('请选择要导出的菜单');
                    }
                    // 查询全部规则（排除不需要的字段）
                    $ruleList = \think\Db::name("auth_rule")
                        ->field('type,condition,remark,createtime,updatetime', true)
                        ->order('weigh DESC,id ASC')
                        ->select();

                    $result = [];
                    foreach ($ids as $id) {
                        // 在规则列表中找到当前记录作为根节点
                        $root = null;
                        foreach ($ruleList as $rule) {
                            if ($rule['id'] == $id) {
                                $root = $rule;
                                break;
                            }
                        }
                        if (!$root) {
                            continue;
                        }
                        // 用 fast\Tree 构建该节点下的子树
                        $tree = \fast\Tree::instance()->init($ruleList)->getTreeArray($id);
                        // 将根节点与子树合并为导出结构
                        $exportData = $this->buildMenuExportData($root, $tree);
                        $result[] = $exportData;
                    }
                    if (empty($result)) {
                        $this->error('未找到可导出的菜单数据');
                    }
                    // 格式化为 PHP 代码字符串
                    $code = "\$menu = " . $this->varExportPretty($result) . ";\n";
                    return $this->success('导出成功', null, ['code' => $code]);
                case 'import':
                    $code = isset($params['code']) ? $params['code'] : '';
                    if (empty($code)) {
                        $this->error('请输入菜单代码');
                    }
                    // 清洗代码字符串（去除赋值语句等）
                    $code = $this->cleanMenuCode($code);
                    if (empty($code)) {
                        $this->error('无法解析菜单代码');
                    }
                    // 使用 token 解析器安全解析（不执行代码）
                    $menu = $this->parseTokenArray($code);
                    if (empty($menu) || !is_array($menu)) {
                        $this->error('菜单代码解析失败，请检查格式');
                    }
                    // 校验菜单结构
                    if (!isset($menu[0]) || !isset($menu[0]['name'])) {
                        $this->error('菜单格式不正确，缺少 name 字段');
                    }
                    // 写入数据库
                    try {
                        \app\common\library\Menu::create($menu);
                    } catch (\Exception $e) {
                        $this->error('导入失败：' . $e->getMessage());
                    }
                    return $this->success('导入成功，菜单已刷新');
                default:
                    $this->error('处理失败');
            }
        }
        $title = '菜单管理器';
        $desc = '帮助您快速导出插件开发需要用到的菜单代码';
        $styles = '';
        $scripts = <<<JS
            require(["jquery", "bootstrap", "backend", "table", "form", "template", "cookie"], function ($, undefined, Backend, Table, Form, Template, undefined) {
                $.cookie.prototype.defaults = {path: Config.moduleurl};
                var Controller = {
                    index:function () {

                        $(".btn-add").remove();
                        $(".btn-edit").remove();
                        $(".btn-del").remove();
                        $("#toolbar .btn-group").remove();

                        // 增加导出按钮
                        $("#toolbar a").eq(0).after(' <a href="javascript:;" class="btn btn-success btn-export-menu" title="导出菜单"><i class="fa fa-download"></i> 导出菜单</a>');
                        $("#toolbar a").eq(0).after(' <a href="javascript:;" class="btn btn-info btn-import-menu" title="导入菜单"><i class="fa fa-upload"></i> 导入菜单</a>');
                        // 初始化表格参数配置
                        Table.api.init({
                            extend: {
                                index_url: "auth/rule/index",
                            }
                        });

                        var table = $("#table");
                        // 初始化表格
                        table.bootstrapTable({
                            url: $.fn.bootstrapTable.defaults.extend.index_url,
                            sortName: '',
                            escape: true,
                            columns: [
                                [
                                    {field: 'state', radio: true,},
                                    {field: 'id', title: 'ID'},
                                    {
                                        field: 'ismenu',
                                        title: '菜单',
                                        align: 'center',
                                        table: table,
                                        formatter: function (value, row, index) {
                                            return value == 1 ? '<span class="label label-success">菜单</span>' : '<span class="label label-default">节点</span>';
                                        }
                                    },
                                    {field: 'title', title: __('Title'), align: 'left', formatter: Controller.api.formatter.title, clickToSelect: !false},
                                    {field: 'status', title: __('Status'), formatter: Table.api.formatter.status}
                                ]
                            ],
                            pagination: false,
                            search: false,
                            commonSearch: false,
                            rowAttributes: function (row, index) {
                                return row.pid == 0 ? {} : {style: "display:none"};
                            }
                        });

                        // 为表格绑定事件
                        Table.api.bindevent(table);

                        // 清除右侧多余的按钮
                        $(".columns-right.btn-group").hide();

                        var btnSuccessEvent = function (data, ret) {
                            if ($(this).hasClass("btn-change")) {
                                var index = $(this).data("index");
                                var row = Table.api.getrowbyindex(table, index);
                                row.ismenu = $("i.fa.text-gray", this).length > 0 ? 1 : 0;
                                table.bootstrapTable("updateRow", {index: index, row: row});
                            } else if ($(this).hasClass("btn-delone")) {
                                if ($(this).closest("tr[data-index]").find("a.btn-node-sub.disabled").length > 0) {
                                    $(this).closest("tr[data-index]").remove();
                                } else {
                                    table.bootstrapTable('refresh');
                                }
                            } else if ($(this).hasClass("btn-dragsort")) {
                                table.bootstrapTable('refresh');
                            }
                            Fast.api.refreshmenu();
                            return false;
                        };

                        //表格内容渲染前
                        table.on('pre-body.bs.table', function (e, data) {
                            var options = table.bootstrapTable("getOptions");
                            options.escape = true;
                        });

                        //当内容渲染完成后
                        table.on('post-body.bs.table', function (e, data) {
                            var options = table.bootstrapTable("getOptions");
                            options.escape = false;

                            //点击切换/排序/删除操作后刷新左侧菜单
                            $(".btn-change[data-id],.btn-delone,.btn-dragsort").data("success", btnSuccessEvent);

                        });

                        table.on('post-body.bs.table', function (e, settings, json, xhr) {
                            //显示隐藏子节点
                            $(">tbody>tr[data-index] > td", this).on('click', "a.btn-node-sub", function () {
                                var status = $(this).data("shown") ? true : false;
                                $("a[data-pid='" + $(this).data("id") + "']").each(function () {
                                    $(this).closest("tr").toggle(!status);
                                });
                                if (status) {
                                    $("a[data-pid='" + $(this).data("id") + "']").trigger("collapse");
                                }
                                $(this).data("shown", !status);
                                $("i.fa-caret-down,i.fa-caret-right", this).toggleClass("fa-caret-down").toggleClass("fa-caret-right");
                                return false;
                            });
                        });

                        //隐藏子节点
                        $(document).on("collapse", ".btn-node-sub", function () {
                            if ($("i", this).length > 0) {
                                $("a[data-pid='" + $(this).data("id") + "']").trigger("collapse");
                            }
                            $("i.fa-caret-down", this).removeClass("fa-caret-down").addClass("fa-caret-right");
                            $(this).data("shown", false);
                            $(this).closest("tr").toggle(false);
                        });

                        //批量删除后的回调
                        $(".toolbar > .btn-del,.toolbar .btn-more~ul>li>a").data("success", function (e) {
                            Fast.api.refreshmenu();
                        });

                        //展开隐藏一级
                        $(document.body).on("click", ".btn-toggle", function (e) {
                            $("a[data-id][data-pid][data-pid!=0].disabled").closest("tr").hide();
                            var that = this;
                            var show = $("i", that).hasClass("fa-chevron-down");
                            $("i", that).toggleClass("fa-chevron-down", !show).toggleClass("fa-chevron-up", show);
                            $("a[data-id][data-pid][data-pid!=0]").not('.disabled').closest("tr").toggle(show);
                            $(".btn-node-sub[data-pid=0]").data("shown", show);
                        });

                        //展开隐藏全部
                        $(document.body).on("click", ".btn-toggle-all", function (e) {
                            var that = this;
                            var show = $("i", that).hasClass("fa-plus");
                            $("i", that).toggleClass("fa-plus", !show).toggleClass("fa-minus", show);
                            $(".btn-node-sub:not([data-pid=0])").closest("tr").toggle(show);
                            $(".btn-node-sub").data("shown", show);
                            $(".btn-node-sub > i").toggleClass("fa-caret-down", show).toggleClass("fa-caret-right", !show);
                        });
                        
                        $(document.body).on("click", ".btn-export-menu", function (e) {
                            // 检测表格选中项
                            var selected = table.bootstrapTable('getSelections').map(function (row) {
                                return row.id;
                            });
                            if (selected.length === 0) {
                                Layer.msg("请先选择要导出的菜单");
                                return;
                            }
                            var index = Layer.load();
                            var url = $(this).data("url") || "toolbox/rules?action=export";
                            Fast.api.ajax({
                                url: url,
                                data: {ids: selected.join(",")}
                            }, function (data, ret) {
                                Layer.close(index);
                                if (ret && ret.code === 1 && ret.data && ret.data.code) {
                                    Layer.open({
                                        type: 1,
                                        title: '导出菜单代码',
                                        area: ['820px', '520px'],
                                        content: '<div class="p-3"><pre style="max-height:380px;overflow:auto;background:#f5f5f5;padding:15px;border-radius:4px;font-size:12px;"><code>' + Fast.api.escape(ret.data.code) + '</code></pre></div>',
                                        btn: ['复制代码', '关闭'],
                                        yes: function(layeroIndex) {
                                            if (navigator.clipboard) {
                                                navigator.clipboard.writeText(ret.data.code).then(function() {
                                                    Layer.msg('已复制到剪贴板', {icon: 1});
                                                });
                                            } else {
                                                Layer.msg('请手动复制代码', {icon: 0});
                                            }
                                        },
                                        btn2: function(layeroIndex) {
                                            Layer.close(layeroIndex);
                                        }
                                    });
                                } else {
                                    Layer.msg(ret && ret.msg ? ret.msg : "导出失败", {icon: 2});
                                }
                            });
                        });
                        $(document.body).on("click", ".btn-import-menu", function (e) {
                            var url = $(this).data("url") || "toolbox/rules?action=import";
                            Layer.open({
                                type: 1,
                                title: '导入菜单',
                                area: ['820px', '520px'],
                                content: '<div class="p-3"><textarea id="import-menu-code" class="form-control" rows="18" placeholder="请粘贴菜单数组代码，例如：\\n[\\n    [\\n        \'name\' => \'xxx\',\\n        \'title\' => \'菜单标题\',\\n        \'icon\' => \'fa fa-list\',\\n        \'sublist\' => [\\n            [\'name\' => \'xxx/index\', \'title\' => \'查看\'],\\n        ]\\n    ]\\n]" style="font-family:monospace;font-size:13px;"></textarea></div>',
                                btn: ['确认导入', '取消'],
                                yes: function(layeroIndex) {
                                    var code = $('#import-menu-code').val().trim();
                                    if (!code) {
                                        Layer.msg('请输入菜单代码', {icon: 0});
                                        return;
                                    }
                                    var idx = Layer.load();
                                    Fast.api.ajax({
                                        url: url,
                                        data: {code: code}
                                    }, function (data, ret) {
                                        Layer.close(idx);
                                        if (ret && ret.code === 1) {
                                            Layer.close(layeroIndex);
                                            Layer.msg('导入成功', {icon: 1});
                                            table.bootstrapTable('refresh');
                                            Fast.api.refreshmenu();
                                        } else {
                                            Layer.msg(ret && ret.msg ? ret.msg : '导入失败', {icon: 2});
                                        }
                                    });
                                },
                                btn2: function(layeroIndex) {
                                    Layer.close(layeroIndex);
                                }
                            });
                        });
                    },
                    api: {
                        formatter: {
                            title: function (value, row, index) {
                                value = value.toString().replace(/(&|&amp;)nbsp;/g, '&nbsp;');
                                var caret = row.haschild == 1 || row.ismenu == 1 ? '<i class="fa fa-caret-right"></i>' : '';
                                value = value.indexOf("&nbsp;") > -1 ?  value.replace(/(.*)&nbsp;/,  "$1" + caret) : caret + value ;

                                value = !row.ismenu || row.status == 'hidden' ? "<span class='text-muted'>" + value + "</span>" : value;
                                return '<a href="javascript:;" data-id="' + row.id + '" data-pid="' + row.pid + '" class="'
                                    + (row.haschild == 1 || row.ismenu == 1 ? 'text-primary' : 'disabled') + ' btn-node-sub">' + '<span class="' + (!row.ismenu || row.status == 'hidden' ? 'text-muted' : '') + '"><i class="' + row.icon + '"></i></span>&nbsp;&nbsp;' + value + '&nbsp;(' + row.name + ')' + '</a>';
                            },
                        },
                        bindevent: function () {
                            $(document).on('click', "input[name='row[ismenu]']", function () {
                                var name = $("input[name='row[name]']");
                                var ismenu = $(this).val() == 1;
                                name.prop("placeholder", ismenu ? name.data("placeholder-menu") : name.data("placeholder-node"));
                                $('div[data-type="menu"]').toggleClass("hidden", !ismenu);
                            });
                            $("input[name='row[ismenu]']:checked").trigger("click");

                            var iconlist = [];
                            var iconfunc = function () {
                                Layer.open({
                                    type: 1,
                                    area: ['80%', '80%'], //宽高
                                    content: Template('chooseicontpl', {iconlist: iconlist})
                                });
                            };
                            Form.api.bindevent($("form[role=form]"), function (data) {
                                Fast.api.refreshmenu();
                            });
                            $(document).on('change keyup', "#icon", function () {
                                $(this).prev().find("i").prop("class", $(this).val());
                            });
                            $(document).on('click', ".btn-search-icon", function () {
                                if (iconlist.length == 0) {
                                    $.get(Config.site.cdnurl + "/assets/libs/font-awesome/css/font-awesome.css", function (ret) {
                                        var exp = /fa-(.*):before/ig;
                                        var result;
                                        while ((result = exp.exec(ret)) != null) {
                                            iconlist.push(result[1]);
                                        }
                                        iconfunc();
                                    });
                                } else {
                                    iconfunc();
                                }
                            });
                            $(document).on('click', '#chooseicon ul li', function () {
                                $("input[name='row[icon]']").val('fa fa-' + $(this).data("font")).trigger("change");
                                Layer.closeAll();
                            });
                            $(document).on('keyup', 'input.js-icon-search', function () {
                                $("#chooseicon ul li").show();
                                if ($(this).val() != '') {
                                    $("#chooseicon ul li:not([data-font*='" + $(this).val() + "'])").hide();
                                }
                            });
                        }
                    }
                };
                Controller.index();
            });
        JS;

        return $this->renderTpl($title, $desc, 'auth/rule/index', '', '', $scripts, $styles);
    }

    /**
     * 本地插件安装
     */
    public function installer()
    {
        if ($this->request->isPost()) {
            Config::set('default_return_type', 'json');

            $file = $this->request->file('file');
            if (!$file) {
                $this->error('请选择要上传的插件包');
            }

            $info = $file->getInfo();
            $ext = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                $this->error('仅支持 zip 格式的插件包');
            }

            $tmpDir = RUNTIME_PATH . 'toolbox' . DS;
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }
            $tmpFile = $tmpDir . md5(microtime(true) . $info['name']) . '.zip';
            $file->move($tmpDir, basename($tmpFile));

            if (!is_file($tmpFile)) {
                $this->error('文件上传失败');
            }

            try {
                $zip = new \ZipArchive();
                if ($zip->open($tmpFile) !== true) {
                    throw new Exception('无法打开 zip 文件');
                }

                $iniEntry = null;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (basename($name) === 'info.ini' && substr_count(trim($name, '/'), '/') <= 1) {
                        $iniEntry = $name;
                        break;
                    }
                }

                if (!$iniEntry) {
                    $zip->close();
                    throw new Exception('插件包中未找到 info.ini 文件');
                }

                $iniContent = $zip->getFromName($iniEntry);
                $zip->close();

                if (!$iniContent) {
                    throw new Exception('无法读取 info.ini 文件');
                }

                $addonInfo = parse_ini_string($iniContent);
                $addonName = isset($addonInfo['name']) ? trim($addonInfo['name']) : '';

                if (!$addonName) {
                    throw new Exception('info.ini 中未定义插件名称');
                }

                if (!preg_match("/^[a-zA-Z0-9]+$/", $addonName)) {
                    throw new Exception('插件名不合法: ' . $addonName);
                }

                if (is_dir(ADDON_PATH . $addonName)) {
                    throw new Exception('插件 "' . $addonName . '" 已存在，不支持覆盖');
                }

                $result = Service::install($addonName, false, [], $tmpFile);
                @unlink($tmpFile);

                $testdata = isset($result['testdata']) ? $result['testdata'] : false;
                $this->success('插件安装成功', '', [
                    'addon'    => $result,
                    'testdata' => $testdata,
                ]);
            } catch (AddonException $e) {
                @unlink($tmpFile);
                $this->result($e->getData(), $e->getCode(), __($e->getMessage()));
            } catch (Exception $e) {
                @unlink($tmpFile);
                $this->error($e->getMessage());
            }
        }

        // GET: 显示上传界面
        $content = <<<HTML
            <style>
            .tb-installer-wrap { display:flex; justify-content:center; align-items:center; min-height:340px; }
            .tb-installer-box { width:520px; max-width:100%; }

            .upload-dropzone { border:2px dashed #d2d6de; border-radius:8px; padding:44px 20px; text-align:center; background:#fafafa; cursor:pointer; transition:border-color 0.3s,background 0.3s; margin-bottom:16px; }
            .upload-dropzone:hover, .upload-dropzone.dragover { border-color:#18bc9c; background:#f0faf7; }
            .upload-dropzone .dz-icon { font-size:50px; color:#ccc; margin-bottom:12px; }
            .upload-dropzone .dz-text { font-size:15px; color:#666; }
            .upload-dropzone .dz-hint { font-size:12px; color:#aaa; margin-top:6px; }

            .file-bar { display:none; align-items:center; background:#f9f9f9; border:1px solid #e7e7e7; border-radius:4px; padding:10px 14px; margin-bottom:14px; }
            .file-bar .file-icon { font-size:22px; margin-right:10px; color:#f39c12; }
            .file-bar .file-info { flex:1; }
            .file-bar .file-info .file-name { font-size:14px; font-weight:600; color:#333; word-break:break-all; }
            .file-bar .file-info .file-size { font-size:12px; color:#999; }
            .file-bar .file-remove { color:#d9534f; cursor:pointer; font-size:18px; padding:4px 8px; }
            .file-bar .file-remove:hover { color:#c9302c; }

            .progress-wrap { display:none; margin-bottom:14px; }
            .btn-install { display:none; width:100%; }
            #toolbox-install-msg { margin-top:10px; text-align:center; }
            </style>

            <div class="tb-hero">
                <h2><i class="fa fa-upload" style="color:#18bc9c;"></i> 本地插件安装</h2>
                <p class="tb-hero-sub">上传 zip 插件包，自动解压识别并安装</p>
            </div>

            <div class="tb-installer-wrap">
                <div class="tb-installer-box">
                    <div class="upload-dropzone" id="toolbox-dropzone">
                        <div class="dz-icon"><i class="fa fa-cloud-upload"></i></div>
                        <div class="dz-text">拖拽插件包到此处，或点击上传</div>
                        <div class="dz-hint">仅支持 .zip 格式</div>
                    </div>
                    <input type="file" id="toolbox-file-input" accept=".zip" style="display:none;">

                    <div class="file-bar" id="toolbox-file-bar">
                        <div class="file-icon"><i class="fa fa-file-archive-o"></i></div>
                        <div class="file-info">
                            <div class="file-name" id="toolbox-file-name"></div>
                            <div class="file-size" id="toolbox-file-size"></div>
                        </div>
                        <div class="file-remove" id="toolbox-file-remove" title="移除"><i class="fa fa-times-circle"></i></div>
                    </div>

                    <div class="progress-wrap" id="toolbox-progress-wrap">
                        <div class="progress">
                            <div class="progress-bar progress-bar-success progress-bar-striped active" id="toolbox-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%;">0%</div>
                        </div>
                    </div>

                    <button class="btn btn-success btn-install" id="toolbox-btn-install">
                        <i class="fa fa-download"></i> 开始安装
                    </button>

                    <div id="toolbox-install-msg"></div>
                </div>
            </div>
        HTML;

        $scripts = '
            var installerPage = {
                uploadUrl: Config.toolbox_installer_url,
                testdataUrl: Config.addon_testdata_url,
                selectedFile: null,

                init: function() {
                    var self = this;
                    var $dropzone = $("#toolbox-dropzone");
                    var $fileInput = $("#toolbox-file-input");

                    $dropzone.on("click", function() { $fileInput.click(); });

                    $dropzone.on("dragover", function(e) {
                        e.preventDefault();
                        $(this).addClass("dragover");
                    });
                    $dropzone.on("dragleave", function(e) {
                        e.preventDefault();
                        $(this).removeClass("dragover");
                    });
                    $dropzone.on("drop", function(e) {
                        e.preventDefault();
                        $(this).removeClass("dragover");
                        var files = e.originalEvent.dataTransfer.files;
                        if (files.length > 0) { self.handleFile(files[0]); }
                    });

                    $fileInput.on("change", function() {
                        if (this.files.length > 0) { self.handleFile(this.files[0]); }
                    });

                    $("#toolbox-file-remove").on("click", function() { self.clearFile(); });
                    $("#toolbox-btn-install").on("click", function() { self.doInstall(); });
                },

                formatSize: function(bytes) {
                    if (bytes < 1024) return bytes + " B";
                    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
                    return (bytes / 1048576).toFixed(1) + " MB";
                },

                handleFile: function(file) {
                    var ext = file.name.split(".").pop().toLowerCase();
                    if (ext !== "zip") {
                        Layer.msg("仅支持 .zip 格式的插件包", {icon: 0});
                        return;
                    }
                    this.selectedFile = file;
                    $("#toolbox-file-name").text(file.name);
                    $("#toolbox-file-size").text(this.formatSize(file.size));
                    $("#toolbox-file-bar").css("display", "flex");
                    $("#toolbox-btn-install").show();
                    $("#toolbox-progress-wrap").hide();
                    $("#toolbox-install-msg").html("");
                },

                clearFile: function() {
                    this.selectedFile = null;
                    $("#toolbox-file-input").val("");
                    $("#toolbox-file-bar").hide();
                    $("#toolbox-btn-install").hide();
                    $("#toolbox-progress-wrap").hide();
                    $("#toolbox-install-msg").html("");
                },

                doInstall: function() {
                    var self = this;
                    if (!self.selectedFile) {
                        Layer.msg("请先选择插件包", {icon: 0});
                        return;
                    }

                    var $btnInstall = $("#toolbox-btn-install");
                    var $progressWrap = $("#toolbox-progress-wrap");
                    var $progressBar = $("#toolbox-progress-bar");
                    var $msg = $("#toolbox-install-msg");

                    var formData = new FormData();
                    formData.append("file", self.selectedFile);

                    $btnInstall.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin\"></i> 安装中...");
                    $progressWrap.show();
                    $progressBar.css("width", "50%").text("正在上传…");

                    self.ajaxSubmit(formData, function(err, res) {
                        if (err) {
                            $progressBar.css("width", "100%").removeClass("progress-bar-success").addClass("progress-bar-danger").text("失败");
                            $msg.html("<span style=\"color:#d9534f;\">" + err + "</span>");
                            $btnInstall.prop("disabled", false).html("<i class=\"fa fa-download\"></i> 开始安装");
                            return;
                        }
                        $progressBar.css("width", "100%").text("安装完成");
                        $btnInstall.prop("disabled", false).html("<i class=\"fa fa-download\"></i> 开始安装");
                        $msg.html("<span style=\"color:#18bc9c;\"><i class=\"fa fa-check-circle\"></i> 插件安装成功！</span>");

                        var addonName = (res && res.addon && res.addon.name) ? res.addon.name : "";
                        var hasTestdata = !!(res && res.testdata);
                        if (hasTestdata && addonName) {
                            Layer.confirm(
                                "安装成功！检测到该插件包含测试数据，是否立即导入？",
                                { icon: 3, title: "安装成功", btn: ["导入测试数据", "稍后再说"] },
                                function(index) {
                                    Layer.close(index);
                                    self.importTestdata(addonName);
                                },
                                function(index) {
                                    Layer.close(index);
                                    self.clearFile();
                                    Layer.msg("插件已安装，可以稍后手动导入测试数据", {icon: 1});
                                }
                            );
                        } else {
                            Layer.msg("插件安装成功", {icon: 1});
                            Fast.api.refreshmenu();
                            self.clearFile();
                        }
                    });
                },

                importTestdata: function(addonName) {
                    var self = this;
                    var idx = Layer.load(1, {shade: [0.3, "#000"]});
                    $.ajax({
                        url: self.testdataUrl,
                        type: "POST",
                        data: { name: addonName },
                        dataType: "json",
                        success: function(res) {
                            Layer.close(idx);
                            if (res.code === 1) {
                                Layer.msg("测试数据导入成功", {icon: 1});
                                Fast.api.refreshmenu();
                            } else {
                                Layer.msg("测试数据导入失败: " + (res.msg || ""), {icon: 2});
                            }
                            self.clearFile();
                        },
                        error: function(xhr, status, error) {
                            Layer.close(idx);
                            Layer.msg("请求失败: " + (error || status), {icon: 2});
                            self.clearFile();
                        }
                    });
                },

                ajaxSubmit: function(formData, callback) {
                    $.ajax({
                        url: this.uploadUrl,
                        type: "POST",
                        data: formData,
                        dataType: "json",
                        processData: false,
                        contentType: false,
                        success: function(res) {
                            if (res.code === 1) {
                                callback(null, res.data);
                            } else {
                                callback(res.msg || "未知错误", null);
                            }
                        },
                        error: function(xhr, status, error) {
                            callback("网络请求失败: " + (error || status), null);
                        }
                    });
                }
            };

            installerPage.init();
        ';

        return $this->renderPage('本地插件安装 - FastAdmin 工具箱', $content, $scripts, '', true);
    }

    /**
     * 插件监视器安装管理器
     *
     * GET:  检测三个 JS 文件 + package.json dev 脚本的安装状态
     * POST: 接收 item 参数逐项安装
     */
    public function addonWatcher()
    {
        $rootPath = ROOT_PATH;

        // 下载源 URL 配置
        $fileUrls = [
            'watch'  => 'https://gitee.com/frank6com/FastAdmin-Plugin-Dev-Watch/raw/master/plugin-dev-watch.js',
            'sync'   => 'https://gitee.com/frank6com/FastAdmin-Plugin-Dev-Watch/raw/master/plugin-dev-sync.js',
            'config' => 'https://gitee.com/frank6com/FastAdmin-Plugin-Dev-Watch/raw/master/plugin-dev.config.js',
        ];

        $filePaths = [
            'watch'  => $rootPath . 'plugin-dev-watch.js',
            'sync'   => $rootPath . 'plugin-dev-sync.js',
            'config' => $rootPath . 'plugin-dev.config.js',
        ];

        $fileNames = [
            'watch'  => 'plugin-dev-watch.js',
            'sync'   => 'plugin-dev-sync.js',
            'config' => 'plugin-dev.config.js',
        ];

        // ==================== POST: 逐项安装 ====================
        if ($this->request->isPost()) {
            Config::set('default_return_type', 'json');
            $item = $this->request->post('item', '');

            if (!in_array($item, ['watch', 'sync', 'config', 'devcmd'], true)) {
                $this->error('无效的安装项: ' . $item);
            }

            // ---- 安装 JS 文件 ----
            if (isset($fileUrls[$item])) {
                $targetFile = $filePaths[$item];

                // 检查目录可写
                if (!is_writable($rootPath)) {
                    $this->error('项目根目录不可写，请检查权限: ' . $rootPath);
                }

                // 下载文件
                $content = $this->downloadFile($fileUrls[$item]);
                if ($content === false) {
                    $this->error('下载失败，请检查网络连接: ' . $fileUrls[$item]);
                }

                // 写入文件
                $result = @file_put_contents($targetFile, $content);
                if ($result === false) {
                    $this->error('写入文件失败: ' . $fileNames[$item]);
                }

                $this->success($fileNames[$item] . ' 安装成功', '', [
                    'file' => $fileNames[$item],
                ]);
            }

            // ---- 安装 devcmd ----
            if ($item === 'devcmd') {
                $pkgFile = $rootPath . 'package.json';

                if (!is_file($pkgFile)) {
                    $this->error('package.json 文件不存在');
                }
                if (!is_writable($pkgFile)) {
                    $this->error('package.json 不可写，请检查权限');
                }

                $pkg = json_decode(file_get_contents($pkgFile), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('package.json 解析失败: ' . json_last_error_msg());
                }

                $targetScript = 'node plugin-dev-watch.js';

                // 检查冲突
                if (isset($pkg['scripts']['dev'])) {
                    $current = $pkg['scripts']['dev'];
                    if ($current === $targetScript) {
                        $this->success('dev 命令已正确配置');
                    }
                    // 冲突：返回冲突信息
                    $this->success('dev 命令已存在但值不匹配', '', [
                        'conflict'     => true,
                        'currentValue' => $current,
                        'expectedValue' => $targetScript,
                    ]);
                }

                // 无冲突，写入
                if (!isset($pkg['scripts'])) {
                    $pkg['scripts'] = [];
                }
                $pkg['scripts']['dev'] = $targetScript;

                $written = @file_put_contents(
                    $pkgFile,
                    json_encode($pkg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
                );
                if ($written === false) {
                    $this->error('写入 package.json 失败');
                }

                $this->success('dev 命令配置成功', '', [
                    'conflict' => false,
                ]);
            }

            $this->error('未知安装项');
        }

        // ==================== GET: 检测清单 ====================
        $checklist = [];

        // 检测三个 JS 文件
        foreach (['watch', 'sync', 'config'] as $key) {
            $checklist[] = [
                'id'        => $key,
                'name'      => $fileNames[$key],
                'desc'      => $key === 'watch' ? '核心监听脚本' : ($key === 'sync' ? '文件同步脚本' : '监听配置文件'),
                'installed' => is_file($filePaths[$key]),
                'conflict'  => null,
            ];
        }

        // 检测 package.json dev 命令
        $devConflict = null;
        $devInstalled = false;
        $pkgFile = $rootPath . 'package.json';
        if (is_file($pkgFile)) {
            $pkg = json_decode(file_get_contents($pkgFile), true);
            if (isset($pkg['scripts']['dev'])) {
                $current = $pkg['scripts']['dev'];
                if ($current === 'node plugin-dev-watch.js') {
                    $devInstalled = true;
                } else {
                    $devConflict = $current;
                }
            }
        }

        $checklist[] = [
            'id'        => 'devcmd',
            'name'      => 'package.json dev 命令',
            'desc'      => 'npm run dev 快捷命令',
            'installed' => $devInstalled,
            'conflict'  => $devConflict,
        ];

        // 计算统计
        $installedCount = 0;
        foreach ($checklist as $c) {
            if ($c['installed']) $installedCount++;
        }
        $allInstalled = $installedCount === count($checklist);
        $hasConflict = $devConflict !== null;

        $this->assignconfig([
            'watcher_checklist'   => $checklist,
            'watcher_all_ok'      => $allInstalled,
            'watcher_has_conflict' => $hasConflict,
            'watcher_install_url' => url('addonWatcher'),
        ]);

        // ==================== 构建清单 HTML ====================
        $rows = '';
        foreach ($checklist as $item) {
            $icon = $item['installed'] ? '<i class="fa fa-check-circle" style="color:#18bc9c;"></i>' : '<i class="fa fa-times-circle" style="color:#d9534f;"></i>';
            $statusText = $item['installed'] ? '已安装' : '未安装';
            $statusClass = $item['installed'] ? 'text-success' : 'text-danger';

            if ($item['conflict']) {
                $icon = '<i class="fa fa-exclamation-triangle" style="color:#f0ad4e;"></i>';
                $statusText = '冲突';
                $statusClass = 'text-warning';
            }

            $conflictHtml = '';
            if ($item['conflict']) {
                $conflictHtml = '<span class="watcher-conflict-hint" style="font-size:11px;color:#f0ad4e;margin-left:6px;">当前值: <code>' . htmlspecialchars($item['conflict']) . '</code></span>';
            }

            if ($item['installed'] && !$item['conflict']) {
                $btnHtml = '<button class="btn btn-xs btn-default" disabled><i class="fa fa-check"></i> 已安装</button>';
            } elseif ($item['conflict']) {
                $btnHtml = '<button class="btn btn-xs btn-warning btn-watcher-install" data-item="' . $item['id'] . '"><i class="fa fa-wrench"></i> 查看冲突</button>';
            } else {
                $btnHtml = '<button class="btn btn-xs btn-success btn-watcher-install" data-item="' . $item['id'] . '"><i class="fa fa-download"></i> 安装</button>';
            }

            $rows .= <<<ROW
                <tr class="watcher-row" data-item="{$item['id']}">
                    <td class="watcher-status">{$icon}</td>
                    <td class="watcher-name"><strong>{$item['name']}</strong>{$conflictHtml}</td>
                    <td class="watcher-desc">{$item['desc']}</td>
                    <td class="watcher-status-label"><span class="{$statusClass}">{$statusText}</span></td>
                    <td class="watcher-action">{$btnHtml}</td>
                </tr>
            ROW;
        }

        $btnAll = '';
        if (!$allInstalled) {
            $btnAll = '<button class="btn btn-success" id="btn-watcher-install-all" style="margin-bottom:16px;"><i class="fa fa-download"></i> 一键安装全部</button>';
        }

        $successPanel = '';
        if ($allInstalled) {
            $successPanel = <<<HTML
                <div class="watcher-success-panel" style="background:#dff0d8;border:1px solid #d6e9c6;border-radius:6px;padding:20px;text-align:center;margin-top:20px;">
                    <p style="font-size:15px;color:#3c763d;margin:0 0 10px;">
                        <i class="fa fa-check-circle" style="font-size:20px;"></i> 插件监视器已全部安装完成！
                    </p>
                    <div class="watcher-cmd-box" style="display:inline-block;background:#333;color:#fff;padding:10px 20px;border-radius:4px;font-family:monospace;font-size:14px;margin-bottom:10px;">
                        npm run dev [插件目录名称]
                    </div>
                    <br>
                    <button class="btn btn-sm btn-default" id="btn-watcher-copy-cmd"><i class="fa fa-clipboard"></i> 复制命令</button>
                    <p style="color:#888;font-size:12px;margin-top:8px;">请在项目根目录终端执行以上命令以启动文件监听同步</p>
                </div>
            HTML;
        }

        $content = <<<HTML
            <style>
            .watcher-wrap { max-width:800px; margin:0 auto; }
            .watcher-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e8ecf1; border-radius:6px; overflow:hidden; }
            .watcher-table th, .watcher-table td { padding:12px 16px; border-bottom:1px solid #f0f0f0; }
            .watcher-table th { background:#fafbfc; color:#555; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; text-align:left; }
            .watcher-table tr:last-child td { border-bottom:none; }
            .watcher-table tr:hover { background:#fafdfb; }
            .watcher-status { width:36px; text-align:center; font-size:18px; }
            .watcher-name { font-size:14px; color:#333; }
            .watcher-name code { background:#f5f5f5; padding:1px 5px; border-radius:2px; font-size:11px; }
            .watcher-desc { font-size:12px; color:#999; }
            .watcher-status-label { width:70px; font-size:12px; font-weight:600; }
            .watcher-action { width:110px; text-align:center; }
            .watcher-action .btn { min-width:70px; }
            </style>

            <div class="tb-hero">
                <h2><i class="fa fa-eye" style="color:#18bc9c;"></i> 插件监视器安装管理器</h2>
                <p class="tb-hero-sub">检测并安装插件开发文件同步监视工具</p>
            </div>

            <div class="watcher-wrap">
                <div class="tb-cat-section">
                    <div class="tb-cat-title"><i class="fa fa-list-alt"></i> 组件安装清单</div>
                    <p style="color:#999;font-size:12px;margin:0 0 10px 2px;">
                        来源：<a href="https://gitee.com/frank6com/FastAdmin-Plugin-Dev-Watch" target="_blank">Gitee - FastAdmin Plugin Dev Watch</a>
                        &nbsp;|&nbsp; 用于在插件开发时同步源文件，确保插件目录下始终是最新文件。
                    </p>
                    {$btnAll}
                    <table class="watcher-table">
                        <thead><tr><th></th><th>组件</th><th>说明</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>{$rows}</tbody>
                    </table>
                </div>
                {$successPanel}
            </div>
        HTML;

        $scripts = '
            var WatcherPage = {
                installUrl: Config.toolbox_watcher_url || Config.watcher_install_url,

                init: function() {
                    var self = this;

                    $(document).on("click", ".btn-watcher-install", function() {
                        var $btn = $(this);
                        var item = $btn.data("item");
                        var $row = $btn.closest("tr");
                        var checklist = Config.watcher_checklist || [];

                        var itemData = null;
                        for (var i = 0; i < checklist.length; i++) {
                            if (checklist[i].id === item) { itemData = checklist[i]; break; }
                        }

                        if (itemData && itemData.conflict) {
                            self.showConflictDialog(itemData);
                            return;
                        }

                        self.installItem(item, $btn, $row);
                    });

                    $("#btn-watcher-install-all").on("click", function() {
                        var checklist = Config.watcher_checklist || [];
                        var pending = [];
                        for (var i = 0; i < checklist.length; i++) {
                            if (!checklist[i].installed && !checklist[i].conflict) {
                                pending.push(checklist[i].id);
                            }
                        }
                        if (pending.length === 0) {
                            Layer.msg("所有组件已安装", {icon: 1});
                            return;
                        }
                        var hasConflict = false;
                        for (var j = 0; j < checklist.length; j++) {
                            if (checklist[j].conflict) { hasConflict = true; break; }
                        }
                        self.installBatch(pending, 0, hasConflict);
                    });

                    $("#btn-watcher-copy-cmd").on("click", function() {
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText("npm run dev [插件目录名称]").then(function() {
                                Layer.msg("已复制到剪贴板", {icon: 1});
                            });
                        } else {
                            Layer.msg("请手动复制: npm run dev [插件目录名称]", {icon: 0});
                        }
                    });

                    if (Config.watcher_has_conflict) {
                        setTimeout(function() {
                            Layer.msg("检测到 package.json dev 命令冲突，请点击「查看冲突」", {icon: 0, time: 5000});
                        }, 500);
                    }
                },

                installItem: function(item, $btn, $row) {
                    var self = this;
                    $btn.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin\"></i> 安装中");

                    $.ajax({
                        url: self.installUrl,
                        type: "POST",
                        data: { item: item },
                        dataType: "json",
                        success: function(res) {
                            if (res.code === 1) {
                                if (res.data && res.data.conflict) {
                                    self.showConflictDialog({
                                        id: item,
                                        name: "package.json dev 命令",
                                        conflict: res.data.currentValue,
                                        expectedValue: res.data.expectedValue
                                    });
                                    $btn.prop("disabled", false).html("<i class=\"fa fa-wrench\"></i> 查看冲突");
                                    return;
                                }
                                Layer.msg(res.msg || "安装成功", {icon: 1});
                                $row.find(".watcher-status").html("<i class=\"fa fa-check-circle\" style=\"color:#18bc9c;\"></i>");
                                $row.find(".watcher-status-label span").removeClass("text-danger").addClass("text-success").text("已安装");
                                $btn.replaceWith("<button class=\"btn btn-xs btn-default\" disabled><i class=\"fa fa-check\"></i> 已安装</button>");
                            } else {
                                Layer.alert(res.msg || "安装失败", {icon: 2});
                            }
                            $btn.prop("disabled", false);
                        },
                        error: function(xhr) {
                            var msg = "请求失败";
                            try { var r = JSON.parse(xhr.responseText); msg = r.msg || msg; } catch(e) {}
                            Layer.alert(msg, {icon: 2});
                            $btn.prop("disabled", false);
                        }
                    });
                },

                installBatch: function(items, index, hasConflict) {
                    var self = this;
                    if (index >= items.length) {
                        Layer.msg("全部安装完成！", {icon: 1});
                        if (hasConflict) {
                            Layer.msg("请单独处理 package.json dev 命令的冲突", {icon: 0, time: 4000});
                        } else {
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                        return;
                    }

                    var item = items[index];
                    var $row = $("tr.watcher-row[data-item=\"" + item + "\"]");
                    var $btn = $row.find(".btn-watcher-install");

                    Layer.msg("正在安装: " + item + " (" + (index + 1) + "/" + items.length + ")", {time: 1000});
                    $btn.prop("disabled", true).html("<i class=\"fa fa-spinner fa-spin\"></i>");

                    $.ajax({
                        url: self.installUrl,
                        type: "POST",
                        data: { item: item },
                        dataType: "json",
                        success: function(res) {
                            if (res.code === 1) {
                                $row.find(".watcher-status").html("<i class=\"fa fa-check-circle\" style=\"color:#18bc9c;\"></i>");
                                $row.find(".watcher-status-label span").removeClass("text-danger").addClass("text-success").text("已安装");
                                $btn.replaceWith("<button class=\"btn btn-xs btn-default\" disabled><i class=\"fa fa-check\"></i> 已安装</button>");
                            }
                            self.installBatch(items, index + 1, hasConflict);
                        },
                        error: function() {
                            self.installBatch(items, index + 1, hasConflict);
                        }
                    });
                },

                showConflictDialog: function(itemData) {
                    var current = itemData.conflict || "未知";
                    var expected = itemData.expectedValue || "node plugin-dev-watch.js";

                    var html = "<div style=\"padding:10px;\">";
                    html += "<p style=\"color:#f0ad4e;font-size:14px;\"><i class=\"fa fa-exclamation-triangle\"></i> <strong>dev 命令冲突</strong></p>";
                    html += "<p style=\"color:#666;\">package.json 中已存在 <code>scripts.dev</code>，但值与期望不匹配：</p>";
                    html += "<table class=\"table table-bordered\" style=\"font-size:12px;\">";
                    html += "<tr><td style=\"width:80px;font-weight:600;\">当前值</td><td><code style=\"color:#d9534f;\">" + current.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</code></td></tr>";
                    html += "<tr><td style=\"font-weight:600;\">期望值</td><td><code style=\"color:#18bc9c;\">" + expected.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</code></td></tr>";
                    html += "</table>";
                    html += "<p style=\"color:#888;font-size:12px;margin-top:10px;\">";
                    html += "请手动编辑项目根目录下的 <code>package.json</code> 文件，<br>";
                    html += "将 <code>scripts</code> 中的 <code>dev</code> 字段修改为以上期望值。<br><br>";
                    html += "或者将 <code>dev</code> 重命名为其他名称后，重新点击安装。";
                    html += "</p></div>";

                    Layer.open({
                        type: 1,
                        title: "冲突详情 — 如何修复",
                        area: ["560px", "360px"],
                        content: html,
                        btn: ["知道了"],
                    });
                }
            };

            WatcherPage.init();
        ';

        return $this->renderPage('插件监视器安装管理器 - FastAdmin 工具箱', $content, $scripts, '', true);
    }

    /**
     * 通过 file_get_contents 下载文件，失败回退 cURL
     */
    private function downloadFile($url)
    {
        // 尝试 file_get_contents（跟随重定向）
        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; FastAdmin-Toolbox)',
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
        ]);
        $content = @file_get_contents($url, false, $ctx);
        if ($content !== false && strlen($content) > 0) {
            return $content;
        }

        // 回退 cURL
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FastAdmin-Toolbox)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && strlen($content) > 0) {
                return $content;
            }
        }

        return false;
    }
    /**
     * PHP 环境信息
     */
    public function phpinfo()
    {
        $phpVersion = PHP_VERSION;
        $tpVersion  = THINK_VERSION;
        $faVersion  = Config::get('fastadmin.version');

        $extensions = [
            'PDO'       => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
            'GD'        => extension_loaded('gd'),
            'cURL'      => extension_loaded('curl'),
            'Mbstring'  => extension_loaded('mbstring'),
            'Fileinfo'  => extension_loaded('fileinfo'),
            'OpenSSL'   => extension_loaded('openssl'),
            'Zip'       => extension_loaded('zip'),
            'Redis'     => extension_loaded('redis'),
            'Memcached' => extension_loaded('memcached'),
            'Swoole'    => extension_loaded('swoole'),
            'OPcache'   => extension_loaded('Zend OPcache'),
            'Xdebug'    => extension_loaded('xdebug'),
            'IonCube'   => extension_loaded('ionCube Loader'),
        ];

        $extRows = '';
        foreach ($extensions as $name => $loaded) {
            $badge = $loaded
                ? '<span style="color:#3c763d;background:#dff0d8;padding:2px 8px;border-radius:3px;font-size:12px;">已加载</span>'
                : '<span style="color:#999;background:#f5f5f5;padding:2px 8px;border-radius:3px;font-size:12px;">未加载</span>';
            $extRows .= "<div class=\"ext-cell\">{$name} {$badge}</div>";
        }

        $serverInfo = [
            '操作系统'     => php_uname('s') . ' ' . php_uname('r'),
            '服务器软件'   => $this->request->server('SERVER_SOFTWARE', 'N/A'),
            'PHP 运行方式' => php_sapi_name(),
            '上传限制'     => ini_get('upload_max_filesize'),
            'POST 限制'    => ini_get('post_max_size'),
            '执行时间限制' => ini_get('max_execution_time') . ' 秒',
            '内存限制'     => ini_get('memory_limit'),
            '时区'        => date_default_timezone_get(),
            '当前时间'     => date('Y-m-d H:i:s'),
        ];

        $serverRows = '';
        foreach ($serverInfo as $key => $value) {
            $serverRows .= '<tr><td style="padding:8px 14px;font-weight:600;white-space:nowrap;color:#555;width:140px;background:#fafafa;">' . $key . '</td><td style="padding:8px 14px;color:#333;">' . htmlspecialchars($value) . '</td></tr>';
        }

        $content = <<<HTML
            <style>
            .tb-phpinfo-grid { display:flex; gap:24px; flex-wrap:wrap; }
            .tb-phpinfo-col { flex:1 1 400px; min-width:0; }

            .tb-phpinfo-section { background:#fff; border:1px solid #e7e7e7; border-radius:6px; margin-bottom:20px; overflow:hidden; }
            .tb-phpinfo-section .sect-head { padding:10px 16px; font-size:14px; font-weight:600; color:#555; background:#fafafa; border-bottom:1px solid #eee; }
            .tb-phpinfo-section .sect-head i { margin-right:6px; color:#18bc9c; }

            .version-row { display:flex; padding:12px 16px; align-items:center; border-bottom:1px solid #f0f0f0; }
            .version-row:last-child { border-bottom:none; }
            .version-row .v-name { width:130px; font-weight:600; font-size:14px; color:#555; }
            .version-row .v-val { padding:3px 14px; background:#18bc9c; color:#fff; border-radius:4px; font-weight:700; font-family:monospace; font-size:14px; }

            .ext-grid { display:flex; flex-wrap:wrap; padding:10px 4px; }
            .ext-cell { padding:6px 12px; display:flex; align-items:center; gap:10px; font-size:13px; color:#555; min-width:140px; }

            .tb-phpinfo-section table { width:100%; border-collapse:collapse; }
            .tb-phpinfo-section table tr:not(:last-child) td { border-bottom:1px solid #f0f0f0; }
            </style>

            <div class="tb-hero">
                <h2><i class="fa fa-info-circle" style="color:#18bc9c;"></i> 系统环境信息</h2>
                <p class="tb-hero-sub">服务器 PHP 环境与扩展状态一览</p>
            </div>

            <div class="tb-phpinfo-grid">
                <div class="tb-phpinfo-col">
                    <div class="tb-phpinfo-section">
                        <div class="sect-head"><i class="fa fa-code"></i>核心版本</div>
                        <div class="sect-body">
                            <div class="version-row"><div class="v-name">PHP</div><div class="v-val">{$phpVersion}</div></div>
                            <div class="version-row"><div class="v-name">ThinkPHP</div><div class="v-val">{$tpVersion}</div></div>
                            <div class="version-row"><div class="v-name">FastAdmin</div><div class="v-val">{$faVersion}</div><a href="javascript:void(0);" class="btn btn-text btn-xs btn-checkupdates" >检查更新</a></div>
                        </div>
                    </div>

                    <div class="tb-phpinfo-section">
                        <div class="sect-head"><i class="fa fa-puzzle-piece"></i>关键扩展</div>
                        <div class="sect-body"><div class="ext-grid">{$extRows}</div></div>
                    </div>
                </div>

                <div class="tb-phpinfo-col">
                    <div class="tb-phpinfo-section">
                        <div class="sect-head"><i class="fa fa-server"></i>服务器信息</div>
                        <div class="sect-body"><table>{$serverRows}</table></div>
                    </div>
                </div>
            </div>
        HTML;

        $scripts = <<<JS
            $(document).on('click', '.btn-checkupdates', function() {
                Layer.msg("正在检查更新...", {icon: 16, shade: 0.3, time: 0});
                $.ajax({
                    url: Config.toolbox_check_updates_url,
                    type: "POST",
                    dataType: "json",
                    success: function(res) {
                        Layer.closeAll();
                        if (res.code === 1) {
                            var data = res.data;
                            if (data.has_update) {
                                var html = "<div style='padding:20px;'>";
                                html += "<p>当前版本：<strong>" + data.current_version + "</strong></p>";
                                html += "<p>发现新版本：</p><ul style='padding-left:20px;'>";
                                data.new_versions.forEach(function(ver) {
                                    html += "<li><strong>" + ver.version_full + "</strong>:<br>" + ver.changelog.map(function(item) { return "- " + item; }).join("<br>") + "<br>";
                                    if (ver.security_link) {
                                        html += "<a href='" + ver.security_link + "' target='_blank'>查看安全修复方法</a><br>";
                                    }
                                    html += "<a href='" + ver.download_full + "' target='_blank'>下载完整包</a> | <a href='" + ver.download_patch + "' target='_blank'>下载补丁包</a>";
                                    html += "</li><hr>";
                                });
                                html += "</ul></div>";
                                Layer.open({
                                    type: 1,
                                    title: "更新提示",
                                    area: ["500px", "400px"],
                                    content: html,
                                    btn: ["关闭"]
                                });
                            } else {
                                Layer.msg("当前已是最新版本", {icon: 1});
                            }
                        } else {
                            Layer.msg("检查更新失败: " + (res.msg || ""), {icon: 2});
                        }
                    },
                    error: function(xhr, status, error) {
                        Layer.closeAll();
                        Layer.msg("请求失败: " + (error || status), {icon: 2});
                    }
                });
            });
        JS;
        $this->assignconfig('toolbox_check_updates_url', url('checkUpdates'));
        return $this->renderPage('系统环境信息 - FastAdmin 工具箱', $content, $scripts, '', true);
    }

    public function checkUpdates()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (!$this->request->isAjax()) {
            $this->error(__('请求无效'));
        }

        // 1. 获取当前系统版本号
        $currentVersion = config('fastadmin.version');
        // 提取干净的版本号用于比较 (假设 config('site.version') 格式为 'V1.6.2.20260323')
        $currentVersionClean = ltrim($currentVersion, 'Vv');

        // 2. 尝试从缓存获取版本列表
        $versionList = Cache::get('fastadmin_version_list');
        if (!$versionList) {
            // 3. 从官网获取并解析版本列表
            $versionList = $this->fetchVersionsFromOfficial();
            if (empty($versionList)) {
                $this->error('获取官方版本信息失败，请检查网络');
            }
            // 缓存12小时
            Cache::set('fastadmin_version_list', $versionList, 43200);
        }

        // 4. 筛选出新版本
        $newVersions = [];
        foreach ($versionList as $ver) {
            if (version_compare($ver['version_clean'], $currentVersionClean, '>')) {
                $newVersions[] = $ver;
            }
        }

        if (empty($newVersions)) {
            $this->success('当前已是最新版本', null, ['has_update' => false]);
        } else {
            $this->success('发现新版本', null, [
                'has_update'     => true,
                'current_version' => $currentVersion,
                'new_versions'    => $newVersions,
            ]);
        }
    }

    /**
     * 抓取并解析官网下载页
     * @return array
     */
    protected function fetchVersionsFromOfficial()
    {
        $url = 'https://www.fastadmin.net/download.html';
        $html = $this->getRemoteHtml($url);
        if (!$html) {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // 1. 定位 id="version-list" 的容器
        $versionListContainer = $xpath->query("//*[@id='version-list']");
        if ($versionListContainer->length == 0) {
            // 如果没找到，回退到原来的全局查找
            $versionNodes = $xpath->query("//*[contains(text(), '#### V')]");
        } else {
            // 在容器内查找所有包含版本号的元素（可能是 h3/h4 或带有 'version-item' 类的 div）
            $container = $versionListContainer->item(0);
            // 查找所有标题标签或包含 'V数字' 的元素
            $versionNodes = $xpath->query(".//*[contains(text(), '#### V')]", $container);
            if ($versionNodes->length == 0) {
                // 备用：查找所有 h4 或 h3 元素
                $versionNodes = $xpath->query(".//h4 | .//h3", $container);
            }
        }

        if ($versionNodes->length == 0) {
            \think\Log::record('未找到版本列表容器或版本节点', 'error');
            return [];
        }

        $versions = [];
        foreach ($versionNodes as $node) {
            $fullText = trim($node->nodeValue);
            // 尝试多种正则提取版本号和日期
            if (preg_match('/####\s+V([\d\.]+)\s+(\d{4}-\d{2}-\d{2})/', $fullText, $match)) {
                $versionClean = $match[1];
                $versionFull = 'V' . $versionClean;
                $date = $match[2];
            } elseif (preg_match('/V([\d\.]+)(?:\s+(\d{4}-\d{2}-\d{2}))?/', $fullText, $match)) {
                $versionClean = $match[1];
                $versionFull = 'V' . $versionClean;
                $date = $match[2] ?? '';
            } else {
                continue;
            }

            // 获取当前版本节点的下一个兄弟节点（通常是 ul 列表或描述文本）
            $blockNodes = [];
            $next = $node->nextSibling;
            while ($next) {
                if ($next instanceof \DOMElement) {
                    // 如果遇到下一个版本标题，停止
                    $nextText = trim($next->nodeValue);
                    if (preg_match('/####\s+V|V[\d\.]+/', $nextText) && $next !== $node) {
                        break;
                    }
                    $blockNodes[] = $next;
                }
                $next = $next->nextSibling;
            }

            // 提取更新日志（ul > li 列表）
            $changelog = [];
            foreach ($blockNodes as $block) {
                if ($block->tagName == 'ul') {
                    $lis = $xpath->query('.//li', $block);
                    foreach ($lis as $li) {
                        // 获取 li 内部的 HTML，包括 a 标签
                        $innerHtml = '';
                        foreach ($li->childNodes as $child) {
                            $innerHtml .= $dom->saveHTML($child);
                        }
                        $innerHtml = trim($innerHtml);
                        if ($innerHtml !== '') {
                            $changelog[] = $innerHtml;
                        }
                    }
                    break;
                }
            }

            // 提取安全更新链接（在描述文本中可能有 [查看修复方法](url)）
            $securityLink = '';
            foreach ($blockNodes as $block) {
                if ($block->tagName == 'p' || $block->tagName == 'div') {
                    if (preg_match('/\[查看修复方法\]\((https?:\/\/[^\)]+)\)/', $block->nodeValue, $linkMatch)) {
                        $securityLink = $linkMatch[1];
                        break;
                    }
                }
            }

            // 拼接下载链接
            $fullDownloadLink = "https://www.fastadmin.net/download/full.html?version={$versionClean}";
            $patchDownloadLink = "https://www.fastadmin.net/download/patch.html?version={$versionClean}";

            $versions[] = [
                'version_full'   => $versionFull,
                'version_clean'  => $versionClean,
                'date'           => $date,
                'changelog'      => $changelog,
                'security_link'  => $securityLink ?: null,
                'download_full'  => $fullDownloadLink,
                'download_patch' => $patchDownloadLink,
            ];
        }
        
        // 按版本号降序排序
        usort($versions, function ($a, $b) {
            return version_compare($b['version_clean'], $a['version_clean']);
        });

        return $versions;
    }

    /**
     * 从文本块中提取更新日志项
     * @param string $text
     * @return array
     */
    protected function parseChangelogFromText($text)
    {
        $items = [];
        // 按 " - " 分割
        $parts = explode(' - ', $text);
        foreach ($parts as $part) {
            $item = trim($part);
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * 从文本块中提取安全更新链接
     * @param string $text
     * @return string|null
     */
    protected function extractSecurityLinkFromText($text)
    {
        // 匹配 [查看修复方法](https://...)
        if (preg_match('/\[查看修复方法\]\((https?:\/\/[^\)]+)\)/', $text, $linkMatch)) {
            return $linkMatch[1];
        }
        return null;
    }

    /**
     * 获取远程页面HTML内容
     * @param string $url
     * @return string|false
     */
    protected function getRemoteHtml($url)
    {
        $response = @file_get_contents($url);
        if ($response === false) {
            \think\Log::record('file_get_contents 获取失败', 'error');
            return false;
        }
        return $response;
    }

    protected function appendBeforeContentEnd($html, $appendHtml) {
        // 找到 content div 的起始位置
        $start = strpos($html, '<div class="content"');
        if ($start === false) {
            return $html; // 没找到 content
        }

        // 找到第一个 '>'，即 content div 的开始标签结束处
        $startTagEnd = strpos($html, '>', $start);
        if ($startTagEnd === false) {
            return $html;
        }

        $pos = $startTagEnd + 1;
        $len = strlen($html);
        $depth = 1; // 已经进入了一个 <div>

        while ($pos < $len) {
            // 找下一个 <div 或 </div>
            $nextOpen  = strpos($html, '<div', $pos);
            $nextClose = strpos($html, '</div>', $pos);

            // 如果没有更多 div，退出
            if ($nextClose === false) break;

            // 判断是先遇到开标签还是闭标签
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                // 遇到嵌套 div
                $depth++;
                $pos = $nextOpen + 4;
            } else {
                // 遇到 </div>
                $depth--;
                if ($depth === 0) {
                    // 找到 content 对应的结束 div
                    return substr($html, 0, $nextClose)
                        . $appendHtml
                        . substr($html, $nextClose);
                }
                $pos = $nextClose + 6;
            }
        }

        return $html; // 未找到匹配的结束 div
    }

    /**
     * 插件安装方法（基于addons中已有插件文件）
     * 参照 Service::install 实现，跳过下载/解压步骤
     * @param string $addon_name 插件名称
     * @return true
     * @throws \Exception
     */
    protected function addon_install_by_dir($addon_name)
    {
        $addonDir = ADDON_PATH . $addon_name . DS;

        // 检查 info.ini
        $infoFile = $addonDir . 'info.ini';
        if (!is_file($infoFile)) {
            throw new \think\Exception('插件 info.ini 文件不存在');
        }

        // 检查文件冲突
        Service::noconflict($addon_name);

        // 获取插件信息
        $info = get_addon_info($addon_name);

        Db::startTrans();
        try {
            // 设置为启用状态
            if (!$info['state']) {
                $info['state'] = 1;
                set_addon_info($addon_name, $info);
            }

            // 执行插件的 install() 方法
            $class = get_addon_class($addon_name);
            if (class_exists($class)) {
                $addon = new $class();
                $addon->install();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \think\Exception('插件安装失败: ' . $e->getMessage());
        }

        // 导入 install.sql
        Service::importsql($addon_name);

        // 临时关闭纯净模式，防止 Service::enable() 删除插件目录下的
        // application、public、assets 源码文件
        $pureMode = config('fastadmin.addon_pure_mode');
        if ($pureMode) {
            Config::set('fastadmin.addon_pure_mode', false);
        }

        // 启用插件（复制全局资源文件、刷新缓存）
        Service::enable($addon_name, true);

        // 恢复纯净模式配置
        if ($pureMode) {
            Config::set('fastadmin.addon_pure_mode', true);
        }

        return true;
    }
    /**
     * 插件菜单安装方法（细颗粒度）
     * 仅调用插件类的 install() 方法安装菜单，不修改插件状态和文件
     * @param string $addon_name 插件名称
     * @param string $menu_content 可选的自定义菜单数组
     * @return true
     * @throws \Exception
     */
    protected function addon_install_menu($addon_name, $menu_content = '')
    {
        // 如果传入了自定义菜单内容，直接使用
        if (!empty($menu_content)) {
            if (is_string($menu_content)) {
                $menu = json_decode($menu_content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \think\Exception('菜单内容 JSON 解析失败');
                }
            } else {
                $menu = $menu_content;
            }
            if (empty($menu) || !is_array($menu)) {
                throw new \think\Exception('菜单数据为空或格式错误');
            }
            if (!isset($menu[0]) || !isset($menu[0]['name'])) {
                throw new \think\Exception('菜单格式不正确，缺少 name 字段');
            }
            \app\common\library\Menu::create($menu);
            return true;
        }

        // 获取插件类并执行 install() 方法
        $class = get_addon_class($addon_name);
        if (!$class || !class_exists($class)) {
            throw new \think\Exception('插件类不存在: ' . $addon_name);
        }

        $addon = new $class();
        if (!method_exists($addon, 'install')) {
            throw new \think\Exception('插件未定义 install() 方法');
        }

        Db::startTrans();
        try {
            $addon->install();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return true;
    }

    /**
     * 插件数据表导入方法（细颗粒度）
     * 执行插件目录下的 install.sql 文件，自动替换表前缀
     * @param string $addon_name 插件名称
     * @param string $sql_content 可选的自定义 SQL 内容（传入则优先使用）
     * @return true
     * @throws \Exception
     */
    protected function addon_install_db($addon_name, $sql_content = '')
    {
        $tablePrefix = config('database.prefix');

        if (!empty($sql_content)) {
            // 直接执行传入的 SQL 内容
            $sql = $sql_content;
            $source = '自定义内容';
        } else {
            $sqlFile = ADDON_PATH . $addon_name . DS . 'install.sql';
            if (!is_file($sqlFile)) {
                throw new \think\Exception('install.sql 文件不存在');
            }
            $sql = file_get_contents($sqlFile);
            $source = basename($sqlFile);
            if (empty(trim($sql))) {
                throw new \think\Exception('install.sql 文件内容为空');
            }
        }

        $lines = explode("\n", $sql);
        $templine = '';
        $executedCount = 0;
        $errors = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // 跳过注释行和空行
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                continue;
            }

            $templine .= $line;
            // 以分号结尾表示一条完整 SQL
            if (substr($trimmed, -1, 1) === ';') {
                // 替换表前缀
                $templine = str_ireplace('__PREFIX__', $tablePrefix, $templine);
                // INSERT 转 INSERT IGNORE 避免重复执行报错
                $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);

                try {
                    Db::getPdo()->exec($templine);
                    $executedCount++;
                } catch (\PDOException $e) {
                    $errors[] = $e->getMessage();
                }
                $templine = '';
            }
        }

        // 处理最后可能未以分号结尾的语句
        if (!empty(trim($templine))) {
            $templine = str_ireplace('__PREFIX__', $tablePrefix, $templine);
            $templine = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $templine);
            try {
                Db::getPdo()->exec($templine);
                $executedCount++;
            } catch (\PDOException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new \think\Exception('SQL 执行部分失败: ' . implode('; ', array_slice($errors, 0, 3)));
        }

        return true;
    }

    /**
     * 插件菜单卸载方法（细颗粒度）
     * 优先调用插件类的 uninstall() 方法，若不存在则直接通过 Menu::delete() 删除
     * @param string $addon_name 插件名称
     * @return true
     * @throws \Exception
     */
    protected function addon_uninstall_menu($addon_name)
    {
        $class = get_addon_class($addon_name);

        if ($class && class_exists($class)) {
            $addon = new $class();
            if (method_exists($addon, 'uninstall')) {
                Db::startTrans();
                try {
                    $addon->uninstall();
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    throw $e;
                }
                return true;
            }
        }

        // 降级策略：插件类不存在或无 uninstall 方法，直接用 Menu::delete 删除
        Db::startTrans();
        try {
            \app\common\library\Menu::delete($addon_name);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \think\Exception('菜单卸载失败: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * 插件菜单导出方法
     * 可调用此方法将插件的菜单数据导出为 JSON 格式字符串，方便备份或迁移
     * @param string $menu_id 菜单ID
     * @return string|false
     */
    protected function addon_export_menu($menu_id)
    {

    }

    // ==================== 菜单导入导出辅助方法 ====================

    /**
     * 构建菜单导出数据结构
     * 将根节点与 Tree 子树合并，递归处理 childlist -> sublist
     *
     * @param array $root     根节点（来自 auth_rule 表的一条记录）
     * @param array $children fast\Tree::getTreeArray() 返回的子树
     * @return array
     */
    private function buildMenuExportData($root, $children = [])
    {
        $data = $root;
        unset($data['id'], $data['pid'], $data['py'], $data['pinyin']);
        if (!empty($children)) {
            $data['sublist'] = $this->cleanExportChildren($children);
        }
        return $data;
    }

    /**
     * 递归清洗导出子节点：去除 id/pid/spacer，将 childlist 重命名为 sublist
     *
     * @param array $children
     * @return array
     */
    private function cleanExportChildren($children)
    {
        $result = [];
        foreach ($children as $child) {
            $item = $child;
            unset($item['id'], $item['pid'], $item['py'], $item['pinyin'], $item['spacer']);
            if (!empty($child['childlist'])) {
                $item['sublist'] = $this->cleanExportChildren($child['childlist']);
            }
            // 始终移除 childlist，有子节点时已转为 sublist，无子节点时彻底清除
            unset($item['childlist']);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 美化 var_export 输出为现代 PHP 短数组语法
     *
     * @param mixed $data
     * @return string
     */
    private function varExportPretty($data)
    {
        $export = var_export($data, true);
        // 去除数字索引键（var_export 会生成 0 =>, 1 => 等）
        $export = preg_replace('/^(\s*)\d+ => /m', '$1', $export);
        // array ( -> [
        $export = preg_replace('/^(\s*)array \(/m', '$1[', $export);
        // ) -> ]（var_export 的每个 ) 都在独立行）
        $export = preg_replace('/^(\s*)\)/m', '$1]', $export);
        return $export;
    }

    /**
     * 清洗用户粘贴的菜单代码字符串
     * 去除 PHP 标签、变量赋值、结尾分号
     *
     * @param string $code
     * @return string
     */
    private function cleanMenuCode($code)
    {
        $code = trim($code);
        // 去除开头的 PHP 标签
        $code = preg_replace('/^<\?php\s*/i', '', $code);
        // 去除变量赋值语句，如 $menu = , $menus = 等
        $code = preg_replace('/^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*=\s*/', '', $code);
        // 去除结尾分号
        $code = rtrim($code, ";\t\n\r\0\x0B ");
        return trim($code);
    }

    /**
     * 安全解析 PHP 数组字符串（token_get_all 词法分析，零代码执行）
     *
     * @param string $code PHP 数组字面量字符串
     * @return array
     */
    private function parseTokenArray($code)
    {
        $code = trim($code);
        if (empty($code)) {
            return [];
        }
        $tokens = token_get_all('<?php ' . $code);
        $index = 0;
        // 跳过开头的 T_OPEN_TAG
        while ($index < count($tokens)) {
            if (is_array($tokens[$index]) && $tokens[$index][0] === T_OPEN_TAG) {
                $index++;
            } else {
                break;
            }
        }
        // 跳过顶部外层 '['，避免 parseArrayTokens 多包一层
        while ($index < count($tokens) && (is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE || $tokens[$index] === '[')) {
            if ($tokens[$index] === '[') {
                $index++;
                break;
            }
            $index++;
        }
        return $this->parseArrayTokens($tokens, $index);
    }

    /**
     * 递归解析 token 流为 PHP 数组
     *
     * @param array $tokens
     * @param int   &$index
     * @return array
     */
    private function parseArrayTokens(&$tokens, &$index)
    {
        $result = [];
        $key = null;
        $count = count($tokens);

        while ($index < $count) {
            $token = $tokens[$index];
            $index++;

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_ARRAY:
                        // array(...) 语法：跳过 (
                        while ($index < $count) {
                            $t = $tokens[$index];
                            $index++;
                            if ($t === '(') break;
                        }
                        $value = $this->parseArrayTokens($tokens, $index);
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case T_CONSTANT_ENCAPSED_STRING:
                        $value = $this->resolveString($token[1]);
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case T_DOUBLE_ARROW:
                        // => 箭头，上一个值变为键
                        $key = array_pop($result);
                        break;

                    case T_LNUMBER:
                        $value = (int)$token[1];
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case T_DNUMBER:
                        $value = (float)$token[1];
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case T_STRING:
                        // 未加引号的字符串（常量名/关键字 null/true/false）
                        $lower = strtolower($token[1]);
                        if ($lower === 'null') {
                            $value = null;
                        } elseif ($lower === 'true') {
                            $value = true;
                        } elseif ($lower === 'false') {
                            $value = false;
                        } else {
                            $value = $token[1];
                        }
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;

                    default:
                        break;
                }
            } else {
                // 单字符 token
                switch ($token) {
                    case '[':
                        $value = $this->parseArrayTokens($tokens, $index);
                        if ($key !== null) {
                            $result[$key] = $value;
                            $key = null;
                        } else {
                            $result[] = $value;
                        }
                        break;

                    case ']':
                    case ')':
                        return $result;

                    case ',':
                        $key = null;
                        break;

                    default:
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * 将 PHP token 字符串值解析为实际字符串
     *
     * @param string $str token 原始字符串（含引号）
     * @return string
     */
    private function resolveString($str)
    {
        if ($str === '' || strlen($str) < 2) {
            return '';
        }
        $quote = $str[0];
        $content = substr($str, 1, -1);

        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $content);
        } else {
            return str_replace(
                ['\\\\', '\\"', '\\n', '\\r', '\\t', '\\$'],
                ['\\', '"', "\n", "\r", "\t", '$'],
                $content
            );
        }
    }

}