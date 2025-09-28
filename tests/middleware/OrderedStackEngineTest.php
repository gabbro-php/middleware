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
namespace gabbro\test\middleware;

use PHPUnit\Framework\TestCase;
use gabbro\http\Request as HttpRequest;
use gabbro\http\msg\Request;
use gabbro\http\msg\Response;
use gabbro\http\msg\Request\Verb;
use gabbro\middleware\OrderedStackEngine;

final class OrderedStackEngineTest extends TestCase {
    public function testExecutesMiddlewareInOrder(): void {
        $engine = new OrderedStackEngine();

        $calls = [];

        // Lower order executes first
        $engine->addMiddleware(2, Verb::ANY, function (Request $req, OrderedStackEngine $stack) use (&$calls): Response {
            $calls[] = "second";
            $resp = $req->getResponse();
            $resp->addHeader("X-Second", "done");
            return $resp;
        });

        // Higher order executes later
        $engine->addMiddleware(1, Verb::ANY, function (Request $req, OrderedStackEngine $stack) use (&$calls): Response {            
            $calls[] = "first";
            $resp = $stack->process($req);
            $resp->addHeader("X-First", "done");
            return $resp;
        });
        
        // Should not run at all
        $engine->addMiddleware(0, Verb::POST, function (Request $req, OrderedStackEngine $stack) use (&$calls): Response {            
            $calls[] = "none";
            return $stack->process($req);
        });

        $request = new HttpRequest();
        $response = $engine->process($request);

        $this->assertSame(["first", "second"], $calls, "Middleware did not execute in expected order.");
        $this->assertSame("done", $response->getHeader("X-First"));
        $this->assertSame("done", $response->getHeader("X-Second"));
    }

    public function testStopsOnShortCircuit(): void {
        $engine = new OrderedStackEngine();

        $engine->addMiddleware(0, Verb::ANY, function (Request $req, OrderedStackEngine $stack): Response {
            // Short-circuit: return response directly without calling $stack->process()
            $resp = $req->getResponse();
            $resp->setStatus(Response::STATUS_NOT_FOUND);
            return $resp;
        });

        $engine->addMiddleware(0, Verb::ANY, function (Request $req, OrderedStackEngine $stack): Response {
            $this->fail("This middleware should not run after a short-circuit.");
        });

        $request = new HttpRequest();
        $response = $engine->process($request);

        $this->assertSame(Response::STATUS_NOT_FOUND, $response->getStatusCode());
    }

    public function testCatchesExceptionsIfEnabled(): void {
        $engine = new OrderedStackEngine();
        $engine->setCatchExceptions(true);

        $engine->addMiddleware(0, Verb::ANY, function (Request $req, OrderedStackEngine $stack): Response {
            throw new \RuntimeException("boom");
        });

        $request = new HttpRequest();
        $response = $engine->process($request);

        $this->assertSame(Response::STATUS_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }
}
