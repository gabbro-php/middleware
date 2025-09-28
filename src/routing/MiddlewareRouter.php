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

use gabbro\http\msg\Request\Verb;
use gabbro\http\msg\Request;
use gabbro\http\msg\Response;
use gabbro\middleware\Middleware;
use gabbro\middleware\StackEngine;
use gabbro\exception\InvalidInputException;
use gabbro\exception\RoutingException;

/**
 * MiddlewareRouter implements both {@see Router} and {@see Middleware}, providing
 * route-based dispatch of HTTP requests inside a middleware stack.
 *
 * Routes can be registered with {@see MiddlewareRouter::addRoute()} or
 * {@see MiddlewareRouter::addNamedRoute()}:
 *
 *  - Each route may be restricted to specific HTTP verbs (via {@see Verb} flags).
 *  - Route patterns may contain typed placeholders (`{id:int}`, `{name:alpha}`,
 *    `{value:[foo|bar]}`, etc.) and wildcards (`*`).
 *  - Named routes allow reverse path generation via {@see MiddlewareRouter::getNamedPath()}.
 *
 * When {@see MiddlewareRouter::process()} matches a route, it:
 *  - Resolves the controller (callable or {@see Controller}).
 *  - Extracts placeholder matches from the URI.
 *  - Binds them into the request as attributes with keys `bound-{$name}`.
 *    For example, `{id:int}` will be available as `$request->getAttribute("bound-id")`.
 *
 * This class also acts as a {@see Middleware} so it can be composed into
 * a {@see StackEngine} chain directly.
 */
class MiddlewareRouter implements Router, Middleware {

    /** 
     * @ignore
     *
     * @var list<array{
     *      "name": string|null,
     *      "verb": int<1,max>,
     *      "route": string,
     *      "path": string|null,
     *      "controller": string|Controller|callable
     * }>
     */
    protected array $routes = [];
    
    /**
     * Create a new MiddlewareRouter instance.
     *
     * Routes must be
     * registered with {@see MiddlewareRouter::addRoute()} or
     * {@see MiddlewareRouter::addNamedRoute()} before processing requests.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Add a route to the router.
     *
     * The `$path` may contain:
     *  - `*` for wildcard segments.
     *  - `{name}` for generic placeholders.
     *  - `{name:type}` for typed placeholders (`int`, `number`, `alpha`, `word`).
     *  - `{name:[a|b|c]}` for value sets.
     *  - Optional segments prefixed with `?`.
     *
     * @param string $path                              The route path pattern.
     * @param string|Controller|callable $controller    The controller to dispatch when matched.
     * @param int<1,max> $verb                          The verb flags for this route.
     *
     * @return void
     */
    public function addRoute(string $path, int $verb, string|Controller|callable $controller): void {
        $this->routes[] = [
            "name" => null,
            "verb" => $verb,
            "route" => $this->compileRoute($path),
            "path" => null,
            "controller" => $controller
        ];
    }
    
    /**
     * Add a named route to the router.
     *
     * The `$path` may contain:
     *  - `*` for wildcard segments.
     *  - `{name}` for generic placeholders.
     *  - `{name:type}` for typed placeholders (`int`, `number`, `alpha`, `word`).
     *  - `{name:[a|b|c]}` for value sets.
     *  - Optional segments prefixed with `?`.
     *
     * Named routes allow reverse lookup via {@see MiddlewareRouter::getNamedPath()}.
     *
     * @param string $name                                  The unique name of this route.
     * @param string $path                                  The route path pattern.
     * @param string|Controller|callable $controller        The controller to dispatch when matched.
     * @param int<1,max> $verb                              The verb flags for this route.
     *
     * @return void
     */
    public function addNamedRoute(string $name, string $path, int $verb, string|Controller|callable $controller): void {
        $this->routes[] = [
            "name" => $name,
            "verb" => $verb,
            "route" => $this->compileRoute($path),
            "path" => $path,
            "controller" => $controller
        ];
    }

    /**
     * {inheritdoc}
     *
     * Matching is done by:
     *  - Comparing the request verb against the route's verb mask.
     *  - Matching the request URI path against the compiled route regex.
     *
     * When a match is found:
     *  - Controllers are instantiated (if class names) or invoked directly.
     *  - Placeholder values from the URI are bound as request attributes with keys
     *    of the form `bound-{$name}`.
     *
     * If no route matches, a {@see Response} with status
     * {@see Response::STATUS_NOT_FOUND} is returned.
     * 
     * @override {@see Router::process}
     */
    public function process(Request $request): Response {
        $controller = null;
        $path = $request->getUri()->getPath() ?? "/";
        $verb = $request->getVerb();
        
        foreach ($this->routes as $r) {
            if ($verb->hasFlags($r["verb"]) && preg_match($r["route"], $path, $matches)) {
                if (is_string($r["controller"])
                        && class_exists($r["controller"], true)
                        && is_subclass_of($r["controller"], Controller::class)) {
                        
                    $controller = new ($r["controller"])();
                    
                } else if (is_callable($r["controller"]) || !is_string($r["controller"])) {
                    $controller = $r["controller"];
                    
                } else {
                    throw new RoutingException("The class '{$r["controller"]}' must be a member of '". Controller::class ."'");
                }
                
                $vars = array_filter($matches, "is_string", ARRAY_FILTER_USE_KEY);
                foreach ($vars as $name => $value) {
                    $request->setAttribute("bound-{$name}", $value);
                }
                
                break;
            }
        }
        
        if ($controller === null) {
            $response = $request->getResponse();
            $response->setStatus(Response::STATUS_NOT_FOUND);
        
            return $response;

        } else if ($controller instanceof Controller) {
            return $controller->onProcess($request, $this);

        } else {
            return $controller($request, $this);
        }
    }
    
    /**
     * Middleware adapter for {@see StackEngine}.
     *
     * This allows the router to be placed inside a middleware stack.
     * Internally, this simply calls {@see MiddlewareRouter::process()}.
     *
     * @param Request $request                 The request to process.
     * @param StackEngine $stack               The current stack engine.
     *
     * @return Response                        The resulting response.
     * @override {@see Middleware::onProcess}
     */
    public function onProcess(Request $request, StackEngine $stack): Response {
        return $this->process($request);
    }
    
    /**
     * Generate a path string for a named route.
     *
     * Replaces placeholders in the route pattern with the provided values.
     * Supports optional segments, typed placeholders (`int`, `number`, `alpha`, `word`),
     * value sets (`[foo|bar]`), and star segments (`*`).
     *
     * @param string $name                     The name of the route.
     * @param string|int|float ...$values      Values to substitute into placeholders.
     *
     * @return string                          The generated path.
     *
     * @throws InvalidInputException           If the route is not found,
     *                                         values are missing or invalid,
     *                                         or too many values are provided.
     */
    public function getNamedPath(string $name, string|int|float ...$values): string {
        $path = null;
        
        foreach ($this->routes as $r) {
            if ($r["name"] === $name) {
                $path = $r["path"];
                break;
            }
        }
        
        if ($path === null) {
            throw new InvalidInputException("Could not any path with the name \"{$name}\"");
            
        } else if ($path == "" || $path == "/") {
            if (!empty($values)) {
                throw new InvalidInputException("Too many values for root route.");
            }
            
            return "/";
        }

        $segments = explode("/", trim($path, "/"));
        $i = 0; // index into $values
        $n = count($values);
        $out = "";

        // Pre-parse segments: classify, tokenize, count placeholders
        $parsed = [];
        foreach ($segments as $seg) {
            $optional = ($seg != "" && $seg[0] == "?");
            if ($optional) $seg = substr($seg, 1);

            if ($seg === "*") {
                $parsed[] = ["type" => "star", "optional" => $optional];
                continue;
            }

            // Tokenize into literals and placeholders
            $tokens = preg_split(
                "/(\{[a-z][a-z0-9_]*(?::(?:int|number|alpha|word|\[[^}]+\]))?\})/i",
                $seg,
                -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
            ) ?: [];

            $need = 0;
            foreach ($tokens as $t) {
                if ($t[0] == "{") $need++;
            }

            $parsed[] = [
                "type"        => "normal",
                "optional"    => $optional,
                "raw"         => $seg,
                "tokens"      => $tokens,
                "placeholders"=> $need,
            ];
        }

        // Helper: minimum required placeholders remaining after index $k (excluding optional segments)
        $minRequiredAfter = static function(int $k) use ($parsed): int {
            $sum = 0;
            for ($j = $k + 1; $j < count($parsed); $j++) {
                $seg = $parsed[$j];
                if ($seg["type"] == "normal" && !$seg["optional"]) {
                    $sum += $seg["placeholders"];
                }
            }
            return $sum;
        };

        // Build
        foreach ($parsed as $idx => $seg) {
            if ($seg["type"] == "star") {
                // Give "*" as many values as possible while keeping enough for later required placeholders
                $reserve = $minRequiredAfter($idx);
                $canTake = max(0, $n - $i - $reserve);

                for ($t = 0; $t < $canTake; $t++) {
                    $v = $values[$i++];
                    $s = (string)$v;
                    
                    if (strpos($s, "/") !== false) {
                        throw new InvalidInputException("Value \"{$s}\" contains \"/\" which is not allowed for a single path segment.");
                    }
                    
                    $out .= "/" . $s; 
                }
                // If optional "*" and zero values, it simply emits nothing.
                continue;
            }

            // Non-star segment
            $need = $seg["placeholders"];

            if ($need === 0) {
                // Literal-only segment
                $out .= "/" . $seg["raw"];
                continue;
            }

            // Decide inclusion for optional segments
            if ($seg["optional"] && ($i + $need > $n)) {
                // Not enough values for this optional segment -> skip it entirely
                continue;
            }

            if ($i + $need > $n) {
                throw new InvalidInputException(
                    "Not enough values to fill required segment \"" . $seg["raw"] . "\" (needs {$need})."
                );
            }

            // Build this segment from tokens
            $built = "";
            foreach ($seg["tokens"] as $t) {
                if ($t[0] !== "{") {
                    // literal part (exactly as in template)
                    $built .= $t;
                    continue;
                }

                // Parse placeholder
                if (!preg_match(
                    "/^\{(?P<name>[a-z][a-z0-9_]*)(?::(?P<type>int|number|alpha|word|\[(?P<alts>[^\]]+)\]))?\}$/i",
                    $t,
                    $m
                )) {
                    // Malformed placeholder: treat literally
                    $built .= $t;
                    continue;
                }

                if ($i >= $n) {
                    throw new InvalidInputException("Missing value for placeholder {$t}.");
                }

                $val = $values[$i++];
                $str = (string)$val;

                // Validate & emit
                if (!empty($m["alts"])) {
                    // Case-insensitive membership
                    $ok = false;
                    
                    foreach (explode("|", $m["alts"]) as $a) {
                        if (strcasecmp($a, $str) === 0) { $ok = true; break; }
                    }
                    
                    if (!$ok) {
                        throw new InvalidInputException("Value \"{$str}\" not allowed for {$t}.");
                        
                    } else if (strpos($str, "/") !== false) {
                        throw new InvalidInputException("Value \"{$str}\" contains \"/\" which is not allowed in {$t}.");
                    }
                    $built .= $str;
                    
                } else {
                    $type = strtolower($m["type"] ?? "");
                    
                    switch ($type) {
                        case "int":
                            if (!is_int($val) && !ctype_digit($str)) {
                                throw new InvalidInputException("Value \"{$str}\" is not a valid int for {$t}.");
                            }
                            if (strpos($str, "/") !== false) {
                                throw new InvalidInputException("Value \"{$str}\" contains \"/\" which is not allowed in {$t}.");
                            }
                            $built .= $str;
                            break;

                        case "number":
                            if (!preg_match("/^\d+(?:\.\d+)?$/", $str)) {
                                throw new InvalidInputException("Value \"{$str}\" is not a valid number for {$t}.");
                            }
                            if (strpos($str, "/") !== false) {
                                throw new InvalidInputException("Value \"{$str}\" contains \"/\" which is not allowed in {$t}.");
                            }
                            $built .= $str;
                            break;

                        case "alpha":
                            if (!preg_match("/^[A-Za-z_-]+$/", $str)) {
                                throw new InvalidInputException("Value \"{$str}\" is not valid for alpha in {$t}.");
                            }
                            $built .= $str;
                            break;

                        case "word":
                            if (!preg_match("/^[A-Za-z_ ]+$/", $str)) {
                                throw new InvalidInputException("Value \"{$str}\" is not valid for word in {$t}.");
                            }
                            $built .= $str;
                            break;

                        default:
                            if (strpos($str, "/") !== false) {
                                throw new InvalidInputException("Value \"{$str}\" contains \"/\" which is not allowed in {$t}.");
                            }
                            $built .= $str;
                    }
                }
            }

            $out .= "/" . $built;
        }

        // If any values left and no "*" consumed them → error (helps catch bugs)
        if ($i < $n) {
            throw new InvalidInputException("Too many values: ".($n - $i)." unused.");
        }

        return $out == "" ? "/" : $out;
    }

    /**
     * @ignore
     *
     * Compile a route pattern into a regex for matching request paths.
     *
     * Recognizes:
     *  - `*` for wildcard segments.
     *  - `{name}` for generic placeholders.
     *  - `{name:type}` for typed placeholders (`int`, `number`, `alpha`, `word`).
     *  - `{name:[a|b|c]}` for value sets.
     *  - Optional segments prefixed with `?`.
     *
     * Anchors are strict (`\A..\z`) and `/J` is used to allow duplicate param names.
     *
     * @param string $route
     * @return string
     */
    protected function compileRoute(string $route): string {
        // Match "" or "/" only
        if ($route == "" || $route == "/") {
            // \A..\z + D => strict anchors
            return "~\A/?\z~";
        }

        $delim = "~";
        $pieces = [];

        foreach (explode("/", trim($route, "/")) as $segment) {
            $optional = ($segment != "" && $segment[0] == "?");
            
            if ($optional) {
                $segment = substr($segment, 1);
            }

            if ($segment == "*") {
                // Any number of additional path segments, no empty segments
                $part = "(?:/[^/]+)*";
                
            } else {
                // Split into literals and placeholders like {name[:type]}
                $tokens = preg_split(
                    "/(\{[a-z][a-z0-9_]*(?::(?:int|number|alpha|word|\[[^}]+\]))?\})/i",
                    $segment,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
                ) ?: [];

                $built = "";
                
                foreach ($tokens as $t) {
                    if ($t[0] != "{") {
                        // Quote only non-placeholders
                        $built .= preg_quote($t, $delim);
                        continue;
                    }

                    // {name[:type]} — parse name and optional type
                    if (!preg_match(
                        "/^\{(?P<name>[a-z][a-z0-9_]*)(?::(?P<type>int|number|alpha|word|\[(?P<alts>[^\]]+)\]))?\}$/i",
                        $t,
                        $m
                    )) {
                        // Fallback: treat as literal if malformed
                        $built .= preg_quote($t, $delim);
                        continue;
                    }

                    $name = $m["name"];
                    
                    if (!empty($m["alts"])) {
                        // {name:[a|b|c]}  →  (?<name>(?:a|b|c))
                        $alts = implode("|", array_map(static fn($s) => preg_quote($s, $delim), explode("|", $m["alts"])));
                        $built .= "(?P<" . $name . ">(?:" . $alts . "))";
                        
                    } else {
                        // Built-ins (tuned)
                        $type = strtolower($m["type"] ?? "");
                        $built .= match ($type) {
                            "int"    => "(?P<" . $name . ">\d+)",
                            // one optional dot with digits on both sides
                            "number" => "(?P<" . $name . ">\d+(?:\.\d+)?)",
                            // letters with - or _ (ASCII)
                            "alpha"  => "(?P<" . $name . ">[A-Za-z_-]+)",
                            // "word": letters/underscore/spaces
                            "word"   => "(?P<" . $name . ">[A-Za-z_ ]+)",
                            default  => "(?P<" . $name . ">[^/]+)", // default: any non-/ chars
                        };
                    }
                }

                // Prefix each concrete segment with "/"
                $part = "/" . $built;
            }

            // Optionally wraps the whole "/segment"
            $pieces[] = $optional ? "(?:" . $part . ")?" : $part;
        }

        // Allow duplicate param names across segments via /J
        return $delim . "\A" . implode("", $pieces) . "/?\z" . $delim . "J";
    }
}

