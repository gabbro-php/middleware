<?php declare(strict_types=1);
/*
 * This file is part of the Gabbro Project: https://github.com/Gabbro-PHP
 *
 * Copyright (c) 2025 Daniel Bergløv, License: MIT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR
 * THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace gabbro\test\routing;

use PHPUnit\Framework\TestCase;
use gabbro\routing\MiddlewareRouter;
use gabbro\http\Request as HttpRequest;
use gabbro\http\msg\Request;
use gabbro\http\msg\Response;
use gabbro\http\Verb;
use gabbro\http\Uri;

final class MiddlewareRouterTest extends TestCase {

    public function testUnnamedRouteWithClosure() {
        $router = new MiddlewareRouter();
        $router->addRoute("/hello", Verb::GET, function(Request $req, $router) {
            $res = $req->getResponse();
            $res->getStream()->write("world");
            return $res;
        });

        $req = new HttpRequest(new Verb(), new Uri("https://domain.com/hello"));
        $res = $router->process($req);

        $this->assertSame("world", (string)$res->getStream());
    }

    public function testNamedRouteDispatchAndReverseLookup() {
        $router = new MiddlewareRouter();
        $router->addNamedRoute("greet", "/greet/{name:alpha}", Verb::GET, function(Request $req, $router) {
            $name = $req->getAttribute("bound-name");
            $res = $req->getResponse();
            $res->getStream()->write("Hi " . $name);
            return $res;
        });

        // Dispatch
        $req = new HttpRequest(new Verb(), new Uri("https://domain.com/greet/Daniel"));
        $res = $router->process($req);

        $this->assertSame("Hi Daniel", (string)$res->getStream());

        // Reverse lookup
        $path = $router->getNamedPath("greet", "Alice");
        $this->assertSame("/greet/Alice", $path);
    }

    public function testNotFound() {
        $router = new MiddlewareRouter();
        $router->addRoute("/exists", Verb::GET, fn(Request $req, $router) => $req->getResponse());

        $req = new HttpRequest(new Verb(), new Uri("https://domain.com/missing"));
        $res = $router->process($req);

        $this->assertSame(Response::STATUS_NOT_FOUND, $res->getStatusCode());
    }
}

