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
 * Defines a generic middleware stack engine.
 *
 * A stack engine coordinates the sequential execution of middleware
 * components against a {@see Request}, producing a {@see Response}.
 * 
 * Implementations are responsible for managing the stack of middleware
 * (ordering, filtering, invocation, etc.) and ensuring that each
 * middleware is given the opportunity to process the request and pass
 * control forward through the stack.
 */
interface StackEngine {

    /**
     * Process the middleware stack with the given request.
     *
     * The engine dispatches the next middleware in the stack, or returns
     * a response if no further middleware are available.
     *
     * @param Request $request   The request to process.
     *
     * @return Response          The resulting response.
     */
    function process(Request $request): Response;
}
