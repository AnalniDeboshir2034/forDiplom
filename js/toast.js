document.addEventListener('DOMContentLoaded', function () {
    var container = document.createElement('div');
    container.className = 'app-toast-container';
    document.body.appendChild(container);

    var style = document.createElement('style');
    style.textContent = '' +
        '.app-toast-container{position:fixed;right:18px;top:18px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;}' +
        '.app-toast{position:relative;display:flex;align-items:flex-start;gap:10px;max-width:380px;padding:12px 14px 14px;border-radius:14px;border:1px solid #fed7aa;' +
        'background:#ffffff;color:#1f2937;box-shadow:0 10px 24px rgba(15,23,42,.14);' +
        'font-size:14px;line-height:1.4;opacity:0;transform:translateY(-10px) scale(.98);transition:all .24s ease;}' +
        '.app-toast.is-visible{opacity:1;transform:translateY(0) scale(1);}' +
        '.app-toast__icon{width:20px;flex:0 0 20px;font-size:16px;line-height:20px;text-align:center;}' +
        '.app-toast__text{padding-right:4px;}' +
        '.app-toast::after{content:\"\";position:absolute;left:10px;right:10px;bottom:6px;height:3px;border-radius:999px;background:#ffedd5;}' +
        '.app-toast__bar{position:absolute;left:10px;right:10px;bottom:6px;height:3px;border-radius:999px;transform-origin:left center;animation:toastBar 1.8s linear forwards;background:#f97316;}' +
        '.app-toast--success{border-color:#bbf7d0;background:#f0fdf4;}' +
        '.app-toast--success .app-toast__bar{background:#22c55e;}' +
        '.app-toast--error{border-color:#fecaca;background:#fef2f2;}' +
        '.app-toast--error .app-toast__bar{background:#ef4444;}' +
        '.app-toast--info{border-color:#fed7aa;background:#fff7ed;}' +
        '.app-toast--info .app-toast__bar{background:#f97316;}' +
        '@keyframes toastBar{from{transform:scaleX(1);}to{transform:scaleX(0);}}' +
        '@media (max-width:640px){.app-toast-container{left:12px;right:12px;top:12px;}.app-toast{max-width:none;}}';
    document.head.appendChild(style);

    function show(message, type) {
        if (!message) return;
        var toast = document.createElement('div');
        toast.className = 'app-toast app-toast--' + (type || 'info');

        var icon = document.createElement('span');
        icon.className = 'app-toast__icon';
        icon.textContent = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');

        var text = document.createElement('div');
        text.className = 'app-toast__text';
        text.textContent = String(message);

        var bar = document.createElement('span');
        bar.className = 'app-toast__bar';

        toast.appendChild(icon);
        toast.appendChild(text);
        toast.appendChild(bar);
        container.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 220);
        }, 1800);
    }

    window.AppToast = {
        show: show
    };
});
