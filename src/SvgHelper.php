<?php

namespace App;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\BrowserConnectionFailed;
use InvalidArgumentException;

class SvgHelper
{
    private static string $socketFilepath = '/tmp/chrome-php-demo-socket';

    public function renderSvg(string $html, string $selector): string
    {
        $browserFactory = new BrowserFactory();
        $createBrowserOptions = [
            // https://github.com/chrome-php/chrome?tab=readme-ov-file#available-options
            'noSandbox' => true,
        ];

        try {
            $socket = (string) (file_exists(static::$socketFilepath) ? \file_get_contents(static::$socketFilepath) : null);
            try {
                $browser = BrowserFactory::connectToBrowser($socket);
            } catch (\InvalidArgumentException $e) {
                // Check for an exception like
                // PHP Fatal error:  Uncaught InvalidArgumentException: No URI specified in /app/vendor/chrome-php/wrench/src/Client.php:80
                if ('No URI specified' === $e->getMessage()) {
                    throw new BrowserConnectionFailed($e->getMessage());
                }
                throw $e;
            }
        } catch (BrowserConnectionFailed $e) {
            // The browser was probably closed, start it again
            $factory = new BrowserFactory();
            $browser = $factory->createBrowser([
                'keepAlive' => true,
                // https://github.com/chrome-php/chrome?tab=readme-ov-file#available-options
                'noSandbox' => true,
            ]);

            // save the uri to be able to connect again to browser
            \file_put_contents(static::$socketFilepath, $browser->getSocketUri(), LOCK_EX);
        }

        try {
            // creates a new page and navigate to an URL
            $page = $browser->createPage();

            $page->setHtml($html);
            $page
                ->addScriptTag(['content' => file_get_contents(__DIR__.'/../assets/lib/snapdom@1.3.0.min.js')])
                ->waitForResponse();
            $snapdom = $page
                ->evaluate(sprintf('snapdom(document.querySelector(%s))', json_encode($selector)))
                ->getReturnValue();
            $url = $snapdom['url'] ?? '';
            // Remove Data URL stuff (cf. https://developer.mozilla.org/en-US/docs/Web/URI/Reference/Schemes/data)
            $url = preg_replace('/^data:[^,]*,/', '', $url);
            $svg = urldecode($url);

            return $svg;
        } finally {
            // bye
            //    $browser->close();
        }
    }
}
