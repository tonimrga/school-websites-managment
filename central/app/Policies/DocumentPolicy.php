<?php

namespace App\Policies;

/**
 * Documents follow the shared content rules: editors act on their own
 * school, the super admin on the school picked in the switcher. Downloading
 * in the dashboard is checked as view.
 */
class DocumentPolicy extends SchoolContentPolicy
{
    //
}
