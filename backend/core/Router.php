<?php
// backend/core/Router.php

require_once __DIR__ . "/Response.php";

class Router {

    private $routes = [];

    public function add($method, $path, $action) {
        $path = "/" . trim($path, "/");

        $this->routes[] = [
            "method" => strtoupper($method),
            "path"   => $path,
            "action" => $action
        ];
    }

    public function dispatch() {
        $requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";
        $requestUri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);

        // normalize URI
        $requestUri = "/" . trim($requestUri, "/");

        if ($requestMethod === "OPTIONS") {
            http_response_code(200);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route["method"] === $requestMethod && $route["path"] === $requestUri) {
                return $this->callAction($route["action"]);
            }
        }

        Response::notFound("Route not found: " . $requestUri);
    }

    private function callAction($action) {
        if (!is_string($action) || strpos($action, "@") === false) {
            Response::serverError("Invalid route action");
        }

        [$controllerName, $methodName] = explode("@", $action, 2);

        if (!class_exists($controllerName)) {
            Response::serverError("Controller not found: " . $controllerName);
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $methodName)) {
            Response::serverError("Method not found: " . $methodName);
        }

        return call_user_func([$controller, $methodName]);
    }
}
