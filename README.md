# Placeholder UI

Start the show with

``` shell
task site:update
task site:open
```

See [`templates/component/http-auth-basic.html.twig`](templates/component/http-auth-basic.html.twig) for an example.

## Converting HTML to SVG

We need a browser to render the HTML input before converting to SVG. This is done via a custom API server, `html2svg`,
using [Puppetteer](https://pptr.dev/) controlling a headless [Chrome browser](https://www.google.com/chrome/) running
[snapDOM](https://github.com/zumerlab/snapdom).

``` mermaid
sequenceDiagram
    client->>app: GET /component/http-auth-basic.svg
    app->>html2svg: GET /?url=/component/http-auth-basic%3Frender=svg&selector=svg
    Note right of html2svg: Load /component/http-auth-basic?render=svg in headless browser
    Note right of html2svg: Wait for selector to match an element
    Note right of html2svg: Extract SVG from matched element
    html2svg->>app: { status: "OK", content: "<svg …/>" }
    app->>client: <svg …/>
```

Note: For brevity the (internal) server names and ports, `http://html2svg:3000` and `http://phpfpm:8080`, are ommitted
from the diagram.
