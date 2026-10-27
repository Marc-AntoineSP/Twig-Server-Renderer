<?php
// Maintenance-only key generation. Never runs from a request or in a renderer worker.
$directory = dirname(__DIR__, 2).'/var/catalogue/mcp';
if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Cannot create MCP key directory.');
chmod($directory,0700);
$private=$directory.'/oauth-private.pem';$public=$directory.'/oauth-public.pem';$encryption=$directory.'/oauth-encryption.key';
if (is_file($private)!==is_file($public)) throw new RuntimeException('Incomplete existing key pair; restore it rather than replacing it.');
if (!is_file($private)) {
    $key=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
    if (!$key || !openssl_pkey_export($key,$pem)) throw new RuntimeException('Cannot generate OAuth key pair.');
    file_put_contents($private,$pem,LOCK_EX);chmod($private,0600);
    file_put_contents($public,openssl_pkey_get_details($key)['key'],LOCK_EX);chmod($public,0600);
}
if (!is_file($encryption)) {file_put_contents($encryption,bin2hex(random_bytes(32)),LOCK_EX);chmod($encryption,0600);}
// League checks public as well as private key file permissions. Tighten existing
// files without replacing any key bytes.
foreach([$private,$public,$encryption] as $file)chmod($file,0600);
echo "MCP OAuth keys available; existing keys preserved.\n";
