<?php
$root = dirname(__DIR__, 2); $name = $argv[1] ?? '';
if (!preg_match('/^appearance-[a-z0-9-]{1,30}$/D', $name)) throw new RuntimeException('Choose the task-owned appearance fixture.');
$directory = $root.'/var/'.$name;
if (!is_file($directory.'/compose.env') || is_file($directory.'/accounts.json')) throw new RuntimeException('Fixture configuration required; existing credentials are preserved.');
$accounts = [];
foreach (['reader', 'editor', 'admin'] as $role) {
    $account = ['email' => 'appearance-'.bin2hex(random_bytes(8)).'@example.test', 'password' => bin2hex(random_bytes(24))];
    $accounts[$role] = $account;
    file_put_contents($directory.'/'.$role.'.stdin', $account['password'].PHP_EOL.$account['password'].PHP_EOL); chmod($directory.'/'.$role.'.stdin', 0600);
    file_put_contents($directory.'/'.$role.'.email', $account['email']); chmod($directory.'/'.$role.'.email', 0600);
}
file_put_contents($directory.'/accounts.json', json_encode($accounts, JSON_THROW_ON_ERROR)); chmod($directory.'/accounts.json', 0600);
echo "Isolated production test credentials stored privately.\n";
