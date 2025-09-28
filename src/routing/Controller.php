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

namespace gabbro\routing;

use gabbro\http\msg\Request;
use gabbro\http\msg\Response;

/**
 * Defines a routing controller.
 *
 * Controllers are the execution targets of {@see Router} dispatch.  
 * A controller encapsulates the logic for handling a matched route:  
 * it receives the {@see Request}, produces a {@see Response}.
 *
 * Controllers are invoked only when their associated route matches
 * a given request. They are not responsible for routing logic themselves.
 */
interface Controller {
    
    /**
     * Handle a request for a matched route.
     *
     * This method is called by the router when a route resolves to this
     * controller. Implementations should process the request and return
     * a response.
     *
     * @param Request $request   The request to process.
     * @param Router  $router    The router that invoked this controller.
     *
     * @return Response          The response to be delivered to the client.
     */
    function onProcess(Request $request, Router $router): Response;
}

