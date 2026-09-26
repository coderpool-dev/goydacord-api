<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteStructureTest extends TestCase
{
    public function test_api_controller_actions_and_dependencies_can_be_resolved(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || $route->getAction('controller') === null) {
                continue;
            }

            $this->assertTrue(
                is_callable([$route->getController(), $route->getActionMethod()]),
                $route->getActionName(),
            );
        }
    }

    public function test_api_routes_are_named_once(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/'))
            ->map(fn (RoutingRoute $route) => $route->getName());

        $this->assertNotContains(null, $names);
        $this->assertSame($names->count(), $names->unique()->count());
    }

    public function test_access_boundaries_and_compatible_role_methods(): void
    {
        $this->assertRoute('ping', 'GET', 'api/ping', ['throttle:ping'], ['auth:sanctum']);
        $this->assertRoute('uploads.store', 'POST', 'api/uploads', ['auth:sanctum', 'demo.restrict', 'throttle:uploads'], ['verified.email', 'throttle:api']);
        $this->assertRoute('auth.profile.show', 'GET', 'api/auth/profile', ['auth:sanctum', 'throttle:api'], ['verified.email']);
        $this->assertRoute('channels.members.role.update', 'PATCH', 'api/channels/{channel}/members/{member}/role', ['auth:sanctum', 'verified.email']);
        $this->assertRoute('messages.attachment.store', 'POST', 'api/messages/attachment', ['auth:sanctum', 'verified.email', 'throttle:attachments']);
        $this->assertRoute('servers.members.nickname.update', 'PUT', 'api/servers/{server}/members/{member}/nickname', ['auth:sanctum', 'verified.email', 'throttle:settings-write']);
        $this->assertRoute('servers.roles.update', 'PATCH', 'api/servers/{server}/roles/{role}', ['auth:sanctum', 'verified.email', 'throttle:settings-write']);
        $this->assertRoute('servers.roles.update-legacy', 'POST', 'api/servers/{server}/roles/{role}', ['auth:sanctum', 'verified.email', 'throttle:settings-write']);
        $this->assertRoute('admin.users.index', 'GET', 'api/admin/users', ['auth:sanctum', 'verified.email', 'admin']);
    }

    /** @param list<string> $required
     * @param  list<string>  $absent
     */
    private function assertRoute(string $name, string $method, string $uri, array $required, array $absent = []): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, $name);
        $this->assertSame($uri, $route->uri());
        $this->assertContains($method, $route->methods());

        $middleware = $route->gatherMiddleware();

        foreach ($required as $item) {
            $this->assertContains($item, $middleware, $name);
        }

        foreach ($absent as $item) {
            $this->assertNotContains($item, $middleware, $name);
        }
    }
}
