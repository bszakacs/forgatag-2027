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
echo "== error_log (a tarolo gyokereben, utolso 3000 bajt)\n";
$el = $repo . '/error_log';
echo is_readable($el) ? substr((string) file_get_contents($el), -3000) . "\n" : "(nincs)\n";
$naplok = glob('/home/' . get_current_user() . '/.cpanel/logs/*deploy*.log') ?: [];
usort($naplok, fn ($a, $b) => filemtime($b) <=> filemtime($a));
echo "== legfrissebb telepitesi naplo: ", $naplok[0] ?? '(nincs)', "\n";
echo isset($naplok[0]) ? substr((string) file_get_contents($naplok[0]), -6000) : '', "\n";
