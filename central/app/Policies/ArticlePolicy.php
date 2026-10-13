<?php

namespace App\Policies;

/**
 * Articles follow the shared content rules: editors act on their own school,
 * the super admin on the school picked in the switcher. Publishing, preview
 * and captions are checked as update/view.
 */
class ArticlePolicy extends SchoolContentPolicy
{
    //
}
