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

final class SanitizationTestField extends FormField
{
    protected $type = 'SanitizationTest';

    protected function getInput(): string
    {
        $pluginEnabled = PluginHelper::isEnabled('system', 'cbgjvisibility');
        $url = Route::_(
            'index.php?option=com_ajax&plugin=cbgjvisibility&group=system&format=json&' . Session::getFormToken() . '=1',
            false
        );

        $this->registerAssets();

        $buttonLabel = Text::_('PLG_SYSTEM_CBGJVISIBILITY_FIELD_TEST_BUTTON');
        $pluginDisabledNote = Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_PLUGIN_DISABLED');
        $warning = '';

        if (!$pluginEnabled) {
            $warning = '<div class="alert alert-warning">' . htmlspecialchars($pluginDisabledNote, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return '
            <div class="card card-body js-cbgjvisibility-sanitization-test" data-cbgjvisibility-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">
                ' . $warning . '
                <button type="button" class="btn btn-primary js-cbgjvisibility-test-button" ' . (!$pluginEnabled ? 'disabled="disabled"' : '') . '>
                    ' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '
                </button>
                <pre class="border rounded p-2 mt-3 bg-light overflow-auto js-cbgjvisibility-test-output" aria-live="polite"></pre>
            </div>
        ';
    }

    private function registerAssets(): void
    {
        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->registerAndUseScript(
                'plg_system_cbgjvisibility.sanitization_test',
                'plg_system_cbgjvisibility/sanitization-test.js',
                [],
                ['defer' => true],
                ['core']
            );

        Text::script('PLG_SYSTEM_CBGJVISIBILITY_TEST_RUNNING', true);
        Text::script('PLG_SYSTEM_CBGJVISIBILITY_TEST_INCONCLUSIVE', true);
    }
}
