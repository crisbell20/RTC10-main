/**
 * Repeating examinee watermark over the full question area.
 */
(function (window, document) {
    'use strict';

    function escapeXml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function resolveWatermarkText(source) {
        if (source && source.getAttribute) {
            const fromDom = (source.getAttribute('data-watermark-text') || '').trim();
            if (fromDom) {
                return fromDom;
            }
        }

        const cfg = window.EXAM_WATERMARK || {};
        const parts = [cfg.rank, cfg.name, cfg.badge_number]
            .map((part) => String(part || '').trim())
            .filter(Boolean);
        let text = parts.join(', ');
        const suffix = String(cfg.suffix || '').trim();
        if (suffix) {
            text = text ? `${text}, ${suffix}` : suffix;
        }
        return text || 'PNP RTC X';
    }

    function buildWatermarkPattern(text) {
        const label = String(text || 'PNP RTC X').replace(/\s+/g, ' ').trim().toUpperCase();
        const tileW = Math.min(640, Math.max(420, Math.round(label.length * 9 + 200)));
        const tileH = 180;
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${tileW}" height="${tileH}" viewBox="0 0 ${tileW} ${tileH}">`
            + `<text x="${tileW / 2}" y="${tileH / 2}" fill="rgba(30, 58, 138, 0.15)" font-size="12" font-weight="600"`
            + ` letter-spacing="1.2" text-anchor="middle" dominant-baseline="middle"`
            + ` font-family="Segoe UI, system-ui, sans-serif"`
            + ` transform="rotate(-22 ${tileW / 2} ${tileH / 2})">${escapeXml(label)}</text>`
            + `</svg>`;

        return {
            image: `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`,
            width: tileW,
            height: tileH,
        };
    }

    function applyWatermarkPattern(target, pattern) {
        if (!target || !pattern) {
            return;
        }

        target.style.setProperty('--exam-watermark-image', `${pattern.image}, ${pattern.image}`);
        target.style.setProperty('--exam-watermark-size', `${pattern.width}px ${pattern.height}px`);
        target.style.setProperty(
            '--exam-watermark-position',
            `0 0, ${Math.round(pattern.width / 2)}px ${Math.round(pattern.height / 2)}px`
        );
        target.classList.add('is-watermarked');
    }

    function paintExamineeWatermark(layerOrShield) {
        if (!layerOrShield) {
            return;
        }

        const layer = layerOrShield.classList.contains('exam-watermark-layer')
            ? layerOrShield
            : (layerOrShield.querySelector('.exam-watermark-layer') || layerOrShield);
        const shield = layer.closest('.watermark-shield') || layer.parentElement || layer;
        const pattern = buildWatermarkPattern(resolveWatermarkText(layer));

        applyWatermarkPattern(shield, pattern);
        applyWatermarkPattern(layer, pattern);
        layer.replaceChildren();
    }

    function paintAllExamineeWatermarks() {
        document.querySelectorAll('.exam-watermark-layer').forEach((layer) => {
            paintExamineeWatermark(layer);
        });
    }

    window.paintExamineeWatermark = paintExamineeWatermark;
    window.paintAllExamineeWatermarks = paintAllExamineeWatermarks;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', paintAllExamineeWatermarks);
    } else {
        paintAllExamineeWatermarks();
    }
})(window, document);
