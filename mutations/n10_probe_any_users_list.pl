s/if \(\$user->id == \$cache->get\(.userid.\)\) \{\n(\s*)return \(bool\) \$cache->get/if (\$cache->get('userid')) {\n$1return (bool) \$cache->get/;
