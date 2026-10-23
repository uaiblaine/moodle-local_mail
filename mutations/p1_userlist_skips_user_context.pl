s/\n\s*if \(\$context->contextlevel == CONTEXT_USER\) \{\n\s*\$sql = 'SELECT userid FROM \{local_mail_labels\}.*?return;\n\s*\}\n/\n/s;
