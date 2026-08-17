#!/bin/bash

# The admin dashboard credentials live in the 'users' array as a single
# key => value pair. Replacing the bare words "username" and "password"
# would only rewrite the surrounding comment block, leaving the real
# password at its default, so match the array entry itself.
sed -i "s|'username' => 'secret'|'${username}' => '${password}'|" ${exposeConfigPath}

if [[ $# -eq 0 ]]; then
    exec /src/expose-server serve ${domain} --port ${port} --validateAuthTokens
else
    exec /src/expose-server "$@"
fi
