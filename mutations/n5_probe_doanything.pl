s/has_capability\('local\/mail:usemail', \$context, \$user->id, false\)\n(\s*\|\| has_capability\('local\/mail:viewmail')/has_capability('local\/mail:usemail', \$context, \$user->id, true)\n$1/;
