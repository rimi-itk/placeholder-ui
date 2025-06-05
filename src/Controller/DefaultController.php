<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Error\LoaderError;

final class DefaultController extends AbstractController
{
    private const string OUTLINE_COLOR = '#fedabe';
    private const int OUTLINE_WIDTH = 2;

    #[Route(
        path: '/{path}.{_format}',
        name: 'default',
        requirements: [
            // Allow slashes in path.
            'path' => '[^.]*',
            '_format' => 'html|svg',
        ],
        methods: [Request::METHOD_GET],
        format: 'html'
    )]
    public function index(Request $request, string $path, string $_format, HttpClientInterface $httpClient): Response
    {
        if (empty($path)) {
            return $this->renderIndex($request);
        }

        $parameters = $request->query->all() + ['_format' => $_format];

        if ('svg' === $_format) {
            $url = 'http://html2svg:8080';
            $path = substr($request->getPathInfo(), 1, -4);
            $body = array_filter([
                'url' => 'http://nginx:8080/'.$path.'?'.http_build_query($parameters),
                'width' => $parameters['width'] ?? null,
                'height' => $parameters['height'] ?? null,
                'format' => 'svg',
            ]);
            $response = $httpClient->request('POST', $url, ['json' => $body]);

            return new Response($this->trimSvg($response->getContent()), $response->getStatusCode(), $response->getHeaders() + ['content-type' => 'image/svg+xml']);
        }

        try {
            $parameters += [
                'outline_color' => static::OUTLINE_COLOR,
                'outline_width' => static::OUTLINE_WIDTH,
                'urls' => [
                    'svg' => $this->generateUrl('default', ['path' => $path, '_format' => 'svg'] + $request->query->all()),
                ],
            ];

            return $this->render(rtrim($path ?: 'index', '/').'.html.twig', $parameters);
        } catch (LoaderError $loaderError) {
            throw new NotFoundHttpException($loaderError->getMessage());
        }
    }

    private function trimSvg(string $svg): string
    {
        $document = new \DOMDocument();
        $document->loadXML($svg);

        $width = null;
        $height = null;

        // Find 3 rect elements:
        // 1. The rendered body element background; <rect fill="#FEDABE" />
        // 2. The rendered .component element background; <rect fill="#FEDABE" />
        // 3. The rendered .component element outline; <rect stroke="#FEDABE" />
        $rects = $document->getElementsByTagName('rect');

        // The DOMNodeList is live. To remove nodes, we must process a static list.
        foreach (iterator_to_array($rects->getIterator()) as $rect) {
            $fill = strtolower($rect->attributes->getNamedItem('fill')?->nodeValue ?? '');
            $stroke = strtolower($rect->attributes->getNamedItem('stroke')?->nodeValue ?? '');

            // The elements with a background has a fill, but no stroke.
            if (static::OUTLINE_COLOR === $fill && empty($stroke)) {
                $elementWidth = (int) $rect->attributes->getNamedItem('width')?->nodeValue;
                $elementHeight = (int) $rect->attributes->getNamedItem('height')?->nodeValue;
                if ($elementWidth > 0 && $elementHeight > 0) {
                    $width = min($width ?? $elementWidth, $elementWidth);
                    $height = min($height ?? $elementHeight, $elementHeight);
                }
            }

            if (static::OUTLINE_COLOR === $fill || static::OUTLINE_COLOR === $stroke) {
                $rect->parentNode->removeChild($rect);
            }
        }

        if ($width && $height) {
            // We must subtract two times half the the outline width, i.e. 1 outline width, from each dimension.
            $document->documentElement->setAttribute('width', $width - static::OUTLINE_WIDTH);
            $document->documentElement->setAttribute('height', $height - static::OUTLINE_WIDTH);
        }

        return $document->saveXML();
    }

    private function renderIndex(Request $request)
    {
        $items = $this->getItems();

        return $this->render('index.html.twig', [
            'items' => $items,
        ]);
    }

    private function getItems(): array
    {
        $items = [];

        $basePath = realpath(__DIR__.'/../../templates');
        $finder = new Finder()->in([
            $basePath.'/*',
        ]);

        foreach ($finder->name('*.html.twig') as $file) {
            // Remove base path prefix and file name extensions.
            $path = substr($file->getPath(), strlen($basePath) + 1).'/'.substr($file->getFilename(), 0, -10);
            $item = [
                'path' => $path,
                'url' => $this->generateUrl('default', ['path' => $path]),
            ];

            $contents = $file->getContents();
            // Our Twig linter adds spaces in our `---` markers …
            $pattern = '/(\{#- ?--|-- ?-#})/';
            $stuff = preg_split($pattern, $contents, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (count($stuff) > 2 && preg_match($pattern, $stuff[0]) && preg_match($pattern, $stuff[2])) {
                $item += Yaml::parse($stuff[1]);
            }

            // Add default/fallback values.
            $item += [
                'title' => $file->getBasename('.html.twig'),
            ];

            if (isset($item['description'])) {
                $item['description'] = new \Parsedown()->text($item['description']);
            }

            $items[] = $item;
        }

        return $items;
    }
}
