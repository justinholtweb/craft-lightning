/* ============================================================
   Lightning — PageSpeed Insights for Craft CMS
   ============================================================ */
(function () {
    'use strict';

    // ---- DOM Helpers ----

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                if (key === 'className') {
                    node.className = attrs[key];
                } else if (key === 'textContent') {
                    node.textContent = attrs[key];
                } else {
                    node.setAttribute(key, attrs[key]);
                }
            });
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (child) {
                if (typeof child === 'string') {
                    node.appendChild(document.createTextNode(child));
                } else if (child) {
                    node.appendChild(child);
                }
            });
        }
        return node;
    }

    function clearChildren(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    // ---- Helpers ----

    function scoreClass(score) {
        if (score >= 90) return 'good';
        if (score >= 50) return 'average';
        return 'poor';
    }

    function createGauge(score, size) {
        var cls = 'lightning-gauge';
        if (score === null || score === undefined) {
            return el('span', { className: cls + ' lightning-gauge--poor' }, '—');
        }
        cls += ' lightning-gauge--' + scoreClass(score);
        if (size === 'sm') cls += ' lightning-gauge--sm';
        return el('span', { className: cls }, String(score));
    }

    function createDot(score) {
        return el('span', { className: 'lightning-dot lightning-dot--' + scoreClass(score) });
    }

    function formatSavings(ms) {
        if (ms >= 1000) return (ms / 1000).toFixed(1) + ' s';
        return ms + ' ms';
    }

    // ---- Render Strategy Results ----

    function buildStrategyResults(data, size) {
        var frag = document.createDocumentFragment();

        // Performance score gauge
        var scoresDiv = el('div', { className: 'lightning-scores' }, [
            el('div', { className: 'lightning-score-col' }, [
                createGauge(data.performanceScore, size),
                el('label', null, 'Performance'),
            ]),
        ]);
        frag.appendChild(scoresDiv);

        // CWV Metrics table
        var metrics = data.metrics || {};
        var metricLabels = {
            fcp: 'First Contentful Paint',
            lcp: 'Largest Contentful Paint',
            tbt: 'Total Blocking Time',
            cls: 'Cumulative Layout Shift',
            si: 'Speed Index',
        };

        var thead = el('thead', null, [
            el('tr', null, [
                el('th', null, 'Metric'),
                el('th', null, 'Value'),
            ]),
        ]);

        var tbodyRows = [];
        Object.keys(metricLabels).forEach(function (key) {
            var m = metrics[key];
            if (!m) return;
            var labelCell = el('td', { className: 'lightning-metric-label' }, [
                createDot(m.score),
                metricLabels[key],
            ]);
            var valueCell = el('td', { className: 'lightning-metric-value' }, m.displayValue || '—');
            tbodyRows.push(el('tr', null, [labelCell, valueCell]));
        });

        var tbody = el('tbody', null, tbodyRows);
        frag.appendChild(el('table', { className: 'lightning-metrics' }, [thead, tbody]));

        // Opportunities
        if (data.opportunities && data.opportunities.length > 0) {
            frag.appendChild(el('div', { className: 'lightning-section-title' }, 'Opportunities'));
            data.opportunities.forEach(function (opp) {
                var children = [
                    el('span', { className: 'lightning-opportunity-title' }, opp.title),
                ];
                if (opp.savingsMs) {
                    children.push(el('span', { className: 'lightning-opportunity-savings' }, '−' + formatSavings(opp.savingsMs)));
                }
                if (opp.description) {
                    children.push(el('div', { className: 'lightning-opportunity-desc' }, opp.description));
                }
                frag.appendChild(el('div', { className: 'lightning-opportunity' }, children));
            });
        }

        // Diagnostics
        if (data.diagnostics && data.diagnostics.length > 0) {
            frag.appendChild(el('div', { className: 'lightning-section-title' }, 'Diagnostics'));
            data.diagnostics.forEach(function (diag) {
                var children = [
                    el('span', { className: 'lightning-opportunity-title' }, diag.title),
                ];
                if (diag.displayValue) {
                    children.push(el('span', { className: 'lightning-opportunity-savings' }, diag.displayValue));
                }
                if (diag.description) {
                    children.push(el('div', { className: 'lightning-opportunity-desc' }, diag.description));
                }
                frag.appendChild(el('div', { className: 'lightning-opportunity' }, children));
            });
        }

        return frag;
    }

    // ---- Render Full Results with Tabs ----

    function buildResults(data, size) {
        var hasMobile = data.mobile && !data.mobile.error;
        var hasDesktop = data.desktop && !data.desktop.error;

        if (!hasMobile && !hasDesktop) {
            return el('div', { className: 'lightning-error' }, 'No results available.');
        }

        // Single strategy — no tabs
        if (hasMobile && !hasDesktop) {
            var wrap = document.createDocumentFragment();
            wrap.appendChild(buildStrategyResults(data.mobile, size));
            return wrap;
        }
        if (!hasMobile && hasDesktop) {
            var wrap2 = document.createDocumentFragment();
            wrap2.appendChild(buildStrategyResults(data.desktop, size));
            return wrap2;
        }

        // Both — tabbed interface
        var container = document.createDocumentFragment();

        var mobileTab = el('button', { type: 'button', className: 'lightning-tab active', 'data-panel': 'mobile' }, 'Mobile');
        var desktopTab = el('button', { type: 'button', className: 'lightning-tab', 'data-panel': 'desktop' }, 'Desktop');
        container.appendChild(el('div', { className: 'lightning-tabs' }, [mobileTab, desktopTab]));

        var mobilePanel = el('div', { className: 'lightning-tab-panel active', 'data-panel': 'mobile' });
        mobilePanel.appendChild(buildStrategyResults(data.mobile, size));
        container.appendChild(mobilePanel);

        var desktopPanel = el('div', { className: 'lightning-tab-panel', 'data-panel': 'desktop' });
        desktopPanel.appendChild(buildStrategyResults(data.desktop, size));
        container.appendChild(desktopPanel);

        return container;
    }

    // ---- Tab Switching ----

    function bindTabs(container) {
        var tabs = container.querySelectorAll('.lightning-tab');
        var panels = container.querySelectorAll('.lightning-tab-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-panel');
                tabs.forEach(function (t) { t.classList.remove('active'); });
                panels.forEach(function (p) { p.classList.remove('active'); });
                tab.classList.add('active');
                container.querySelector('.lightning-tab-panel[data-panel="' + target + '"]').classList.add('active');
            });
        });
    }

    // ---- API Call ----

    function runAudit(url, strategy, callback) {
        var data = {
            url: url,
            strategy: strategy || 'both',
        };

        Craft.sendActionRequest('POST', 'lightning/api/run-audit', { data: data })
            .then(function (response) {
                callback(null, response.data);
            })
            .catch(function (error) {
                var msg = 'Audit failed.';
                if (error.response && error.response.data && error.response.data.error) {
                    msg = error.response.data.error;
                }
                callback(msg, null);
            });
    }

    // ---- Shared Audit Handler ----

    function handleAuditClick(url, strategy, resultsEl, loadingEl, errorEl, actionsEl, size) {
        loadingEl.style.display = '';
        resultsEl.style.display = 'none';
        errorEl.style.display = 'none';
        actionsEl.style.display = 'none';

        runAudit(url, strategy, function (err, data) {
            loadingEl.style.display = 'none';

            if (err) {
                errorEl.textContent = err;
                errorEl.style.display = '';
                actionsEl.style.display = '';
                return;
            }

            clearChildren(resultsEl);
            resultsEl.appendChild(buildResults(data.data || data, size));
            resultsEl.style.display = '';
            actionsEl.style.display = '';
            bindTabs(resultsEl);
        });
    }

    // ---- Dashboard Widget ----

    function initWidgets() {
        var widgets = document.querySelectorAll('.lightning-widget');
        widgets.forEach(function (widget) {
            var url = widget.getAttribute('data-url');
            var strategy = widget.getAttribute('data-strategy') || 'both';
            if (!url) return;

            var resultsEl = widget.querySelector('.lightning-results');
            var loadingEl = widget.querySelector('.lightning-loading');
            var errorEl = widget.querySelector('.lightning-error');
            var actionsEl = widget.querySelector('.lightning-actions');
            var runBtn = widget.querySelector('.lightning-run-btn');

            runBtn.addEventListener('click', function () {
                handleAuditClick(url, strategy, resultsEl, loadingEl, errorEl, actionsEl, null);
            });
        });
    }

    // ---- Entry Sidebar ----

    function initSidebars() {
        var sidebars = document.querySelectorAll('.lightning-sidebar');
        sidebars.forEach(function (sidebar) {
            var url = sidebar.getAttribute('data-url');
            if (!url) return;

            var resultsEl = sidebar.querySelector('.lightning-sidebar-results');
            var loadingEl = sidebar.querySelector('.lightning-sidebar-loading');
            var errorEl = sidebar.querySelector('.lightning-sidebar-error');
            var actionsEl = sidebar.querySelector('.lightning-sidebar-actions');
            var runBtn = sidebar.querySelector('.lightning-sidebar-run-btn');

            runBtn.addEventListener('click', function () {
                handleAuditClick(url, 'both', resultsEl, loadingEl, errorEl, actionsEl, 'sm');
            });
        });
    }

    // ---- Init ----

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initWidgets();
            initSidebars();
        });
    } else {
        initWidgets();
        initSidebars();
    }

})();
