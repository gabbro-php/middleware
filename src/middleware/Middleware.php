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

namespace gabbro\middleware;

use gabbro\http\msg\Request;
use gabbro\http\msg\Response;

/**
 * Defines a middleware component.
 *
 * Middleware are invoked as part of a processing stack. Each middleware
 * receives the current {@see Request} and a reference to the
 * {@see StackEngine} managing the stack. Middleware can:
 *
 *  - Perform work *before* delegating to the next middleware
 *    (e.g. authentication, logging, mutation of the request).
 *  - Call {@see StackEngine::process()} to pass control to the next
 *    middleware in the stack and receive its {@see Response}.
 *  - Optionally short-circuit the stack by returning a {@see Response}
 *    directly, preventing further middleware from executing.
 *  - Perform work *after* delegating, by wrapping or modifying the
 *    response returned from downstream middleware.
 *
 * This pattern allows middleware to be composed into flexible pipelines
 * where each component can contribute to request and response handling.
 */
interface Middleware {
    
    /**
     * Process the given request as part of the middleware stack.
     *
     * Implementations may:
     *  - Inspect or mutate the request.
     *  - Call {@see StackEngine::process()} to continue to the next middleware.
     *  - Return a {@see Response} immediately to bypass remaining middleware.
     *
     * @param Request     $request  The request to process.
     * @param StackEngine $stack    The stack engine controlling this middleware chain.
     *
     * @return Response             The response to be delivered to the client.
     */
    function onProcess(Request $request, StackEngine $stack): Response;
}

