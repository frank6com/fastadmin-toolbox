<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;
use think\Exception;
use think\addons\AddonException;
use think\addons\Service;

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

        $this->assignconfig([
            'toolbox_index_url'     => url('index'),
            'toolbox_installer_url' => url('installer'),
            'toolbox_phpinfo_url'   => url('phpinfo'),
            'addon_testdata_url'    => url('addon/testdata'),
        ]);

        $this->assign('title', $title);

        $backHtml = '';
        if ($showBack) {
            $backUrl = $backUrl ?: url('index');
            $backHtml = '<a href="' . $backUrl . '" class="btn btn-default back-btn" style="margin-bottom:15px;"><i class="fa fa-arrow-left"></i> 工具箱首页</a>';
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
                    'icon'   => 'fa-upload',
                    'title'  => '本地插件安装',
                    'desc'   => '上传 zip 包离线安装插件，自动解压识别，支持安装后导入测试数据。',
                    'url'    => url('installer'),
                    'status' => 'ready',
                    'color'  => '#18bc9c',
                ],
                [
                    'icon'   => 'fa-info-circle',
                    'title'  => 'PHP 环境信息',
                    'desc'   => '查看 PHP、ThinkPHP、FastAdmin 版本及关键扩展状态等服务器环境详情。',
                    'url'    => url('phpinfo'),
                    'status' => 'ready',
                    'color'  => '#18bc9c',
                ],
            ],
            '实用工具' => [
                [
                    'icon'   => 'fa-database',
                    'title'  => '数据库助手',
                    'desc'   => '常用数据库操作辅助工具。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#3c8dbc',
                ],
                [
                    'icon'   => 'fa-file-code-o',
                    'title'  => '代码生成器',
                    'desc'   => '快速生成标准 CRUD 控制器和模型代码。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#f39c12',
                ],
                [
                    'icon'   => 'fa-bug',
                    'title'  => '调试面板',
                    'desc'   => '查看日志、SQL 查询记录、请求链路追踪。',
                    'url'    => '',
                    'status' => 'wip',
                    'color'  => '#d9534f',
                ],
            ],
        ];

        $toolHtml = '';
        foreach ($categories as $catName => $tools) {
            $cards = '';
            foreach ($tools as $t) {
                $badge = $t['status'] === 'ready'
                    ? '<span class="card-badge badge-ready">可用</span>'
                    : '<span class="card-badge badge-wip">准备中</span>';
                $href = $t['status'] === 'ready'
                    ? 'href="' . $t['url'] . '"'
                    : 'href="javascript:void(0);" onclick="return false;"';
                $cls = $t['status'] === 'ready' ? 'tool-card-active' : 'tool-card-disabled';
                $cards .= <<<CARD
                <a {$href} class="tool-card {$cls}">
                    <div class="tool-card-icon" style="background:{$t['color']};">
                        <i class="fa {$t['icon']}"></i>
                    </div>
                    <div class="tool-card-body">
                        <div class="tool-card-title">{$t['title']} {$badge}</div>
                        <div class="tool-card-desc">{$t['desc']}</div>
                    </div>
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

        // -------- 速查文档 --------
        $docs = [
            [
                'icon'  => 'fa-file-text-o',
                'title' => 'FastAdmin 文档',
                'desc'  => 'FastAdmin 框架官方文档与社区资源',
                'url'   => 'https://doc.fastadmin.net/',
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
                'icon'  => 'fa-css3',
                'title' => 'Bootstrap 3',
                'desc'  => 'Bootstrap 3 中文文档',
                'url'   => 'https://v3.bootcss.com/',
                'color' => '#563d7c',
                'tag'   => '前端UI',
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
                'icon'  => 'fa-code',
                'title' => 'jQuery API',
                'desc'  => 'jQuery API 文档',
                'url'   => 'https://api.jquery.com/',
                'color' => '#0769ad',
                'tag'   => 'JS库',
            ],
            [
                'icon'  => 'fa-table',
                'title' => 'Bootstrap Table',
                'desc'  => '表格组件文档',
                'url'   => 'https://bootstrap-table.com/docs/',
                'color' => '#d9534f',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-window-maximize',
                'title' => 'Layer',
                'desc'  => 'Web 弹层组件文档',
                'url'   => 'https://layer.layui.com/',
                'color' => '#f39c12',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-sitemap',
                'title' => 'jstree',
                'desc'  => '树形控件文档',
                'url'   => 'https://www.jstree.com/',
                'color' => '#337ab7',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-check-square-o',
                'title' => 'Nice Validator',
                'desc'  => '表单验证组件文档',
                'url'   => 'https://niceue.com/validator/',
                'color' => '#5cb85c',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-search',
                'title' => 'SelectPage',
                'desc'  => '动态下拉分页选择插件文档',
                'url'   => 'https://selectpage.info/',
                'color' => '#8e44ad',
                'tag'   => '组件库',
            ],
            [
                'icon'  => 'fa-bar-chart',
                'title' => 'ECharts',
                'desc'  => '数据可视化图表库文档',
                'url'   => 'https://echarts.apache.org/',
                'color' => '#e74c3c',
                'tag'   => '图表库',
            ],
        ];

        $docCards = '';
        foreach ($docs as $d) {
            $docCards .= <<<CARD
            <a class="doc-item" href="{$d['url']}" target="_blank">
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

            .badge-ready { display:inline-block; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:500; background:#dff0d8; color:#3c763d; vertical-align:middle; margin-left:6px; line-height:1.6; }
            .badge-wip { display:inline-block; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:500; background:#f3f3f3; color:#aaa; vertical-align:middle; margin-left:6px; line-height:1.6; }

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
                    <div class="tb-docs-section">
                        <div class="tb-docs-title"><i class="fa fa-book"></i> 速查文档</div>
                        {$docCards}
                    </div>
                </div>
            </div>
        HTML;

        return $this->renderPage('工具箱 - FastAdmin 开发工具箱', $content, '', '', false);
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
                <h2><i class="fa fa-info-circle" style="color:#18bc9c;"></i> PHP 环境信息</h2>
                <p class="tb-hero-sub">服务器 PHP 环境与扩展状态一览</p>
            </div>

            <div class="tb-phpinfo-grid">
                <div class="tb-phpinfo-col">
                    <div class="tb-phpinfo-section">
                        <div class="sect-head"><i class="fa fa-code"></i>核心版本</div>
                        <div class="sect-body">
                            <div class="version-row"><div class="v-name">PHP</div><div class="v-val">{$phpVersion}</div></div>
                            <div class="version-row"><div class="v-name">ThinkPHP</div><div class="v-val">{$tpVersion}</div></div>
                            <div class="version-row"><div class="v-name">FastAdmin</div><div class="v-val">{$faVersion}</div></div>
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

        return $this->renderPage('PHP 环境信息 - FastAdmin 工具箱', $content, '', '', true);
    }

}