# Gabbro - Middleware & Routing

This library provides a **middleware execution engine** and a **routing component** for building HTTP services.  
It is designed to be simple, explicit, and framework-agnostic — you can drop it into any project and wire up your own request/response handling.

---

## Middleware Engine

At the core is the `StackEngine` contract, implemented by `OrderedStackEngine`.

- Middleware are small units of code that take a `Request`, do some work, and then either:
  - Call the next middleware in the stack, or
  - Short-circuit the pipeline and return a `Response`.

- Middleware can be registered in a specific **order**, and can be filtered by **HTTP verb flags**.  
- The engine supports optional **exception catching**, so uncaught exceptions can be converted into `500 Internal Server Error` responses.

This gives you a **pipeline** model where cross-cutting concerns like logging, auth, or body parsing can be layered transparently.

---

## Router

The `MiddlewareRouter` component acts as both:

- A **Router**, capable of dispatching requests to controllers based on HTTP verb and URI pattern.
- A **Middleware**, so it can slot into the stack like any other component.

### Features

- **Path patterns**:
  - `*` wildcard segments.
  - Placeholders: `{id}`, `{id:int}`, `{slug:alpha}`.
  - Value sets: `{status:[open|closed|pending]}`.
  - Optional segments prefixed with `?`.

- **Named routes**:  
  Define routes with a name and later generate the corresponding path with dynamic values (`getNamedPath()`).

- **Binding of route parameters**:  
  When a path matches, placeholders are automatically injected into the request attributes using the key format `bound-{name}`.  
  Example: a route `/users/{id:int}` will yield `$request->getAttribute("bound-id")`.

- **Controller flexibility**:  
  Controllers can be:
  - A callable (closure or function),
  - A class implementing the `Controller` interface, or
  - A class name string (which will be instantiated if it implements `Controller`).

---

## Composition

Because the router itself is a middleware, you can:

- Use `OrderedStackEngine` to build a layered stack:
  ```php
  $engine = new OrderedStackEngine();
  $engine->addMiddleware(10, Verb::ANY, SomeAuthMiddleware::class);
  $engine->addMiddleware(50, Verb::GET|Verb::POST, new MiddlewareRouter());
  $response = $engine->process($request);
  ```

- Or just use the router directly if you don’t need a full middleware stack:
  ```php
  $router = new MiddlewareRouter();
  $router->addRoute("/hello", Verb::GET, fn($req) => new Response("Hello World"));
  $response = $router->process($request);
  ```

---

## Philosophy

This library is intentionally minimal:

- **No global state** — everything is explicit and injectable.
- **Small contracts** (`Middleware`, `StackEngine`, `Router`, `Controller`) define clear extension points.
- **Predictable behavior** — request goes in, response comes out, with optional error handling.

The idea is to give you a **solid foundation** for building your own micro-frameworks, HTTP services, or experimental backends, without forcing a specific architecture or heavy dependency tree.

---
