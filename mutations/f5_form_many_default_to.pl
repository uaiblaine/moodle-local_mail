s/(in_array\(\$role, \['to', 'bcc'\], true\)\) \{\n\s*\$role = )count\(\$recipients\) > 1 \? 'bcc' : 'to'/$1'to'/;
