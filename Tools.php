<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\addons\AddonException;
use think\addons\Service;
use think\exception\HttpResponseException;
use PhpZip\ZipFile;

class Tools extends Backend
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 本地插件免验证安装
     * GET: 显示上传页  POST: 执行安装
     */
    public function install()
    {
        if ($this->request->isPost()) {
            return $this->doInstall();
        }

        // 显示上传页
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>本地插件安装（免验证）</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #f0f2f5;
            --card: #ffffff;
            --card-border: rgba(0,0,0,0.06);
            --fg: #1a1d23;
            --muted: #8c919a;
            --accent: #0ea573;
            --accent-hover: #0c8f64;
            --accent-dim: rgba(14,165,115,0.08);
            --accent-ring: rgba(14,165,115,0.18);
            --danger: #dc3545;
            --danger-dim: rgba(220,53,69,0.07);
            --danger-ring: rgba(220,53,69,0.18);
            --radius: 16px;
            --shadow-card: 0 1px 3px rgba(0,0,0,0.04), 0 8px 32px rgba(0,0,0,0.06);
            --shadow-btn: 0 2px 8px rgba(14,165,115,0.25);
        }

        body {
            font-family: "Microsoft YaHei", sans-serif;
            background: var(--bg);
            color: var(--fg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* 背景装饰 */
        .bg-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.45;
            pointer-events: none;
            will-change: transform;
        }
        .bg-blob--1 {
            width: 520px; height: 520px;
            background: radial-gradient(circle, #a7f3d0 0%, transparent 70%);
            top: -200px; left: -140px;
            animation: blobDrift1 14s ease-in-out infinite;
        }
        .bg-blob--2 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #93c5fd 0%, transparent 70%);
            bottom: -160px; right: -100px;
            animation: blobDrift2 17s ease-in-out infinite;
        }
        .bg-blob--3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #fde68a 0%, transparent 70%);
            top: 40%; left: 55%;
            opacity: 0.25;
            animation: blobDrift3 11s ease-in-out infinite;
        }
        @keyframes blobDrift1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(60px, 50px) scale(1.08); }
        }
        @keyframes blobDrift2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-50px, -60px) scale(1.12); }
        }
        @keyframes blobDrift3 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-40px, 30px); }
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,0,0,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.025) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
        }

        /* 浮动小圆点 */
        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .particle {
            position: absolute;
            width: 3px; height: 3px;
            background: var(--accent);
            border-radius: 50%;
            opacity: 0;
            animation: particlePulse 5s ease-in-out infinite;
        }
        @keyframes particlePulse {
            0%, 100% { opacity: 0; transform: translateY(0) scale(0.8); }
            50% { opacity: 0.35; transform: translateY(-18px) scale(1.1); }
        }

        /* 卡片 */
        .card {
            position: relative;
            z-index: 1;
            width: 460px;
            max-width: 92vw;
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 40px 36px 36px;
            box-shadow: var(--shadow-card);
            animation: cardEnter 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* 卡片顶部装饰线 */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 36px; right: 36px;
            height: 3px;
            border-radius: 0 0 3px 3px;
            background: linear-gradient(90deg, var(--accent), #34d399, var(--accent));
            opacity: 0.7;
        }

        /* 头部 */
        .header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .header-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent-dim), rgba(52,211,153,0.12));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            color: var(--accent);
            flex-shrink: 0;
            border: 1px solid rgba(14,165,115,0.1);
        }
        .header-text h3 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
            line-height: 1.3;
        }
        .header-text span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 400;
        }

        /* 拖拽上传区 */
        .drop-zone {
            position: relative;
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 34px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
            background: rgba(0,0,0,0.008);
        }
        .drop-zone::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, var(--accent-dim), transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(14,165,115,0.02);
        }
        .drop-zone:hover::before,
        .drop-zone.dragover::before {
            opacity: 1;
        }
        .drop-zone.has-file {
            border-color: var(--accent-ring);
            border-style: solid;
            background: var(--accent-dim);
        }

        .drop-zone-icon {
            font-size: 30px;
            color: #c0c4cc;
            margin-bottom: 10px;
            transition: color 0.3s, transform 0.3s;
            position: relative;
            z-index: 1;
        }
        .drop-zone:hover .drop-zone-icon,
        .drop-zone.dragover .drop-zone-icon {
            color: var(--accent);
            transform: translateY(-4px);
        }
        .drop-zone.has-file .drop-zone-icon {
            color: var(--accent);
        }

        .drop-zone-label {
            font-size: 14px;
            color: var(--muted);
            position: relative;
            z-index: 1;
            transition: color 0.3s;
        }
        .drop-zone:hover .drop-zone-label {
            color: var(--fg);
        }
        .drop-zone-label strong {
            color: var(--accent);
            font-weight: 600;
        }
        .drop-zone-hint {
            font-size: 11px;
            color: rgba(0,0,0,0.18);
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }

        .file-input-hidden {
            position: absolute;
            width: 0; height: 0;
            opacity: 0;
            pointer-events: none;
        }

        /* 已选文件名 */
        .file-name {
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--fg);
            background: var(--accent-dim);
            border: 1px solid var(--accent-ring);
            border-radius: 8px;
            padding: 9px 12px;
            margin-bottom: 20px;
            animation: fadeSlide 0.3s ease;
        }
        .file-name.show { display: flex; }
        .file-name i { color: var(--accent); font-size: 15px; }
        .file-name-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-name-clear {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: 2px 4px;
            font-size: 13px;
            transition: color 0.2s;
            line-height: 1;
        }
        .file-name-clear:hover { color: var(--danger); }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 提交按钮 */
        .btn-submit {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: var(--accent);
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.18) 0%, transparent 50%);
            opacity: 0;
            transition: opacity 0.25s;
            pointer-events: none;
        }
        .btn-submit:hover:not(:disabled)::after { opacity: 1; }
        .btn-submit:hover:not(:disabled) {
            background: var(--accent-hover);
            box-shadow: var(--shadow-btn);
            transform: translateY(-1px);
        }
        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(14,165,115,0.2);
        }
        .btn-submit:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            flex-shrink: 0;
        }
        .btn-submit.loading .spinner { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* 底部提示 */
        .tip {
            margin-top: 20px;
            padding: 12px 14px;
            background: rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 10px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.7;
            display: flex;
            gap: 8px;
        }
        .tip i {
            color: rgba(0,0,0,0.15);
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* 消息提示 */
        .msg {
            margin-top: 16px;
            font-size: 13px;
            font-weight: 600;
            padding: 12px 14px;
            border-radius: 10px;
            display: none;
            align-items: center;
            gap: 8px;
            animation: fadeSlide 0.35s ease;
        }
        .msg.show { display: flex; }

        .msg--loading {
            background: rgba(0,0,0,0.025);
            border: 1px solid rgba(0,0,0,0.06);
            color: var(--muted);
        }
        .msg-dot {
            display: inline-flex;
            gap: 3px;
        }
        .msg-dot span {
            width: 4px; height: 4px;
            background: var(--muted);
            border-radius: 50%;
            animation: dotBounce 1.2s ease-in-out infinite;
        }
        .msg-dot span:nth-child(2) { animation-delay: 0.15s; }
        .msg-dot span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes dotBounce {
            0%, 80%, 100% { opacity: 0.25; transform: scale(0.7); }
            40% { opacity: 1; transform: scale(1.2); }
        }

        .msg--success {
            background: var(--accent-dim);
            border: 1px solid var(--accent-ring);
            color: var(--accent);
        }
        .msg--error {
            background: var(--danger-dim);
            border: 1px solid var(--danger-ring);
            color: var(--danger);
        }

        /* 减少动效偏好 */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        @media (max-width: 500px) {
            .card { padding: 28px 20px 24px; }
            .header-icon { width: 42px; height: 42px; font-size: 18px; }
            .header-text h3 { font-size: 16px; }
            .drop-zone { padding: 26px 16px; }
        }
    </style>
</head>
<body>

    <div class="bg-blob bg-blob--1"></div>
    <div class="bg-blob bg-blob--2"></div>
    <div class="bg-blob bg-blob--3"></div>
    <div class="bg-grid"></div>
    <div class="particles" id="particles"></div>

    <div class="card">
        <div class="header">
            <div class="header-icon">
                <i class="fa-solid fa-puzzle-piece"></i>
            </div>
            <div class="header-text">
                <h3>本地插件安装</h3>
                <span>离线部署</span>
            </div>
        </div>

        <form id="upload-form" enctype="multipart/form-data">
            <div class="drop-zone" id="dropZone" role="button" tabindex="0" aria-label="点击或拖拽上传插件压缩包">
                <div class="drop-zone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <div class="drop-zone-label">拖拽文件到此处，或 <strong>点击选择</strong></div>
                <div class="drop-zone-hint">仅支持 .zip 格式</div>
                <input type="file" name="file" accept=".zip" required class="file-input-hidden" id="fileInput">
            </div>

            <div class="file-name" id="fileName">
                <i class="fa-solid fa-file-zipper"></i>
                <span class="file-name-text" id="fileNameText"></span>
                <button type="button" class="file-name-clear" id="fileClear" aria-label="移除文件"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit" disabled>
                <span class="spinner"></span>
                <span class="btn-text">开始安装</span>
            </button>
        </form>

        <div class="tip">
            <i class="fa-solid fa-shield-halved"></i>
            <span>请选择 FastAdmin 标准插件压缩包，将会直接进行上传安装。</span>
        </div>

        <div class="msg" id="msg"></div>
    </div>

    <script>
        /* 粒子生成 */
        (function() {
            var container = document.getElementById('particles');
            for (var i = 0; i < 18; i++) {
                var p = document.createElement('div');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.top = Math.random() * 100 + '%';
                p.style.animationDelay = (Math.random() * 5) + 's';
                p.style.animationDuration = (3.5 + Math.random() * 3) + 's';
                container.appendChild(p);
            }
        })();

        /* 元素引用 */
        var form = document.getElementById('upload-form');
        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('fileInput');
        var fileNameEl = document.getElementById('fileName');
        var fileNameText = document.getElementById('fileNameText');
        var fileClear = document.getElementById('fileClear');
        var btnSubmit = document.getElementById('btnSubmit');
        var msg = document.getElementById('msg');

        /* 点击上传区打开文件选择 */
        dropZone.addEventListener('click', function() {
            fileInput.click();
        });
        dropZone.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        /* 拖拽交互 */
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        dropZone.addEventListener('dragleave', function() {
            dropZone.classList.remove('dragover');
        });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        /* 文件选择变化 */
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                handleFileSelect(fileInput.files[0]);
            }
        });

        function handleFileSelect(file) {
            if (!file.name.toLowerCase().endsWith('.zip')) {
                showMsg('error', '请选择 .zip 格式的文件');
                return;
            }
            fileNameText.textContent = file.name;
            fileNameEl.classList.add('show');
            dropZone.classList.add('has-file');
            btnSubmit.disabled = false;
            hideMsg();
        }

        /* 清除已选文件 */
        fileClear.addEventListener('click', function(e) {
            e.stopPropagation();
            fileInput.value = '';
            fileNameEl.classList.remove('show');
            dropZone.classList.remove('has-file');
            btnSubmit.disabled = true;
            hideMsg();
        });

        /* 消息展示 */
        function showMsg(type, text) {
            msg.className = 'msg show';
            if (type === 'loading') {
                msg.classList.add('msg--loading');
                msg.innerHTML = '<span class="msg-dot"><span></span><span></span><span></span></span> ' + text;
            } else if (type === 'success') {
                msg.classList.add('msg--success');
                msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + text;
            } else {
                msg.classList.add('msg--error');
                msg.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + text;
            }
        }

        function hideMsg() {
            msg.className = 'msg';
            msg.innerHTML = '';
        }

        /* 表单提交 — 核心逻辑保持与原始代码一致 */
        form.onsubmit = async function(e) {
            e.preventDefault();
            var formData = new FormData(this);

            btnSubmit.classList.add('loading');
            btnSubmit.disabled = true;
            showMsg('loading', '安装中');

            try {
                var resp = await fetch('', { method: 'POST', body: formData });
                var result = await resp.json();
                if (result.code === 1) {
                    showMsg('success', result.msg);
                } else {
                    showMsg('error', result.msg);
                }
            } catch (err) {
                showMsg('error', '请求失败：' + err);
            } finally {
                btnSubmit.classList.remove('loading');
                btnSubmit.disabled = false;
            }
        };
    </script>
</body>
</html>

HTML;
        throw new HttpResponseException(response($html));
    }

    /**
     * 执行安装
     */
    private function doInstall()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 0, 'msg' => '请选择插件压缩包']);
        }

        // 使用官方的插件备份目录存放临时文件
        $tmpDir = Service::getAddonsBackupDir();
        try {
            $uploadFile = $file->rule('uniqid')->validate(['size' => 102400000, 'ext' => 'zip'])->move($tmpDir);
            if (!$uploadFile) {
                return json(['code' => 0, 'msg' => $file->getError()]);
            }
            $tmpFile = $tmpDir . $uploadFile->getSaveName();

            // 从压缩包中读取插件标识
            $zip = new ZipFile();
            try {
                $zip->openFile($tmpFile);
                $info = $zip->getEntryContents('info.ini');
                $config = parse_ini_string($info);
                $name = $config['name'] ?? '';
            } catch (\Exception $e) {
                @unlink($tmpFile);
                return json(['code' => 0, 'msg' => '无法读取插件信息，压缩包可能已损坏']);
            } finally {
                $zip->close();
            }

            if (!$name || !preg_match('/^[a-zA-Z0-9]+$/', $name)) {
                @unlink($tmpFile);
                return json(['code' => 0, 'msg' => '插件标识不合法']);
            }

            // 调用官方安装方法（内部不含验证，且会启用、复制资源、导入SQL等）
            // 第二个参数 true 表示覆盖安装，可自行调整
            $result = Service::install($name, true, [], $tmpFile);
            // Service::install 已经自动清理了临时文件

            return json([
                'code' => 1,
                'msg' => "插件 {$result['title']} 安装成功！",
                'data' => $result
            ]);
        } catch (AddonException $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}