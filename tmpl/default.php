<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_launch_audit
 *
 * @copyright   (C) 2026 Your Name or Company. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
/** @var \Joomla\Registry\Registry $params */
/** @var object $module */
/** @var array<string, mixed> $auditData */

$wa = $app->getDocument()->getWebAssetManager();
$wa->useStyle('mod_launch_audit.admin')->useScript('mod_launch_audit.admin');

// Pass options to JavaScript
$doc = $app->getDocument();
$doc->addScriptOptions('mod_launch_audit', [
    'ajaxUrl'   => Route::_('index.php?option=com_ajax&module=launch_audit&method=fixConfig&format=json'),
    'csrfToken' => Session::getFormToken(),
    'moduleId'  => (int) $module->id,
]);

// Register translation strings for JavaScript
Text::script('MOD_LAUNCH_AUDIT_JS_CONFIRM_FIX');
Text::script('MOD_LAUNCH_AUDIT_JS_FIXING');
Text::script('MOD_LAUNCH_AUDIT_JS_FIX_SUCCESS');
Text::script('MOD_LAUNCH_AUDIT_JS_FIX_ERROR');
Text::script('MOD_LAUNCH_AUDIT_JS_NETWORK_ERROR');
Text::script('MOD_LAUNCH_AUDIT_STATUS_PASS');
Text::script('MOD_LAUNCH_AUDIT_STATUS_WARNING');
Text::script('MOD_LAUNCH_AUDIT_STATUS_FAIL');
Text::script('MOD_LAUNCH_AUDIT_STATUS_DISABLED');
Text::script('MOD_LAUNCH_AUDIT_ERROR_REPORTING_DEFAULT');
Text::script('MOD_LAUNCH_AUDIT_ROBOTS_DEFAULT_INDEX');

$showScore          = (bool) $params->get('show_score', 1);
$showCategoryFilter = (bool) $params->get('show_category_filter', 1);

$score        = (int) ($auditData['score'] ?? 0);
$passCount    = (int) ($auditData['passCount'] ?? 0);
$warningCount = (int) ($auditData['warningCount'] ?? 0);
$failCount    = (int) ($auditData['failCount'] ?? 0);
$totalCount   = (int) ($auditData['totalCount'] ?? 0);
$categories   = (array) ($auditData['categories'] ?? []);
$moduleId     = (int) $module->id;

// Determine score banner color & label
if ($score >= 90) {
    $scoreBadgeClass = 'bg-success';
    $scoreText       = Text::_('MOD_LAUNCH_AUDIT_SCORE_EXCELLENT');
    $progressColor   = 'var(--bs-success)';
} elseif ($score >= 70) {
    $scoreBadgeClass = 'bg-warning text-dark';
    $scoreText       = Text::_('MOD_LAUNCH_AUDIT_SCORE_WARNING');
    $progressColor   = 'var(--bs-warning)';
} else {
    $scoreBadgeClass = 'bg-danger';
    $scoreText       = Text::_('MOD_LAUNCH_AUDIT_SCORE_CRITICAL');
    $progressColor   = 'var(--bs-danger)';
}

$renderCheckItem = static function (array $check): string {
    $status      = $check['status'] ?? 'warning';
    $statusClass = match ($status) {
        'pass'    => 'status-pass border-start-success',
        'warning' => 'status-warning border-start-warning',
        'fail'    => 'status-fail border-start-danger',
        default   => 'status-warning',
    };

    $badgeClass = match ($status) {
        'pass'    => 'bg-success text-white',
        'warning' => 'bg-warning text-dark',
        'fail'    => 'bg-danger text-white',
        default   => 'bg-secondary text-white',
    };

    $statusLabel = match ($status) {
        'pass'    => Text::_('MOD_LAUNCH_AUDIT_STATUS_PASS'),
        'warning' => Text::_('MOD_LAUNCH_AUDIT_STATUS_WARNING'),
        'fail'    => Text::_('MOD_LAUNCH_AUDIT_STATUS_FAIL'),
        default   => ucfirst($status),
    };

    $iconClass = match ($status) {
        'pass'    => 'icon-check',
        'warning' => 'icon-warning',
        'fail'    => 'icon-times',
        default   => 'icon-info',
    };

    ob_start();
    ?>
    <div class="audit-item card border-start border-4 <?php echo $statusClass; ?> p-3"
         data-check-id="<?php echo htmlspecialchars($check['id'], ENT_QUOTES, 'UTF-8'); ?>"
         data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <span class="badge <?php echo $badgeClass; ?> p-0 d-inline-flex align-items-center justify-content-center audit-badge"
                      title="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                    <span class="visually-hidden"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <div>
                    <h6 class="mb-0 fw-semibold audit-title">
                        <?php echo htmlspecialchars($check['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h6>
                    <div class="audit-meta small text-muted mt-1">
                        <span class="audit-current me-3">
                            <strong><?php echo Text::_('MOD_LAUNCH_AUDIT_CURRENT_LABEL'); ?>:</strong>
                            <span class="current-value"><?php echo htmlspecialchars($check['current'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <span class="audit-recommended">
                            <strong><?php echo Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_LABEL'); ?>:</strong>
                            <span><?php echo htmlspecialchars($check['recommended'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto audit-actions">
                <?php if (!empty($check['canQuickFix']) && !empty($check['fixKey'])) : ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary mod-launch-audit-fix-btn"
                            data-fix-key="<?php echo htmlspecialchars($check['fixKey'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-check-id="<?php echo htmlspecialchars($check['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="icon-wrench me-1" aria-hidden="true"></span>
                        <span class="btn-text"><?php echo Text::_('MOD_LAUNCH_AUDIT_BTN_QUICK_FIX'); ?></span>
                    </button>
                <?php endif; ?>

                <?php if (!empty($check['actionUrl'])) : ?>
                    <a href="<?php echo htmlspecialchars(Route::_($check['actionUrl']), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn btn-sm btn-subtle-secondary"
                       title="<?php echo Text::_('MOD_LAUNCH_AUDIT_BTN_ACTION'); ?>">
                        <span class="icon-cog" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('MOD_LAUNCH_AUDIT_BTN_ACTION'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<div id="mod-launch-audit-<?php echo $moduleId; ?>" class="mod-launch-audit card shadow-sm" data-module-id="<?php echo $moduleId; ?>">
    <div class="card-body p-3 p-md-4">
        <?php if ($showScore) : ?>
            <div class="mod-launch-audit-header mb-4">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-auto text-center">
                        <div class="mod-launch-audit-score-dial position-relative d-inline-flex align-items-center justify-content-center"
                             style="--score-pct: <?php echo $score; ?>%; --score-color: <?php echo $progressColor; ?>;">
                            <svg class="score-ring" viewBox="0 0 100 100" width="90" height="90" aria-hidden="true">
                                <circle class="score-ring-bg" cx="50" cy="50" r="42" fill="none" stroke-width="8"></circle>
                                <circle class="score-ring-progress" cx="50" cy="50" r="42" fill="none" stroke-width="8"
                                        stroke-dasharray="264"
                                        stroke-dashoffset="<?php echo (int) (264 - (264 * $score / 100)); ?>"></circle>
                            </svg>
                            <div class="mod-launch-audit-score-value text-center position-absolute">
                                <span class="score-number fw-bold"><?php echo $score; ?></span><span class="score-unit fs-6">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm">
                        <h4 class="card-title mb-1 d-flex align-items-center flex-wrap gap-2">
                            <span><?php echo Text::_('MOD_LAUNCH_AUDIT_TITLE'); ?></span>
                            <span class="badge <?php echo $scoreBadgeClass; ?> fs-6 score-badge">
                                <?php echo htmlspecialchars($scoreText, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </h4>
                        <p class="text-muted small mb-2">
                            <?php echo Text::sprintf('MOD_LAUNCH_AUDIT_SUBTITLE_COUNTS', $totalCount, $passCount, $warningCount, $failCount); ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mod-launch-audit-counts">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <span class="icon-check me-1" aria-hidden="true"></span>
                                <strong class="count-pass"><?php echo $passCount; ?></strong> <?php echo Text::_('MOD_LAUNCH_AUDIT_LABEL_PASSED'); ?>
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                <span class="icon-warning me-1" aria-hidden="true"></span>
                                <strong class="count-warning"><?php echo $warningCount; ?></strong> <?php echo Text::_('MOD_LAUNCH_AUDIT_LABEL_WARNINGS'); ?>
                            </span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                <span class="icon-times me-1" aria-hidden="true"></span>
                                <strong class="count-fail"><?php echo $failCount; ?></strong> <?php echo Text::_('MOD_LAUNCH_AUDIT_LABEL_FAILS'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showCategoryFilter && !empty($categories)) : ?>
            <ul class="nav nav-pills mod-launch-audit-nav mb-3 gap-1" id="audit-tabs-<?php echo $moduleId; ?>" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-all-<?php echo $moduleId; ?>" data-bs-toggle="pill"
                            data-bs-target="#pane-all-<?php echo $moduleId; ?>" type="button" role="tab"
                            aria-controls="pane-all-<?php echo $moduleId; ?>" aria-selected="true">
                        <?php echo Text::_('MOD_LAUNCH_AUDIT_TAB_ALL'); ?>
                        <span class="badge bg-secondary ms-1"><?php echo $totalCount; ?></span>
                    </button>
                </li>
                <?php foreach ($categories as $catKey => $category) : ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-<?php echo $catKey . '-' . $moduleId; ?>" data-bs-toggle="pill"
                                data-bs-target="#pane-<?php echo $catKey . '-' . $moduleId; ?>" type="button" role="tab"
                                aria-controls="pane-<?php echo $catKey . '-' . $moduleId; ?>" aria-selected="false">
                            <?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?>
                            <span class="badge bg-secondary ms-1"><?php echo \count($category['checks']); ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="tab-content" id="audit-content-<?php echo $moduleId; ?>">
            <div class="tab-pane fade show active" id="pane-all-<?php echo $moduleId; ?>" role="tabpanel"
                 aria-labelledby="tab-all-<?php echo $moduleId; ?>">
                <div class="audit-checklist d-flex flex-column gap-2">
                    <?php foreach ($auditData['checks'] as $check) : ?>
                        <?php echo $renderCheckItem($check); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($showCategoryFilter && !empty($categories)) : ?>
                <?php foreach ($categories as $catKey => $category) : ?>
                    <div class="tab-pane fade" id="pane-<?php echo $catKey . '-' . $moduleId; ?>" role="tabpanel"
                         aria-labelledby="tab-<?php echo $catKey . '-' . $moduleId; ?>">
                        <div class="audit-checklist d-flex flex-column gap-2">
                            <?php foreach ($category['checks'] as $check) : ?>
                                <?php echo $renderCheckItem($check); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
