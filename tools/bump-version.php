#!/usr/bin/env php
<?php
declare(strict_types=1);

$pluginFile = __DIR__ . '/../fp-restaurant-reservations.php';

$bumpType = 'patch';
$setVersion = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--major') {
        $bumpType = 'major';
    } elseif ($arg === '--minor') {
        $bumpType = 'minor';
    } elseif ($arg === '--patch') {
        $bumpType = 'patch';
    } elseif (str_starts_with($arg, '--set=')) {
        $setVersion = substr($arg, 6);
    } elseif (str_starts_with($arg, '--file=')) {
        $pluginFile = substr($arg, 7);
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php tools/bump-version.php [--major|--minor|--patch] [--set=X.Y.Z] [--file=path]\n");
        exit(0);
    }
}

if (!is_file($pluginFile) || !is_readable($pluginFile)) {
    fwrite(STDERR, "Plugin file not found or unreadable: {$pluginFile}\n");
    exit(1);
}

$contents = file_get_contents($pluginFile);
if ($contents === false) {
    fwrite(STDERR, "Unable to read plugin file: {$pluginFile}\n");
    exit(1);
}

$pattern = '/^(\s*\*\s*Version:\s*)([^\r\n]+)/mi';
if (!preg_match($pattern, $contents, $matches)) {
    fwrite(STDERR, "Version line not found in plugin header.\n");
    exit(1);
}

$currentVersion = trim($matches[2]);

if ($setVersion !== null) {
    if (!preg_match('/^\d+\.\d+\.\d+$/', $setVersion)) {
        fwrite(STDERR, "Invalid version format for --set. Use X.Y.Z\n");
        exit(1);
    }
    $newVersion = $setVersion;
} else {
    $parts = array_map('intval', explode('.', $currentVersion));
    if (count($parts) !== 3) {
        fwrite(STDERR, "Current version is not semantic (X.Y.Z): {$currentVersion}\n");
        exit(1);
    }

    [$major, $minor, $patch] = $parts;

    switch ($bumpType) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        default:
            $patch++;
            break;
    }

    $newVersion = sprintf('%d.%d.%d', $major, $minor, $patch);
}

$count = 0;
$updated = preg_replace_callback(
    $pattern,
    static function (array $match) use ($newVersion): string {
        return $match[1] . $newVersion;
    },
    $contents,
    1,
    $count
);

if ($updated === null || $count === 0) {
    fwrite(STDERR, "Failed to update version line.\n");
    exit(1);
}

if (file_put_contents($pluginFile, $updated) === false) {
    fwrite(STDERR, "Failed to write updated plugin file.\n");
    exit(1);
}

fwrite(STDOUT, $newVersion . "\n");
