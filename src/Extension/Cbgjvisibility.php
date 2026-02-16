<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cbgjvisibility
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace YakShaver\Plugin\System\Cbgjvisibility\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Throwable;

final class Cbgjvisibility extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    private const TEMPLATE_ROOT = '/components/com_comprofiler/plugin/user/';

    private const TEMPLATE_MAP = [
        'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/events.php' => [
            'host',
            'group',
            'guests',
            'description',
        ],
        'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/module.php' => [
            'host',
            'group',
            'guests',
            'description',
        ],
        'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/activity.php' => [
            'host',
            'group',
            'guests',
            'description',
        ],
        'plug_cbactivity/templates/default/activity/core/group/event.php' => [
            'host',
            'group',
            'guests',
            'description',
        ],
        'plug_cbgroupjive/plugins/cbgroupjiveevents/templates/default/attending.php' => [],
    ];

    private const SELECTOR_DEFAULTS = [
        'host' => ['element' => 'div', 'class' => 'gjGroupEventHost', 'hide_default' => 1],
        'group' => ['element' => 'div', 'class' => 'gjGroupEventGroup', 'hide_default' => 1],
        'guests' => ['element' => 'div', 'class' => 'gjGroupEventGuests', 'hide_default' => 1],
        'description' => ['element' => 'div', 'class' => 'gjGroupEventDescription', 'hide_default' => 0],
    ];

    private const VERIFIED_VERSION_PARAMS = [
        'cbgroupjive' => 'verified_cbgroupjive_version',
        'cbgroupjiveevents' => 'verified_cbgroupjiveevents_version',
        'cbactivity' => 'verified_cbactivity_version',
    ];

    private const MANIFEST_MAP = [
        'cbgroupjive' => 'plug_cbgroupjive/cbgroupjive.xml',
        'cbgroupjiveevents' => 'plug_cbgroupjive/plugins/cbgroupjiveevents/cbgroupjiveevents.xml',
        'cbactivity' => 'plug_cbactivity/cbactivity.xml',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRender' => 'onAfterRender',
            'onAjaxCbgjvisibility' => 'onAjaxCbgjvisibility',
            'onAjaxcbgjvisibility' => 'onAjaxCbgjvisibility',
        ];
    }

    public function onAfterRender(?Event $event = null): void
    {
        $app = $this->getApplication();

        if ($app->isClient('administrator')) {
            $this->enqueueCompatibilityWarning();

            return;
        }

        if (!$app->isClient('site')) {
            return;
        }

        if (!$app->getIdentity()->guest) {
            return;
        }

        if (!$this->isHtmlResponse()) {
            return;
        }

        $body = (string) $app->getBody();

        if ($body === '') {
            return;
        }

        $markerString = trim((string) $this->params->get('marker_string', 'gjGroupEvent'));

        if ($markerString !== '' && strpos($body, $markerString) === false) {
            return;
        }

        $updatedBody = $this->stripConfiguredBlocks($body);

        if ($updatedBody !== $body) {
            $app->setBody($updatedBody);
        }
    }

    public function onAjaxCbgjvisibility(?Event $event = null): array
    {
        $result = $this->runCompatibilityVerification();

        if ($event instanceof ResultAwareInterface) {
            $event->addResult($result);
            return $result;
        }

        if ($event instanceof Event) {
            $results = (array) ($event->getArgument('result') ?? []);
            $results[] = $result;
            $event->setArgument('result', $results);
        }

        return $result;
    }

    private function stripConfiguredBlocks(string $body): string
    {
        foreach (self::SELECTOR_DEFAULTS as $key => $defaults) {
            $hideEnabled = (int) $this->params->get('hide_' . $key, $defaults['hide_default']) === 1;

            if (!$hideEnabled) {
                continue;
            }

            $element = $this->getSelectorElement($key);
            $className = $this->getSelectorClass($key);

            if ($className === '') {
                continue;
            }

            if ($key === 'description') {
                $body = $this->stripNestedBlockByClass($body, $element, $className);
                continue;
            }

            $body = $this->stripSimpleBlockByClass($body, $element, $className);
        }

        return $body;
    }

    private function stripSimpleBlockByClass(string $body, string $element, string $className): string
    {
        $quotedElement = preg_quote($element, '#');
        $quotedClass = preg_quote($className, '#');
        $pattern = '#<' . $quotedElement . '\b[^>]*\bclass="[^"]*\b' . $quotedClass . '\b[^"]*"[^>]*>.*?</' . $quotedElement . '>#si';
        $updatedBody = preg_replace($pattern, '', $body);

        return $updatedBody === null ? $body : $updatedBody;
    }

    private function stripNestedBlockByClass(string $body, string $element, string $className): string
    {
        $quotedElement = preg_quote($element, '#');
        $quotedClass = preg_quote($className, '#');
        $openingPattern = '#<' . $quotedElement . '\b[^>]*\bclass="[^"]*\b' . $quotedClass . '\b[^"]*"[^>]*>#i';
        $searchOffset = 0;

        while (preg_match($openingPattern, $body, $match, PREG_OFFSET_CAPTURE, $searchOffset) === 1) {
            $openingTag = (string) $match[0][0];
            $openingTagOffset = (int) $match[0][1];
            $afterOpeningOffset = $openingTagOffset + strlen($openingTag);
            $closingOffset = $this->findMatchingClosingOffset($body, $element, $afterOpeningOffset);

            if ($closingOffset === null) {
                $searchOffset = $afterOpeningOffset;
                continue;
            }

            $body = substr($body, 0, $openingTagOffset) . substr($body, $closingOffset);
            $searchOffset = $openingTagOffset;
        }

        return $body;
    }

    private function findMatchingClosingOffset(string $body, string $element, int $offset): ?int
    {
        $quotedElement = preg_quote($element, '#');
        $tokenPattern = '#</?' . $quotedElement . '\b[^>]*>#i';
        $depth = 1;

        while (preg_match($tokenPattern, $body, $tokenMatch, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $token = (string) $tokenMatch[0][0];
            $tokenOffset = (int) $tokenMatch[0][1];
            $offset = $tokenOffset + strlen($token);

            if (str_starts_with($token, '</')) {
                --$depth;
            } else {
                ++$depth;
            }

            if ($depth === 0) {
                return $offset;
            }
        }

        return null;
    }

    private function isHtmlResponse(): bool
    {
        $app = $this->getApplication();

        if (!method_exists($app, 'getDocument')) {
            return true;
        }

        $document = $app->getDocument();

        if ($document === null || !method_exists($document, 'getType')) {
            return true;
        }

        return $document->getType() === 'html';
    }

    private function runCompatibilityVerification(): array
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return $this->buildErrorResult(Text::_('PLG_SYSTEM_CBGJVISIBILITY_ERROR_ADMIN_ONLY'));
        }

        if (!$app->getIdentity()->authorise('core.manage', 'com_plugins')) {
            return $this->buildErrorResult(Text::_('PLG_SYSTEM_CBGJVISIBILITY_ERROR_UNAUTHORIZED'));
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            return $this->buildErrorResult(Text::_('JINVALID_TOKEN'));
        }

        $installedVersions = $this->getInstalledCbVersions();
        $scanResult = $this->scanTemplateFiles();
        $response = [
            'all_ok' => $scanResult['all_ok'],
            'saved' => false,
            'files' => $scanResult['files'],
            'cbgroupjive_version' => $installedVersions['cbgroupjive'] ?? '',
            'cbgroupjiveevents_version' => $installedVersions['cbgroupjiveevents'] ?? '',
            'cbactivity_version' => $installedVersions['cbactivity'] ?? '',
            'message' => $scanResult['all_ok']
                ? Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_SCAN_OK')
                : Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_SCAN_FAILED'),
        ];

        if (!$scanResult['all_ok']) {
            return $response;
        }

        if (!$this->hasAllTrackedVersions($installedVersions)) {
            $response['all_ok'] = false;
            $response['message'] = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_VERSION_UNAVAILABLE');

            return $response;
        }

        $response['saved'] = $this->saveVerifiedVersions($installedVersions);
        $response['message'] = $response['saved']
            ? Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_SUCCESS')
            : Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_SAVE_FAILED');

        if (!$response['saved']) {
            $response['all_ok'] = false;
        }

        return $response;
    }

    private function scanTemplateFiles(): array
    {
        $allOk = true;
        $files = [];

        foreach (self::TEMPLATE_MAP as $relativePath => $selectorKeys) {
            $absolutePath = JPATH_SITE . self::TEMPLATE_ROOT . $relativePath;
            $fileReport = [
                'path' => $absolutePath,
                'exists' => is_file($absolutePath),
                'found' => [],
                'missing' => [],
            ];

            if (!$fileReport['exists']) {
                $fileReport['error'] = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_FILE_MISSING');
                $files[$relativePath] = $fileReport;
                $allOk = false;
                continue;
            }

            $contents = file_get_contents($absolutePath);

            if ($contents === false) {
                $fileReport['error'] = Text::_('PLG_SYSTEM_CBGJVISIBILITY_VERIFY_FILE_UNREADABLE');
                $files[$relativePath] = $fileReport;
                $allOk = false;
                continue;
            }

            foreach ($selectorKeys as $selectorKey) {
                $className = $this->getSelectorClass($selectorKey);

                if ($className === '') {
                    $fileReport['missing'][] = '[empty:' . $selectorKey . ']';
                    $allOk = false;
                    continue;
                }

                if (strpos($contents, $className) === false) {
                    $fileReport['missing'][] = $className;
                    $allOk = false;
                } else {
                    $fileReport['found'][] = $className;
                }
            }

            $files[$relativePath] = $fileReport;
        }

        return [
            'all_ok' => $allOk,
            'files' => $files,
        ];
    }

    private function saveVerifiedVersions(array $installedVersions): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('cbgjvisibility'));

            $db->setQuery($query);
            $extension = $db->loadAssoc();

            if (!is_array($extension) || !isset($extension['extension_id'])) {
                return false;
            }

            $params = new Registry((string) ($extension['params'] ?? '{}'));

            foreach (self::VERIFIED_VERSION_PARAMS as $element => $paramName) {
                $version = (string) ($installedVersions[$element] ?? '');
                $params->set($paramName, $version);
                $this->params->set($paramName, $version);
            }

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $extension['extension_id']);

            $db->setQuery($updateQuery);
            $db->execute();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function enqueueCompatibilityWarning(): void
    {
        $installedVersions = $this->getInstalledCbVersions();

        if ($installedVersions === []) {
            return;
        }

        $messages = [];

        foreach (self::VERIFIED_VERSION_PARAMS as $element => $paramName) {
            $installed = (string) ($installedVersions[$element] ?? '');

            if ($installed === '') {
                continue;
            }

            $verified = trim((string) $this->params->get($paramName, ''));

            if ($verified === $installed) {
                continue;
            }

            $messages[] = Text::sprintf(
                'PLG_SYSTEM_CBGJVISIBILITY_WARNING_VERSION_MISMATCH',
                $this->getElementLabel($element),
                $installed,
                $verified !== '' ? $verified : Text::_('PLG_SYSTEM_CBGJVISIBILITY_NOT_VERIFIED')
            );
        }

        if ($messages !== []) {
            $this->getApplication()->enqueueMessage(implode(' ', $messages), 'warning');
        }
    }

    private function getInstalledCbVersions(): array
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

        // Some CB installs do not expose a dedicated cbgroupjiveevents element.
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
            $version = trim((string) $registry->get('version', ''));

            return $version;
        } catch (Throwable) {
            return '';
        }
    }

    private function hasAllTrackedVersions(array $versions): bool
    {
        foreach (array_keys(self::VERIFIED_VERSION_PARAMS) as $key) {
            if (trim((string) ($versions[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function buildErrorResult(string $message): array
    {
        return [
            'all_ok' => false,
            'saved' => false,
            'error' => $message,
            'files' => [],
            'cbgroupjive_version' => '',
            'cbgroupjiveevents_version' => '',
            'cbactivity_version' => '',
        ];
    }

    private function getElementLabel(string $element): string
    {
        return match ($element) {
            'cbgroupjive' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBGROUPJIVE'),
            'cbgroupjiveevents' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBGROUPJIVEEVENTS'),
            'cbactivity' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_LABEL_CBACTIVITY'),
            default => $element,
        };
    }

    private function getSelectorElement(string $key): string
    {
        $default = (string) self::SELECTOR_DEFAULTS[$key]['element'];
        $element = strtolower(trim((string) $this->params->get('element_' . $key, $default)));

        if ($element === '') {
            return $default;
        }

        if (!preg_match('/^[a-z][a-z0-9:-]*$/', $element)) {
            return $default;
        }

        return $element;
    }

    private function getSelectorClass(string $key): string
    {
        $default = (string) self::SELECTOR_DEFAULTS[$key]['class'];
        $className = trim((string) $this->params->get('class_' . $key, $default));

        return $className !== '' ? $className : $default;
    }
}
