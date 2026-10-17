/**
 * mod_launch_audit - Admin Security & Pre-Launch Checklist
 * Asynchronous Quick-Fix execution & DOM updates.
 */

(() => {
  'use strict';

  const initLaunchAudit = () => {
    const options = (window.Joomla && window.Joomla.getOptions('mod_launch_audit')) || {};
    const ajaxUrl = options.ajaxUrl || '';
    const csrfToken = options.csrfToken || '';

    if (!ajaxUrl || !csrfToken) {
      return;
    }

    const modules = document.querySelectorAll('.mod-launch-audit');

    modules.forEach((moduleContainer) => {
      const fixButtons = moduleContainer.querySelectorAll('.mod-launch-audit-fix-btn');

      fixButtons.forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();

          const fixKey = btn.dataset.fixKey;
          const checkId = btn.dataset.checkId;

          if (!fixKey) {
            return;
          }

          const confirmMsg = (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_JS_CONFIRM_FIX'))
            || 'Are you sure you want to apply this automatic fix?';

          if (!window.confirm(confirmMsg)) {
            return;
          }

          const originalContent = btn.innerHTML;
          const fixingText = (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_JS_FIXING')) || 'Applying fix...';

          btn.disabled = true;
          btn.classList.add('is-fixing');
          btn.innerHTML = `<span class="icon-wrench me-1" aria-hidden="true"></span><span class="btn-text">${fixingText}</span>`;

          try {
            const bodyParams = new URLSearchParams();
            bodyParams.append(csrfToken, '1');
            bodyParams.append('fix_key', fixKey);

            const response = await fetch(ajaxUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
              },
              body: bodyParams.toString(),
            });

            if (!response.ok) {
              const errData = await response.json().catch(() => null);
              const errMsg = (errData && errData.message)
                || (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_JS_FIX_ERROR'))
                || 'Failed to apply quick fix.';
              throw new Error(errMsg);
            }

            const json = await response.json();
            const resultData = json.data || json;

            const successMsg = (resultData && resultData.message)
              || (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_JS_FIX_SUCCESS'))
              || 'Check fixed successfully.';

            if (window.Joomla && typeof window.Joomla.renderMessages === 'function') {
              window.Joomla.renderMessages({ message: [successMsg] });
            }

            // Update all corresponding check elements across tabs
            const matchingItems = moduleContainer.querySelectorAll(`[data-check-id="${checkId}"]`);
            matchingItems.forEach((item) => {
              item.setAttribute('data-status', 'pass');
              item.classList.remove('status-warning', 'status-fail', 'border-start-warning', 'border-start-danger');
              item.classList.add('status-pass', 'border-start-success');

              const badge = item.querySelector('.audit-badge');
              if (badge) {
                badge.className = 'badge bg-success text-white p-2 d-inline-flex align-items-center justify-content-center audit-badge';
                const passText = (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_STATUS_PASS')) || 'Passed';
                badge.title = passText;
                badge.innerHTML = `<span class="icon-check" aria-hidden="true"></span><span class="visually-hidden">${passText}</span>`;
              }

              // Update current value text
              const currentValEl = item.querySelector('.current-value');
              if (currentValEl) {
                if (fixKey === 'debug' || fixKey === 'disable_compat6') {
                  currentValEl.textContent = 'Disabled';
                } else if (fixKey === 'error_reporting') {
                  currentValEl.textContent = 'Default';
                } else if (fixKey === 'robots') {
                  currentValEl.textContent = 'Index, Follow';
                }
              }

              // Remove the quick fix button since it has passed
              const fixBtn = item.querySelector('.mod-launch-audit-fix-btn');
              if (fixBtn) {
                fixBtn.remove();
              }
            });

            // Recalculate Score and Statistics
            updateModuleStats(moduleContainer);

          } catch (error) {
            const errorMsg = error.message
              || (window.Joomla && window.Joomla.Text._('MOD_LAUNCH_AUDIT_JS_NETWORK_ERROR'))
              || 'Network error occurred while applying fix.';

            if (window.Joomla && typeof window.Joomla.renderMessages === 'function') {
              window.Joomla.renderMessages({ error: [errorMsg] });
            }

            btn.disabled = false;
            btn.classList.remove('is-fixing');
            btn.innerHTML = originalContent;
          }
        });
      });
    });
  };

  /**
   * Recalculates statistics, score ring, and badges in the module container.
   */
  const updateModuleStats = (moduleContainer) => {
    // Only count items in the main pane to prevent double counting
    const mainPane = moduleContainer.querySelector('.tab-pane.active') || moduleContainer;
    const allItems = mainPane.querySelectorAll('.audit-item');

    let passCount = 0;
    let warningCount = 0;
    let failCount = 0;

    allItems.forEach((item) => {
      const status = item.getAttribute('data-status');
      if (status === 'pass') {
        passCount++;
      } else if (status === 'warning') {
        warningCount++;
      } else if (status === 'fail') {
        failCount++;
      }
    });

    const totalCount = allItems.length || 10;
    const newScore = Math.round(((passCount * 10) + (warningCount * 5)) / (totalCount * 10) * 100);

    // Update counters
    const countPassEl = moduleContainer.querySelector('.count-pass');
    const countWarningEl = moduleContainer.querySelector('.count-warning');
    const countFailEl = moduleContainer.querySelector('.count-fail');

    if (countPassEl) countPassEl.textContent = String(passCount);
    if (countWarningEl) countWarningEl.textContent = String(warningCount);
    if (countFailEl) countFailEl.textContent = String(failCount);

    // Update score number
    const scoreNumEl = moduleContainer.querySelector('.score-number');
    if (scoreNumEl) {
      scoreNumEl.textContent = String(newScore);
    }

    // Determine color
    let progressColor = 'var(--bs-danger)';
    let badgeClass = 'bg-danger';

    if (newScore >= 90) {
      progressColor = 'var(--bs-success)';
      badgeClass = 'bg-success';
    } else if (newScore >= 70) {
      progressColor = 'var(--bs-warning)';
      badgeClass = 'bg-warning text-dark';
    }

    // Update Dial Ring
    const scoreDial = moduleContainer.querySelector('.mod-launch-audit-score-dial');
    if (scoreDial) {
      scoreDial.style.setProperty('--score-pct', `${newScore}%`);
      scoreDial.style.setProperty('--score-color', progressColor);

      const progressCircle = scoreDial.querySelector('.score-ring-progress');
      if (progressCircle) {
        const offset = Math.round(264 - (264 * newScore / 100));
        progressCircle.setAttribute('stroke-dashoffset', String(offset));
      }
    }

    // Update Score Badge
    const scoreBadge = moduleContainer.querySelector('.score-badge');
    if (scoreBadge) {
      scoreBadge.className = `badge ${badgeClass} fs-6 score-badge`;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLaunchAudit);
  } else {
    initLaunchAudit();
  }
})();
