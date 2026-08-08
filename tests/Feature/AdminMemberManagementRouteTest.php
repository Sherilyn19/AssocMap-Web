<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class AdminMemberManagementRouteTest extends TestCase
{
    public function test_member_management_routes_are_registered_with_safe_http_verbs(): void
    {
        $index = RouteFacade::getRoutes()->getByName('members.index');
        $show = RouteFacade::getRoutes()->getByName('members.show');
        $update = RouteFacade::getRoutes()->getByName('members.update');
        $archive = RouteFacade::getRoutes()->getByName('members.archive');
        $applications = RouteFacade::getRoutes()->getByName('members.applications.index');

        $this->assertInstanceOf(Route::class, $index);
        $this->assertInstanceOf(Route::class, $show);
        $this->assertInstanceOf(Route::class, $update);
        $this->assertInstanceOf(Route::class, $archive);
        $this->assertInstanceOf(Route::class, $applications);

        $this->assertContains('GET', $index->methods());
        $this->assertContains('GET', $show->methods());
        $this->assertContains('PUT', $update->methods());
        $this->assertContains('PATCH', $archive->methods());
        $this->assertContains('GET', $applications->methods());
    }

    public function test_member_management_routes_are_admin_protected_and_have_no_admin_approval_or_delete_route(): void
    {
        $memberRoutes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'members.'));

        $this->assertNotEmpty($memberRoutes);

        $memberRoutes->each(function (Route $route): void {
            $this->assertContains(
                'assocmap.auth:System Administrator',
                $route->gatherMiddleware(),
                "Route {$route->getName()} must remain System Administrator only."
            );
        });

        $routeNames = $memberRoutes
            ->map(fn (Route $route): string => (string) $route->getName())
            ->values();

        $this->assertFalse($routeNames->contains(
            fn (string $name): bool => str_contains($name, 'approve')
                || str_contains($name, 'reject')
                || str_contains($name, 'delete')
                || str_contains($name, 'destroy')
        ));
    }
}