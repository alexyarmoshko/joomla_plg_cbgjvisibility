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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

final class Cbgjvisibility extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    private const SELECTOR_DEFAULTS = [
        'host' => ['element' => 'div', 'class' => 'gjGroupEventHost', 'hide_default' => 1],
        'group' => ['element' => 'div', 'class' => 'gjGroupEventGroup', 'hide_default' => 1],
        'guests' => ['element' => 'div', 'class' => 'gjGroupEventGuests', 'hide_default' => 1],
        'description' => ['element' => 'div', 'class' => 'gjGroupEventDescription', 'hide_default' => 0],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRender' => 'onAfterRender',
            'onAjaxCbgjvisibility' => 'onAjaxCbgjvisibility',
        ];
    }

    public function onAfterRender(?Event $event = null): void
    {
        $app = $this->getApplication();

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
        $result = $this->runSanitizationTest();

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

    private function runSanitizationTest(): array
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return ['error' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_ERROR_ADMIN_ONLY')];
        }

        if (!$app->getIdentity()->authorise('core.manage', 'com_plugins')) {
            return ['error' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_ERROR_UNAUTHORIZED')];
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            return ['error' => Text::_('JINVALID_TOKEN')];
        }

        $response = $this->fetchFrontPageAsGuest();

        if ($response === null) {
            return ['error' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_FETCH_FAILED')];
        }

        if ($response['httpCode'] === 503) {
            return ['error' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_SITE_OFFLINE')];
        }

        $html = $response['html'];
        $markerString = trim((string) $this->params->get('marker_string', 'gjGroupEvent'));

        if ($markerString !== '' && strpos($html, $markerString) === false) {
            return [
                'marker_found' => false,
                'message' => Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_INCONCLUSIVE'),
            ];
        }

        $checks = [];
        $allOk = true;

        foreach (self::SELECTOR_DEFAULTS as $key => $defaults) {
            $hideEnabled = (int) $this->params->get('hide_' . $key, $defaults['hide_default']) === 1;

            if (!$hideEnabled) {
                continue;
            }

            $className = $this->getSelectorClass($key);

            if ($className === '') {
                continue;
            }

            $found = preg_match('/\bclass="[^"]*\b' . preg_quote($className, '/') . '\b/', $html) === 1;

            $checks[] = [
                'key' => $key,
                'class' => $className,
                'status' => $found ? 'FAIL' : 'PASS',
            ];

            if ($found) {
                $allOk = false;
            }
        }

        return [
            'marker_found' => true,
            'all_ok' => $allOk,
            'checks' => $checks,
            'message' => $allOk
                ? Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_PASS')
                : Text::_('PLG_SYSTEM_CBGJVISIBILITY_TEST_FAIL'),
        ];
    }

    /**
     * @return array{html: string, httpCode: int}|null
     */
    private function fetchFrontPageAsGuest(): ?array
    {
        $url = Uri::root();

        // In admin context, Uri::root() may resolve to include /administrator/
        // in the path; strip it to always target the public site root.
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        if (preg_match('#/administrator(?:/|$)#i', $path)) {
            $path = preg_replace('#/administrator(?:/|$)#i', '/', $path);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? 'localhost';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $url = $scheme . '://' . $host . $port . $path;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_COOKIE => '',
                CURLOPT_HTTPHEADER => ['Cookie:'],
                CURLOPT_USERAGENT => 'CbgjvisibilitySanitizationTest/1.0',
            ]);
            $html = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($html === false || $httpCode < 200 || ($httpCode >= 400 && $httpCode !== 503)) {
                return null;
            }

            return ['html' => (string) $html, 'httpCode' => $httpCode];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "Cookie:\r\nUser-Agent: CbgjvisibilitySanitizationTest/1.0\r\n",
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false) {
            return null;
        }

        // Extract HTTP status code from response headers.
        $httpCode = 200;

        if (!empty($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $m)) {
                    $httpCode = (int) $m[1];
                }
            }
        }

        if ($httpCode < 200 || ($httpCode >= 400 && $httpCode !== 503)) {
            return null;
        }

        return ['html' => $html, 'httpCode' => $httpCode];
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
