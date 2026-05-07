<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;
use think\Exception;
use think\addons\AddonException;
use think\addons\Service;
use fast\Http;
use think\Cache;

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
     * 工具箱首页
     */
    public function index()
    {
        // -------- 工具集（按分类） --------
        $categories = [
            '开发辅助' => [
                [
                    'icon'   => 'fa-info-circle',
                    'title'  => '系统环境信息',
                    'desc'   => '查看 PHP、ThinkPHP、FastAdmin 版本及关键扩展状态等服务器环境详情。',
                    'url'    => url('phpinfo'),
                    'status' => 'ready',
                    'color'  => '#18bc9c',
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
                    'icon'   => 'fa-key',
                    'title'  => '密码生成器',
                    'desc'   => '密码加密生成工具自定义盐值。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#337ab7',
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
                    'icon'   => 'fa-sitemap',
                    'title'  => '菜单管理器',
                    'desc'   => '可以方便的对菜单进行导入导出操作，方便插件开发使用。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#337ab7',
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
            '系统运维' => [
                [
                    'icon'   => 'fa-file-text-o',
                    'title'  => '日志查看器',
                    'desc'   => '实时查看系统运行日志、错误日志与 SQL 日志，支持下载。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#d9534f',
                ],
                [
                    'icon'   => 'fa-folder-open',
                    'title'  => '文件管理器',
                    'desc'   => '在线浏览与管理项目文件系统，支持编辑、上传、重命名。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#3c8dbc',
                ],
                [
                    'icon'   => 'fa-database',
                    'title'  => '数据备份',
                    'desc'   => '一键备份/还原数据库，支持按表选择与定时计划。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#5bc0de',
                ],
                [
                    'icon'   => 'fa-bug',
                    'title'  => '调试面板',
                    'desc'   => '查看日志、SQL 查询记录、请求链路追踪与变量输出。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#34495e',
                ],
            ],
            '插件功能增强' => [
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
                'icon'  => 'fa-flag',
                'title' => 'Font Awesome 4',
                'desc'  => 'Font Awesome 4 图标库',
                'url'   => 'https://fontawesome.com/v4/icons/',
                'color' => '#228b22',
                'tag'   => '图标库',
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
        foreach ($categories as $catName => $tools) {
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

}