# Placeholder UI

``` shell
docker compose pull
docker compose up --detach --remove-orphans
open "http://$(docker compose port php 80)"
```

We use [html2svg](https://github.com/fathyb/html2svg) to convert HTML to SVG.

``` shell name=html2svg-example
# curl "http://$(docker compose port html2svg 8080)" --data '{"url": "https://example.com", "format": "svg"}'
curl "http://$(docker compose port html2svg 8080)" --data '{"url": "http://nginx:8080/component/http-auth-basic", "format": "svg"}'
```

> [!WARNING]
> html2svg looks abandoned. We may have to restore it to former glory.

See [`templates/component/http-auth-basic.html.twig`](templates/component/http-auth-basic.html.twig) for an example.

## Examples

<http://127.0.0.1:8888/component/http-auth-basic?>
