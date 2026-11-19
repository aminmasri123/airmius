set -Eeuo pipefail
root=/home/u270834523/.airmius-deploy/current
probe="$root/airmius-deploy-routing-probe-20261119"
test ! -e "$probe"
mkdir -p "$probe/public" "$probe/vendor"
chmod 755 "$probe" "$probe/public" "$probe/vendor"
cat > "$probe/.htaccess" <<'HTACCESS'
Options -Indexes
DirectoryIndex index.php
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^(?!public/)(.*)$ public/$1 [L]
</IfModule>
HTACCESS
cat > "$probe/public/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
</IfModule>
HTACCESS
cat > "$probe/public/index.php" <<'PHP'
<?php
if (str_contains($_SERVER['REQUEST_URI'], '/vendor/')) { http_response_code(404); exit; }
echo 'airmius-public-routing-ok';
PHP
printf 'must-not-be-served' > "$probe/vendor/probe.txt"
chmod 644 "$probe/.htaccess" "$probe/public/.htaccess" "$probe/public/index.php" "$probe/vendor/probe.txt"
trap 'rm -f "$probe/vendor/probe.txt" "$probe/public/index.php" "$probe/public/.htaccess" "$probe/.htaccess"; rmdir "$probe/vendor" "$probe/public" "$probe"' EXIT
response=$(curl --fail --silent --show-error --max-time 20 https://airmius.com/airmius-deploy-routing-probe-20261119/)
test "$response" = 'airmius-public-routing-ok'
code=$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 20 https://airmius.com/airmius-deploy-routing-probe-20261119/vendor/probe.txt)
test "$code" = 404
printf 'Hostinger root-to-public routing and private-file isolation verified.\n'
