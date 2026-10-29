s/\n\s*if \(!\$user->can_use_mail\(\$message->course\)\) \{\n[^\n]*\n\s*\}\n(\n\s*\$data = message_data::reply\()/\n$1/;
