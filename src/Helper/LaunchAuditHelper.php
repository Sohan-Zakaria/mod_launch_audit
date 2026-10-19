<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_launch_audit
 *
 * @copyright   (C) 2026 Your Name or Company. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace YourVendor\Module\LaunchAudit\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Helper class for mod_launch_audit.
 */
class LaunchAuditHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Executes the 10 audit checks and returns calculated score and structured results.
     *
     * @param   Registry                 $params  Module parameters.
     * @param   CMSApplicationInterface  $app     The CMS Application instance.
     *
     * @return  array<string, mixed>
     */
    public function getAuditResults(Registry $params, CMSApplicationInterface $app): array
    {
        $db = $this->getDatabase();

        if ($db === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $this->setDatabase($db);
        }

        $checks = [];

        // 1. Debug Mode
        $debugVal = $app->get('debug', 0);
        $isDebug  = ($debugVal === 1 || $debugVal === '1' || $debugVal === true);
        $checks['debug'] = [
            'id'          => 'debug',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_DEBUG_TITLE'),
            'status'      => $isDebug ? 'fail' : 'pass',
            'current'     => $isDebug ? Text::_('MOD_LAUNCH_AUDIT_STATUS_ENABLED') : Text::_('MOD_LAUNCH_AUDIT_STATUS_DISABLED'),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_DISABLED'),
            'actionUrl'   => 'index.php?option=com_config',
            'canQuickFix' => $isDebug,
            'fixKey'      => 'debug',
        ];

        // 2. Error Reporting
        $errRep   = (string) $app->get('error_reporting', 'default');
        $errLower = strtolower(trim($errRep));
        if ($errLower === 'default' || $errLower === 'none' || $errLower === '') {
            $errStatus = 'pass';
        } elseif ($errLower === 'simple') {
            $errStatus = 'warning';
        } else {
            $errStatus = 'fail';
        }

        $errCurrentText = match ($errLower) {
            'none'        => Text::_('MOD_LAUNCH_AUDIT_ERROR_REPORTING_NONE'),
            'simple'      => Text::_('MOD_LAUNCH_AUDIT_ERROR_REPORTING_SIMPLE'),
            'maximum'     => Text::_('MOD_LAUNCH_AUDIT_ERROR_REPORTING_MAXIMUM'),
            'development' => Text::_('MOD_LAUNCH_AUDIT_ERROR_REPORTING_DEVELOPMENT'),
            default       => Text::_('MOD_LAUNCH_AUDIT_ERROR_REPORTING_DEFAULT'),
        };

        $checks['error_reporting'] = [
            'id'          => 'error_reporting',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_ERROR_REPORTING_TITLE'),
            'status'      => $errStatus,
            'current'     => $errCurrentText,
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_ERROR_REPORTING'),
            'actionUrl'   => 'index.php?option=com_config',
            'canQuickFix' => $errStatus !== 'pass',
            'fixKey'      => 'error_reporting',
        ];

        // 3. J6.2 Critical Extension Security Updates
        $updateColumns = [];
        try {
            $updateColumns = $db->getTableColumns('#__updates') ?: [];
        } catch (\Throwable) {
            $updateColumns = [];
        }

        $hasSecuritySeverity = \array_key_exists('security_severity', $updateColumns);
        $totalUpdates        = 0;
        $securityUpdates     = 0;

        try {
            if ($hasSecuritySeverity) {
                $query = $db->getQuery(true)
                    ->select([
                        'COUNT(' . $db->quoteName('update_id') . ') AS ' . $db->quoteName('total_count'),
                        'SUM(CASE WHEN ' . $db->quoteName('security_severity') . ' > 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('sec_count'),
                    ])
                    ->from($db->quoteName('#__updates'));
                $db->setQuery($query);
                $row = $db->loadAssoc() ?: [];
                $totalUpdates    = (int) ($row['total_count'] ?? 0);
                $securityUpdates = (int) ($row['sec_count'] ?? 0);
            } else {
                $query = $db->getQuery(true)
                    ->select('COUNT(' . $db->quoteName('update_id') . ')')
                    ->from($db->quoteName('#__updates'));
                $db->setQuery($query);
                $totalUpdates = (int) $db->loadResult();
            }
        } catch (\Throwable) {
            $totalUpdates    = 0;
            $securityUpdates = 0;
        }

        if ($securityUpdates > 0) {
            $updatesStatus = 'fail';
            $updatesDesc   = Text::sprintf('MOD_LAUNCH_AUDIT_UPDATES_CRITICAL_FOUND', $securityUpdates, $totalUpdates);
        } elseif ($totalUpdates > 0) {
            $updatesStatus = 'warning';
            $updatesDesc   = Text::sprintf('MOD_LAUNCH_AUDIT_UPDATES_PENDING_FOUND', $totalUpdates);
        } else {
            $updatesStatus = 'pass';
            $updatesDesc   = Text::_('MOD_LAUNCH_AUDIT_UPDATES_ALL_CURRENT');
        }

        $checks['security_updates'] = [
            'id'          => 'security_updates',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_SECURITY_UPDATES_TITLE'),
            'status'      => $updatesStatus,
            'current'     => $updatesDesc,
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_NO_UPDATES'),
            'actionUrl'   => 'index.php?option=com_installer&view=update',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // 4. Automated Core Updates (TUF)
        $tufEnabled = false;
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_joomlaupdate'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query);
            $rawParams = $db->loadResult();
            $updateParams = new Registry($rawParams ?: '');
            $autoUpdate = (int) $updateParams->get('auto_update', $updateParams->get('automated_updates', 0));
            $tufEnabled = ($autoUpdate === 1);
        } catch (\Throwable) {
            $tufEnabled = false;
        }

        $checks['tuf_updates'] = [
            'id'          => 'tuf_updates',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_TUF_TITLE'),
            'status'      => $tufEnabled ? 'pass' : 'warning',
            'current'     => $tufEnabled ? Text::_('MOD_LAUNCH_AUDIT_STATUS_ENABLED') : Text::_('MOD_LAUNCH_AUDIT_STATUS_DISABLED'),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_TUF_ENABLED'),
            'actionUrl'   => 'index.php?option=com_config&view=component&component=com_joomlaupdate',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // 5. Super User MFA Coverage
        $totalSuperUsers = 0;
        $mfaCoveredUsers = 0;

        try {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('u.id'),
                    'COUNT(' . $db->quoteName('m.id') . ') AS ' . $db->quoteName('mfa_count'),
                ])
                ->from($db->quoteName('#__users', 'u'))
                ->join('INNER', $db->quoteName('#__user_usergroup_map', 'map') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('map.user_id'))
                ->join('LEFT', $db->quoteName('#__user_mfa', 'm') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id'))
                ->where($db->quoteName('map.group_id') . ' = 8')
                ->where($db->quoteName('u.block') . ' = 0')
                ->group($db->quoteName('u.id'));
            $db->setQuery($query);
            $userRows = $db->loadAssocList() ?: [];
            $totalSuperUsers = \count($userRows);

            foreach ($userRows as $userRow) {
                if ((int) ($userRow['mfa_count'] ?? 0) > 0) {
                    $mfaCoveredUsers++;
                }
            }
        } catch (\Throwable) {
            $totalSuperUsers = 0;
            $mfaCoveredUsers = 0;
        }

        if ($totalSuperUsers === 0 || $mfaCoveredUsers === $totalSuperUsers) {
            $mfaStatus = 'pass';
        } elseif ($mfaCoveredUsers > 0) {
            $mfaStatus = 'warning';
        } else {
            $mfaStatus = 'fail';
        }

        $checks['mfa_coverage'] = [
            'id'          => 'mfa_coverage',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_MFA_TITLE'),
            'status'      => $mfaStatus,
            'current'     => Text::sprintf('MOD_LAUNCH_AUDIT_MFA_RATIO', $mfaCoveredUsers, $totalSuperUsers),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_MFA_ALL'),
            'actionUrl'   => 'index.php?option=com_users&view=users',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // 6. Backward Compatibility 6 Plugin (plg_behaviour_compat6)
        $compatEnabled = false;
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('enabled'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('behaviour'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('compat6'));
            $db->setQuery($query);
            $val = $db->loadResult();
            $compatEnabled = ($val !== null && (int) $val === 1);
        } catch (\Throwable) {
            $compatEnabled = false;
        }

        $checks['compat6_plugin'] = [
            'id'          => 'compat6_plugin',
            'category'    => 'system',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_COMPAT6_TITLE'),
            'status'      => $compatEnabled ? 'warning' : 'pass',
            'current'     => $compatEnabled ? Text::_('MOD_LAUNCH_AUDIT_STATUS_ENABLED') : Text::_('MOD_LAUNCH_AUDIT_STATUS_DISABLED'),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_COMPAT6_DISABLED'),
            'actionUrl'   => 'index.php?option=com_plugins&filter[folder]=behaviour',
            'canQuickFix' => $compatEnabled,
            'fixKey'      => 'disable_compat6',
        ];

        // 7. Search Engine Visibility (robots)
        $robots       = (string) $app->get('robots', '');
        $hasNoIndex   = (stripos($robots, 'noindex') !== false);
        $robotsStatus = $hasNoIndex ? 'fail' : 'pass';

        $checks['robots'] = [
            'id'          => 'robots',
            'category'    => 'seo_config',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_ROBOTS_TITLE'),
            'status'      => $robotsStatus,
            'current'     => $robots !== '' ? $robots : Text::_('MOD_LAUNCH_AUDIT_ROBOTS_DEFAULT_INDEX'),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_ROBOTS_INDEX'),
            'actionUrl'   => 'index.php?option=com_config',
            'canQuickFix' => $hasNoIndex,
            'fixKey'      => 'robots',
        ];

        // 8. SEF URLs & URL Rewriting (sef & sef_rewrite)
        $sef        = (int) $app->get('sef', 1);
        $sefRewrite = (int) $app->get('sef_rewrite', 0);

        if ($sef === 1 && $sefRewrite === 1) {
            $sefStatus  = 'pass';
            $sefCurrent = Text::_('MOD_LAUNCH_AUDIT_SEF_BOTH_ENABLED');
        } elseif ($sef === 1 && $sefRewrite === 0) {
            $sefStatus  = 'warning';
            $sefCurrent = Text::_('MOD_LAUNCH_AUDIT_SEF_REWRITE_DISABLED');
        } else {
            $sefStatus  = 'fail';
            $sefCurrent = Text::_('MOD_LAUNCH_AUDIT_SEF_DISABLED');
        }

        $checks['sef_urls'] = [
            'id'          => 'sef_urls',
            'category'    => 'seo_config',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_SEF_TITLE'),
            'status'      => $sefStatus,
            'current'     => $sefCurrent,
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_SEF_ENABLED'),
            'actionUrl'   => 'index.php?option=com_config',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // 9. Captcha / Proof-of-Work Protection
        $captcha = $app->get('captcha');
        $hasCaptcha = (!empty($captcha) && $captcha !== '0' && $captcha !== 0);

        $checks['captcha'] = [
            'id'          => 'captcha',
            'category'    => 'security',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_CAPTCHA_TITLE'),
            'status'      => $hasCaptcha ? 'pass' : 'warning',
            'current'     => $hasCaptcha ? (string) $captcha : Text::_('MOD_LAUNCH_AUDIT_STATUS_DISABLED'),
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_CAPTCHA_ENABLED'),
            'actionUrl'   => 'index.php?option=com_config',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // 10. Task Scheduler Health (com_scheduler)
        $schedulerStatus = 'pass';
        $schedulerDesc   = '';

        try {
            $query = $db->getQuery(true)
                ->select([
                    'COUNT(' . $db->quoteName('id') . ') AS ' . $db->quoteName('active_count'),
                    'SUM(CASE WHEN ' . $db->quoteName('last_exit_code') . ' > 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('failed_count'),
                ])
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            $schedRow = $db->loadAssoc() ?: [];

            $activeTasks = (int) ($schedRow['active_count'] ?? 0);
            $failedTasks = (int) ($schedRow['failed_count'] ?? 0);

            if ($failedTasks > 0) {
                $schedulerStatus = 'fail';
                $schedulerDesc   = Text::sprintf('MOD_LAUNCH_AUDIT_SCHEDULER_FAILED_TASKS', $failedTasks, $activeTasks);
            } elseif ($activeTasks === 0) {
                $schedulerStatus = 'warning';
                $schedulerDesc   = Text::_('MOD_LAUNCH_AUDIT_SCHEDULER_NO_ACTIVE_TASKS');
            } else {
                $schedulerStatus = 'pass';
                $schedulerDesc   = Text::sprintf('MOD_LAUNCH_AUDIT_SCHEDULER_ACTIVE_TASKS', $activeTasks);
            }
        } catch (\Throwable) {
            $schedulerStatus = 'warning';
            $schedulerDesc   = Text::_('MOD_LAUNCH_AUDIT_SCHEDULER_NOT_AVAILABLE');
        }

        $checks['scheduler_health'] = [
            'id'          => 'scheduler_health',
            'category'    => 'system',
            'title'       => Text::_('MOD_LAUNCH_AUDIT_CHECK_SCHEDULER_TITLE'),
            'status'      => $schedulerStatus,
            'current'     => $schedulerDesc,
            'recommended' => Text::_('MOD_LAUNCH_AUDIT_RECOMMENDED_SCHEDULER_HEALTHY'),
            'actionUrl'   => 'index.php?option=com_scheduler&view=tasks',
            'canQuickFix' => false,
            'fixKey'      => null,
        ];

        // Tally scores and group into categories
        $passCount    = 0;
        $warningCount = 0;
        $failCount    = 0;

        $catSecurity = [];
        $catSeo      = [];
        $catSystem   = [];

        foreach ($checks as $item) {
            match ($item['status']) {
                'pass'    => $passCount++,
                'warning' => $warningCount++,
                'fail'    => $failCount++,
            };

            match ($item['category']) {
                'security'   => $catSecurity[] = $item,
                'seo_config' => $catSeo[] = $item,
                'system'     => $catSystem[] = $item,
            };
        }

        $totalCount = \count($checks);
        $score      = ($totalCount > 0)
            ? (int) round((($passCount * 10) + ($warningCount * 5)) / ($totalCount * 10) * 100)
            : 0;

        return [
            'score'        => $score,
            'totalCount'   => $totalCount,
            'passCount'    => $passCount,
            'warningCount' => $warningCount,
            'failCount'    => $failCount,
            'categories'   => [
                'security'   => [
                    'id'     => 'security',
                    'title'  => Text::_('MOD_LAUNCH_AUDIT_CATEGORY_SECURITY'),
                    'checks' => $catSecurity,
                ],
                'seo_config' => [
                    'id'     => 'seo_config',
                    'title'  => Text::_('MOD_LAUNCH_AUDIT_CATEGORY_SEO'),
                    'checks' => $catSeo,
                ],
                'system'     => [
                    'id'     => 'system',
                    'title'  => Text::_('MOD_LAUNCH_AUDIT_CATEGORY_SYSTEM'),
                    'checks' => $catSystem,
                ],
            ],
            'checks'       => $checks,
        ];
    }

    /**
     * Executes 1-Click Quick Fix action via com_ajax.
     *
     * @return  array<string, mixed>
     *
     * @throws  \RuntimeException           If unauthorized, invalid token, or write failure.
     * @throws  \InvalidArgumentException   If invalid fixKey is provided.
     */
    public static function fixConfigAjax(): array
    {
        $app = Factory::getApplication();

        $token          = Session::getFormToken();
        $hasHeaderToken = ($app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', '') === $token);

        if (!$hasHeaderToken && !$app->checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $fixKey = $app->getInput()->post->getCmd('fix_key', '');
        if (empty($fixKey)) {
            $fixKey = $app->getInput()->json->getCmd('fix_key', '');
        }

        $validKeys = ['debug', 'error_reporting', 'robots', 'disable_compat6'];
        if (!\in_array($fixKey, $validKeys, true)) {
            throw new \InvalidArgumentException(Text::_('MOD_LAUNCH_AUDIT_ERROR_INVALID_FIX_KEY'), 400);
        }

        if ($fixKey === 'disable_compat6') {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('behaviour'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('compat6'));
            $db->setQuery($query)->execute();
        } else {
            $configFile = JPATH_CONFIGURATION . '/configuration.php';

            if (!file_exists($configFile)) {
                throw new \RuntimeException(Text::_('MOD_LAUNCH_AUDIT_ERROR_CONFIG_NOT_WRITABLE'), 500);
            }

            // Attempt to make file writable via Path and chmod
            if (!is_writable($configFile)) {
                if (class_exists(Path::class)) {
                    @Path::setPermissions($configFile, '0644');
                }
                @chmod($configFile, 0644);
            }

            if (!class_exists('JConfig')) {
                require_once $configFile;
            }

            $config   = new \JConfig();
            $registry = new Registry($config);

            if ($fixKey === 'debug') {
                $registry->set('debug', 0);
            } elseif ($fixKey === 'error_reporting') {
                $registry->set('error_reporting', 'default');
            } elseif ($fixKey === 'robots') {
                $registry->set('robots', '');
            }

            $phpOutput = $registry->toString('PHP', ['class' => 'JConfig', 'closingtag' => false]);

            $written = false;

            // Strategy 1: Direct write if writable
            if (is_writable($configFile)) {
                if (class_exists(File::class)) {
                    $written = @File::write($configFile, $phpOutput);
                }
                if (!$written) {
                    $written = (@file_put_contents($configFile, $phpOutput, LOCK_EX) !== false);
                }
            }

            // Strategy 2: If direct write failed (e.g. read-only 0444 inode permissions on Unix),
            // write to a temporary file in the configuration directory and atomically rename it.
            // On Unix, rename() and unlink() only require directory write permissions, which works
            // even when the target file has 0444 read-only permissions.
            if (!$written) {
                $tempFile = $configFile . '.tmp.' . bin2hex(random_bytes(6));
                if (@file_put_contents($tempFile, $phpOutput, LOCK_EX) !== false) {
                    @chmod($tempFile, 0644);
                    if (@rename($tempFile, $configFile)) {
                        $written = true;
                    } else {
                        @unlink($configFile);
                        if (@rename($tempFile, $configFile)) {
                            $written = true;
                        } else {
                            @unlink($tempFile);
                        }
                    }
                }
            }

            // Strategy 3: Try forcing chmod 0666 and write
            if (!$written) {
                @chmod($configFile, 0666);
                $written = (@file_put_contents($configFile, $phpOutput, LOCK_EX) !== false);
            }

            if (!$written) {
                throw new \RuntimeException(Text::_('MOD_LAUNCH_AUDIT_ERROR_CONFIG_NOT_WRITABLE'), 500);
            }

            // Restore standard Joomla read-only permissions (0444) for security
            if (class_exists(Path::class)) {
                @Path::setPermissions($configFile, '0444');
            }
            @chmod($configFile, 0444);

            if (\function_exists('opcache_invalidate')) {
                @opcache_invalidate($configFile, true);
            }
        }

        return [
            'success' => true,
            'fixKey'  => $fixKey,
            'message' => Text::_('MOD_LAUNCH_AUDIT_FIX_SUCCESS'),
        ];
    }
}

// Aliases for modern Joomla HelperFactory and com_ajax class resolution
if (!class_exists(__NAMESPACE__ . '\\LaunchauditHelper', false)) {
    class_alias(LaunchAuditHelper::class, __NAMESPACE__ . '\\LaunchauditHelper');
}

if (!class_exists(__NAMESPACE__ . '\\Launch_auditHelper', false)) {
    class_alias(LaunchAuditHelper::class, __NAMESPACE__ . '\\Launch_auditHelper');
}
