<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cbgjvisibility
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace YakShaver\Plugin\System\Cbgjvisibility\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Throwable;

final class VerifyField extends FormField
{
    protected $type = 'Verify';

    private const TEMPLATE_ROOT = '/components/com_comprofiler/plugin/user/';

    private const MANIFEST_MAP = [
        'cbgroupjive' => 'plug_cbgroupjive/cbgroupjive.xml',
        'cbgroupjiveevents' => 'plug_cbgroupjive/plugins/cbgroupjiveevents/cbgroupjiveevents.xml',
        'cbactivity' => 'plug_cbactivity/cbactivity.xml',
    ];

    protected function getInput(): string
    {
        $installed = $this->getInstalledVersions();
        $verified = [
            'cbgroupjive' => (string) $this->form->getValue('verified_cbgroupjive_version', 'params', ''),
            'cbgroupjiveevents' => (string) $this->form->getValue('verified_cbgroupjiveevents_version', 'params', ''),
            'cbactivity' => (string) $this->form->getValue('verified_cbactivity_version', 'params', ''),
        ];

        $buttonId = $this->id . '_button';
        $outputId = $this->id . '_output';
        $pluginEnabled = PluginHelper::isEnabled('system', 'cbgjvisibility');
        $url = Route::_(
            'index.php?option=com_ajax&plugin=cbgjvisibility&group=system&format=json&' . Session::getFormToken() . '=1',
            false
        );

        $rows = [
            [
                'key' => 'cbgroupjive',
                'label' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBGROUPJIVE'),
                'installed' => $installed['cbgroupjive'] ?? '',
                'verified' => $verified['cbgroupjive'] ?? '',
            ],
            [
                'key' => 'cbgroupjiveevents',
                'label' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBGROUPJIVEEVENTS'),
                'installed' => $installed['cbgroupjiveevents'] ?? '',
                'verified' => $verified['cbgroupjiveevents'] ?? '',
            ],
            [
                'key' => 'cbactivity',
                'label' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBACTIVITY'),
                'installed' => $installed['cbactivity'] ?? '',
                'verified' => $verified['cbactivity'] ?? '',
            ],
        ];

        $tableRows = '';

        foreach ($rows as $row) {
            $tableRows .= '<tr>'
                . '<td>' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td><code>' . htmlspecialchars($row['installed'] !== '' ? $row['installed'] : '-', ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '<td><code data-cbgj-verified="' . htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['verified'] !== '' ? $row['verified'] : Text::_('PLG_SYSTEM_CBGJVISIBILITY_NOT_VERIFIED'), ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '</tr>';
        }

        $outputLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_RESULT_LABEL');
        $buttonLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_FIELD_VERIFY_BUTTON');
        $runningLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_RUNNING');
        $savedHint = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_SAVED_HINT');
        $emptyDataHint = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_EMPTY_DATA_HINT');
        $pluginDisabledNote = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_PLUGIN_DISABLED');
        $ajaxUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        $runningLabelJs = json_encode($runningLabel, JSON_UNESCAPED_SLASHES);
        $savedHintJs = json_encode($savedHint, JSON_UNESCAPED_SLASHES);
        $emptyDataHintJs = json_encode($emptyDataHint, JSON_UNESCAPED_SLASHES);
        $isPluginEnabledJs = $pluginEnabled ? 'true' : 'false';

        return '
            <div class="well">
                ' . (!$pluginEnabled ? '<div class="alert alert-warning">' . htmlspecialchars($pluginDisabledNote, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>' . htmlspecialchars(Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_TABLE_PLUGIN'), ENT_QUOTES, 'UTF-8') . '</th>
                            <th>' . htmlspecialchars(Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_TABLE_INSTALLED'), ENT_QUOTES, 'UTF-8') . '</th>
                            <th>' . htmlspecialchars(Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_TABLE_VERIFIED'), ENT_QUOTES, 'UTF-8') . '</th>
                        </tr>
                    </thead>
                    <tbody>' . $tableRows . '</tbody>
                </table>
                <button id="' . htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') . '" type="button" class="btn btn-primary" ' . (!$pluginEnabled ? 'disabled="disabled"' : '') . '>
                    ' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '
                </button>
                <p class="help-block" style="margin-top: 8px;">
                    ' . htmlspecialchars($outputLabel, ENT_QUOTES, 'UTF-8') . '
                </p>
                <pre id="' . htmlspecialchars($outputId, ENT_QUOTES, 'UTF-8') . '" style="max-height: 260px; overflow: auto;"></pre>
            </div>
            <script>
                (() => {
                    const button = document.getElementById(' . json_encode($buttonId) . ');
                    const output = document.getElementById(' . json_encode($outputId) . ');
                    const url = ' . $ajaxUrl . ';
                    const isPluginEnabled = ' . $isPluginEnabledJs . ';
                    const emptyDataHint = ' . $emptyDataHintJs . ';
                    const hiddenVerifiedFields = {
                        cbgroupjive: document.getElementById("jform_params_verified_cbgroupjive_version"),
                        cbgroupjiveevents: document.getElementById("jform_params_verified_cbgroupjiveevents_version"),
                        cbactivity: document.getElementById("jform_params_verified_cbactivity_version")
                    };
                    const verifiedBadges = {
                        cbgroupjive: document.querySelector(\'code[data-cbgj-verified="cbgroupjive"]\'),
                        cbgroupjiveevents: document.querySelector(\'code[data-cbgj-verified="cbgroupjiveevents"]\'),
                        cbactivity: document.querySelector(\'code[data-cbgj-verified="cbactivity"]\')
                    };

                    if (!button || !output) {
                        return;
                    }

                    button.addEventListener("click", async () => {
                        if (!isPluginEnabled) {
                            output.textContent = emptyDataHint;
                            return;
                        }

                        button.disabled = true;
                        output.textContent = ' . $runningLabelJs . ';

                        try {
                            const response = await fetch(url, {
                                credentials: "same-origin",
                                method: "GET",
                                headers: { "Accept": "application/json" }
                            });
                            const json = await response.json();
                            const hasDataArray = Array.isArray(json.data);
                            const payload = hasDataArray
                                ? (json.data.length > 0 ? json.data[0] : null)
                                : (json.data ?? null);

                            output.textContent = JSON.stringify(payload ?? json, null, 2);

                            if (hasDataArray && json.data.length === 0) {
                                output.textContent += "\\n\\n" + emptyDataHint;
                            }

                            if (payload && payload.saved) {
                                const resolved = {
                                    cbgroupjive: payload.cbgroupjive_version ?? "",
                                    cbgroupjiveevents: payload.cbgroupjiveevents_version ?? "",
                                    cbactivity: payload.cbactivity_version ?? ""
                                };

                                Object.keys(resolved).forEach((key) => {
                                    const value = String(resolved[key] ?? "");

                                    if (hiddenVerifiedFields[key]) {
                                        hiddenVerifiedFields[key].value = value;
                                    }

                                    if (verifiedBadges[key]) {
                                        verifiedBadges[key].textContent = value || ' . json_encode(Text::_('PLG_SYSTEM_CBGJVISIBILITY_NOT_VERIFIED')) . ';
                                    }
                                });

                                output.textContent += "\\n\\n" + ' . $savedHintJs . ';
                            }
                        } catch (error) {
                            output.textContent = String(error);
                        } finally {
                            button.disabled = false;
                        }
                    });
                })();
            </script>
        ';
    }

    private function getInstalledVersions(): array
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $columns = array_change_key_case((array) $db->getTableColumns('#__comprofiler_plugin', false), CASE_LOWER);
            $hasVersionColumn = isset($columns['version']);
            $hasParamsColumn = isset($columns['params']);
            $selectColumns = [$db->quoteName('element')];

            if ($hasVersionColumn) {
                $selectColumns[] = $db->quoteName('version');
            }

            if ($hasParamsColumn) {
                $selectColumns[] = $db->quoteName('params');
            }

            $query = $db->getQuery(true)
                ->select($selectColumns)
                ->from($db->quoteName('#__comprofiler_plugin'));
            $db->setQuery($query);
            $rows = (array) $db->loadAssocList();
            $versions = $this->resolveCbVersionsFromRows($rows);

            foreach (self::MANIFEST_MAP as $key => $manifestPath) {
                if (!empty($versions[$key])) {
                    continue;
                }

                $manifestVersion = $this->getVersionFromManifest($manifestPath);

                if ($manifestVersion !== '') {
                    $versions[$key] = $manifestVersion;
                }
            }

            if (empty($versions['cbgroupjiveevents']) && !empty($versions['cbgroupjive'])) {
                $versions['cbgroupjiveevents'] = $versions['cbgroupjive'];
            }

            return array_filter($versions, static fn(string $version): bool => $version !== '');
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveCbVersionsFromRows(array $rows): array
    {
        $resolved = [
            'cbgroupjive' => '',
            'cbgroupjiveevents' => '',
            'cbactivity' => '',
        ];

        foreach ($rows as $row) {
            $element = strtolower(trim((string) ($row['element'] ?? '')));
            $version = trim((string) ($row['version'] ?? ''));

            if ($version === '') {
                $version = $this->extractVersionFromParams((string) ($row['params'] ?? ''));
            }

            if ($element === '' || $version === '') {
                continue;
            }

            if ($element === 'cbgroupjive') {
                $resolved['cbgroupjive'] = $version;
                continue;
            }

            if ($element === 'cbgroupjiveevents') {
                $resolved['cbgroupjiveevents'] = $version;
                continue;
            }

            if ($element === 'cbactivity') {
                $resolved['cbactivity'] = $version;
                continue;
            }

            if ($resolved['cbgroupjiveevents'] === '' && str_contains($element, 'groupjiveevents')) {
                $resolved['cbgroupjiveevents'] = $version;
                continue;
            }

            if ($resolved['cbgroupjive'] === '' && str_contains($element, 'groupjive')) {
                $resolved['cbgroupjive'] = $version;
                continue;
            }

            if ($resolved['cbactivity'] === '' && str_contains($element, 'activity')) {
                $resolved['cbactivity'] = $version;
            }
        }

        if ($resolved['cbgroupjiveevents'] === '' && $resolved['cbgroupjive'] !== '') {
            $resolved['cbgroupjiveevents'] = $resolved['cbgroupjive'];
        }

        return array_filter($resolved, static fn(string $version): bool => $version !== '');
    }

    private function getVersionFromManifest(string $relativePath): string
    {
        $path = JPATH_SITE . self::TEMPLATE_ROOT . $relativePath;

        if (!is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        if (preg_match('/<version>\s*([^<]+)\s*<\/version>/i', $contents, $match) !== 1) {
            return '';
        }

        return trim((string) ($match[1] ?? ''));
    }

    private function extractVersionFromParams(string $params): string
    {
        if ($params === '') {
            return '';
        }

        try {
            $registry = new Registry($params);

            return trim((string) $registry->get('version', ''));
        } catch (Throwable) {
            return '';
        }
    }
}
