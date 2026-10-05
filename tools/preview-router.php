<?php
// PHP's preview server does not enforce Apache .htaccess rules.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)(?:\.[^/]*|config|tools|requirement_docs)(?:/|$)~i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
