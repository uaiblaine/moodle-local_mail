s/ else if \(\$context->contextlevel == CONTEXT_USER\) \{\n\s*foreach \(label::get_by_user\(user::get\(\$context->instanceid\)\) as \$label\) \{\n\s*\$label->delete\(\);\n\s*\}\n\s*\}//;
