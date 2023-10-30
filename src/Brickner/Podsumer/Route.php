<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

class Route
{
    private array $routes = [];
    public array $matchedRoute = [];

    public function __construct(
        private string $route,
        private string $method
    ) {
        $this->collectDefinedRoutes();

        $this->matchedRoute = $this->matchRoute($route, $method);
    }

    private function collectDefinedRoutes()
    {
        foreach (get_defined_functions()['user'] as $fnName) {
            $ref = new \ReflectionFunction($fnName);
            foreach ($ref->getAttributes() as $attr) {
                $name = $attr->getName();
                if ($name === 'Route') {
                    $args = $attr->getArguments();
                    $this->routes[$args[0]] = [
                        $fnName,
                        $args[1]
                    ];
                }
            }
        }
    }

    private function matchRoute(string $route, string $method): array
    {
        foreach ($this->routes as $definedRoute => $fn) {

            if (
                   $definedRoute === $route
                && (
                    (
                           is_array($fn[1])
                        && in_array($method, $fn[1])
                    )
                    || $fn[1] === $method
                )
            ) {
                return $fn;
            }
        }

        return [];
    }
}

