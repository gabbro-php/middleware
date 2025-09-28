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

use gabbro\exception\InvalidInputException;
use gabbro\http\msg\Request\Verb;
use gabbro\http\msg\Request;
use gabbro\http\msg\Response;
use gabbro\io\IOStream;
use gabbro\utils\Shift;
use Throwable;

/**
 * OrderedStackEngine provides a stack-based middleware execution engine
 * with explicit ordering and verb filtering.
 *
 * Middleware can be added with an associated `order` and `verb` mask:
 *  - Middleware is filtered based on the request verb at the start of a
 *    top-level {@see OrderedStackEngine::process()} call.
 *  - The filtered middleware is sorted by `order`, ensuring predictable
 *    execution order. Lower order values are executed earlier.
 *  - Controllers can be either callables or classes/instances implementing
 *    {@see Middleware}.
 *
 * This implementation is suitable for HTTP request/response processing
 * pipelines or any scenario where ordered, verb-based middleware
 * dispatch is required.
 */
class OrderedStackEngine implements StackEngine {

    /**
     * @ignore
     *
     * @var list<array{
     *      "order": int,
     *      "verb": int<1,max>,
     *      "controller": string|Middleware|callable
     * }>
     */
    protected array $middleware = [];
    
    /**
     * @ignore
     *
     * @var list<array{
     *      "order": int,
     *      "verb": int<1,max>,
     *      "controller": string|Middleware|callable
     * }>
     */
    protected array $stack = [];
    
    /**
     * @ignore
     * 
     * @var bool 
     */
    protected bool $catchExceptions = false;
    
    /**
     * @ignore
     *
     * @var int<0,max>
     */
    protected int $level = 0;

    /**
     * Create a new, empty OrderedStackEngine.
     *
     * Middleware must be added with {@see OrderedStackEngine::addMiddleware()}
     * before {@see OrderedStackEngine::process()} can execute a stack.
     *
     * @return void
     */
    public function __construct() {}
    
    /**
     * Configure whether to catch exceptions or not.
     *
     * If this is set, the stack engine will catch exceptions that is
     * not catched by the middleware being executed. It will write a 
     * trace error message to `stderr` and return a new Response with
     * a server error code to the middleware that called {@see StackEngine::process()}.
     *
     * @param bool $catch       Whether or not to catch exceptions.
     *
     * @return void
     */
    public function setCatchExceptions(bool $catch = true): void {
        $this->catchExceptions = $catch;
    }
    
    /**
     * Add a middleware to this stack engine.
     *
     * @param string|Middleware|callable $middleware        The middleware to add.
     *                                                      This can be a callable object/string or
     *                                                      a class object/string. If the middleware is a class/object,
     *                                                      then it must be a member of {@see Middleware}.
     *
     * @param int $order                                    The order of execution relative to other middleware that has been added. 
     *                                                      Lowest order is executed first.
     *
     * @param int<1,max> $verb                              Verb flags that this middleware should respond to. 
     *                                                      These can be found in {@see Verb}.
     *
     * @return void
     */
    public function addMiddleware(int $order, int $verb, string|Middleware|callable $middleware): void {
        array_unshift($this->middleware, [
            "order" => $order,
            "verb" => $verb,
            "controller" => $middleware
        ]);
    }
    
    /**
     * {inheritdoc}
     *
     * @override {@see StackEngine::process()}
     */
    public function process(Request $request): Response {
        $this->level++;
        
        try {
            if ($this->catchExceptions) {
                try {
                    return $this->processStack($request);
                
                } catch (Throwable $e) {
                    $stream = IOStream::getInstance(IOStream::STDERR);
                    $stream->println(
                        Shift::toString($e)
                    );
                }

                $response = $request->getResponse();
                $response->setStatus( Response::STATUS_INTERNAL_SERVER_ERROR );
                
                return $response;
            }
            
            return $this->processStack($request);
            
        } finally {
            $this->level--;
        }
    }
    
    /**
     * @ignore
     *
     * @param Request $request
     * @return Response
     */
    protected function processStack(Request $request): Response {
        if ($this->level == 1) {
            $verb = $request->getVerb();
            
            /*
             * list<VAL> != array<int<0,max>, VAL> apparently.
             */
            // @phpstan-ignore-next-line
            $this->stack = array_filter($this->middleware, fn($item) => $verb->hasFlags( $item["verb"] ));
            
            // Sort stack by order, making sure the first is last
            usort($this->stack, fn($a, $b) => $b["order"] <=> $a["order"]);
        }
        
        $item = array_pop($this->stack);
        
        if ($item !== null) {
            return $this->processMiddleware($request, $item["controller"]);
        }
        
        return $request->getResponse();
    }
    
    /**
     * @ignore
     *
     * @param Request $request
     * @param string|Middleware|callable $controller
     * @return Response
     */
    protected function processMiddleware(Request $request, string|Middleware|callable $controller): Response {
        if (is_string($controller)
                && class_exists($controller, true)
                && is_subclass_of($controller, Middleware::class)) {

            $controller = new ($controller)();

        } else if (!is_callable($controller) && is_string($controller)) {
            throw new InvalidInputException("The class '$controller' must be a member of '". Middleware::class ."'");
        }

        if ($controller instanceof Middleware) {
            $response = $controller->onProcess($request, $this);

        } else {
            $response = ($controller)($request, $this);
        }
        
        return $response;
    }
}

