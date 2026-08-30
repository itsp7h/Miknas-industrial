<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root route is a redirect to the dashboard, not a page of its own —
     * see routes/web.php. Guests then get bounced on to login by the auth
     * middleware, which is asserted separately in Feature/Auth.
     */
    public function test_the_root_route_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
