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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

final class SanitizationTestField extends FormField
{
    protected $type = 'SanitizationTest';

    protected function getInput(): string
    {
        $buttonId = $this->id . '_button';
        $outputId = $this->id . '_output';
        $pluginEnabled = PluginHelper::isEnabled('system', 'cbgjvisibility');
        $url = Route::_(
            'index.php?option=com_ajax&plugin=cbgjvisibility&group=system&format=json&' . Session::getFormToken() . '=1',
            false
        );

        $buttonLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_FIELD_TEST_BUTTON');
        $runningLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_RUNNING');
        $pluginDisabledNote = Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_PLUGIN_DISABLED');
        $ajaxUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        $runningLabelJs = json_encode($runningLabel, JSON_UNESCAPED_SLASHES);

        return '
            <div class="well">
                ' . (!$pluginEnabled ? '<div class="alert alert-warning">' . htmlspecialchars($pluginDisabledNote, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
                <button id="' . htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') . '" type="button" class="btn btn-primary" ' . (!$pluginEnabled ? 'disabled="disabled"' : '') . '>
                    ' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '
                </button>
                <pre id="' . htmlspecialchars($outputId, ENT_QUOTES, 'UTF-8') . '" style="max-height: 260px; overflow: auto; margin-top: 8px;"></pre>
            </div>
            <script>
                (() => {
                    const button = document.getElementById(' . json_encode($buttonId) . ');
                    const output = document.getElementById(' . json_encode($outputId) . ');

                    if (!button || !output) {
                        return;
                    }

                    button.addEventListener("click", async () => {
                        button.disabled = true;
                        output.textContent = ' . $runningLabelJs . ';

                        try {
                            const response = await fetch(' . $ajaxUrl . ', {
                                credentials: "same-origin",
                                method: "GET",
                                headers: { "Accept": "application/json" }
                            });
                            const json = await response.json();
                            const payload = Array.isArray(json.data)
                                ? (json.data.length > 0 ? json.data[0] : null)
                                : (json.data ?? null);

                            if (!payload) {
                                output.textContent = JSON.stringify(json, null, 2);
                                return;
                            }

                            if (payload.error) {
                                output.textContent = payload.error;
                                return;
                            }

                            let lines = [];

                            if (payload.marker_found === false) {
                                lines.push(payload.message || "No event data found on page.");
                            } else {
                                lines.push(payload.message || "");
                                lines.push("");
                                (payload.checks || []).forEach((c) => {
                                    lines.push("[" + c.status + "] " + c.class);
                                });
                            }

                            output.textContent = lines.join("\\n");
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
}
