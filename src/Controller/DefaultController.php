<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        path: '/',
    )]
    public function indexNoLocale(): Response
    {
        // @TODO Handle path, e.g. redirect from /hest/hyp to /en/hest/hyp
        return $this->redirectToRoute('default', ['_locale' => 'en', 'path' => '']);
    }

    #[Route(
        path: '/{_locale}/{path}.{_format}',
        name: 'default',
        requirements: [
            // Allow slashes (but no dots) in path.
            'path' => '[^.]*',
            '_format' => 'html|svg',
        ],
        methods: [Request::METHOD_GET],
        locale: 'en',
        format: 'html',
    )]
    #[Cache(maxage: 3600, public: true, mustRevalidate: true)]
    public function index(Request $request, string $path, HttpClientInterface $httpClient): Response
    {
        if (empty($path)) {
            return $this->renderIndex($request);
        }

        $_format = $request->getRequestFormat();
        $parameters = $request->query->all() + ['_format' => $_format];

        if ('svg' === $_format) {
            $url = 'http://html2svg:3000';
            $query = array_filter([
                'url' => 'http://nginx:8080'.$this->generateUrl('default', [
                    'path' => $path,
                    '_format' => 'html',
                    '_locale' => $request->getLocale(),
                    'render_svg' => true,
                ] + $parameters),
                'selector' => '#svg svg',
            ]);
            try {
                $response = $httpClient->request('GET', $url, ['query' => $query]);

                $data = $response->toArray();

                return new Response('<?xml version="1.0" encoding="utf-8" ?>'.$data['content'], Response::HTTP_OK,
                    ['content-type' => 'image/svg+xml']);
            } catch (ClientException $exception) {
                header('content-type: text/plain');
                echo var_export([
                    $exception->getResponse()->toArray(false),
                    $exception->getResponse()->getStatusCode(),
                ], true);
                exit(__FILE__.':'.__LINE__.':'.__METHOD__);
                throw $exception;
                throw new BadRequestHttpException($throwable->getMessage(), previous: $throwable, code: $throwable->getCode());
            }
        }

        try {
            $parameters += [
                'outline_color' => static::OUTLINE_COLOR,
                'outline_width' => static::OUTLINE_WIDTH,
                'urls' => [
                    'svg' => $this->generateUrl('default', ['path' => $path, '_format' => 'svg'] + $parameters),
                ],
            ];

            return $this->render(rtrim($path ?: 'index', '/').'.html.twig', $parameters);
        } catch (LoaderError $loaderError) {
            throw new NotFoundHttpException($loaderError->getMessage());
        }
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

                if (isset($item['parameters']) && is_array($item['parameters'])) {
                    foreach ($item['parameters'] as &$value) {
                        // Expand
                        //
                        //   name: Some parameter
                        //
                        // to
                        //
                        //   name:
                        //     description: Some parameter
                        if (is_string($value)) {
                            $value = [
                                'description' => $value,
                            ];
                        }
                        // Set default type.
                        $value += [
                            'type' => 'string',
                        ];
                    }
                }
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

        // Sort by title.
        usort($items, static fn ($a, $b) => $a['title'] <=> $b['title']);

        return $items;
    }
}
