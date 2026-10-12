<?php
// IDEIGLENES, CSAK OLVASO diagnosztika (probatelepites): mit lat a git a szerveren?
// Nincs bemenete, rogzitett parancsokat futtat. A meres utan TORLENDO.
header('Content-Type: text/plain; charset=utf-8');
$repo = dirname(__DIR__, 2);
$git = is_executable('/usr/bin/git') ? '/usr/bin/git' : '/usr/local/cpanel/3rdparty/bin/git';
$env = ['HOME' => '/home/' . get_current_user(), 'PATH' => '/usr/bin:/bin', 'LANG' => 'C'];
function futtat(array $cmd, string $cwd, array $env): string {
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    if (! is_resource($p)) { return "(nem indult)\n"; }
    $ki = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($p);
    return $ki === '' ? "(ures)\n" : substr($ki, 0, 6000);
}
echo "== repo: $repo | git: $git\n";
echo "== HEAD\n", futtat([$git, '-c', 'safe.directory=*', 'log', '-1', '--format=%h %s'], $repo, $env);
echo "== status --porcelain (ha nem ures, ez blokkolja a Deploy gombot)\n", futtat([$git, '-c', 'safe.directory=*', 'status', '--porcelain', '--untracked-files=all'], $repo, $env);
echo "== diff (a modositott, kovetett fajlok)\n", futtat([$git, '-c', 'safe.directory=*', 'diff', '--stat'], $repo, $env);
echo "== diff public/.htaccess\n", futtat([$git, '-c', 'safe.directory=*', 'diff', '--', 'public/.htaccess'], $repo, $env);
