# Placeholder UI

Start the show with

``` shell
task site:update
task site:open
```

See [`templates/component/http-auth-basic.html.twig`](templates/component/http-auth-basic.html.twig) for an example.

## Converting HTML to SVG

We need a browser to render the HTML input before converting to SVG. This is done using [Chrome
PHP](https://github.com/chrome-php/chrome) controlling a headless [Chrome browser](https://www.google.com/chrome/)
running [snapDOM](https://github.com/zumerlab/snapdom).
