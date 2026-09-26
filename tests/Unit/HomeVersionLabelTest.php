<?php

use App\Support\HomeVersionLabel;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->projectDirectory = sys_get_temp_dir().'/schooltool-home-version-'.bin2hex(random_bytes(8));
    (new Filesystem)->ensureDirectoryExists($this->projectDirectory.'/.git');
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->projectDirectory);
});

it('shows the branch version and name only outside main', function (): void {
    file_put_contents($this->projectDirectory.'/.git/HEAD', "ref: refs/heads/main\n");
    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('v3.49.7');

    file_put_contents($this->projectDirectory.'/.git/HEAD', "ref: refs/heads/feature/helpers\n");
    file_put_contents($this->projectDirectory.'/UPDATES-helpers.md', "# UPDATES\n\n## 0.1 !!!\n\n### System\n");
    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('App-Version: v3.49.7 · feature/helpers: v0.1');

    file_put_contents($this->projectDirectory.'/UPDATES-helpers.md', "# UPDATES\n\n## unfinished\n\n## 0.1 !!!\n");
    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('App-Version: v3.49.7 · feature/helpers: keine Versionsangabe');

    unlink($this->projectDirectory.'/UPDATES-helpers.md');
    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('App-Version: v3.49.7 · feature/helpers: keine Versionsangabe');
});

it('reads worktree branch metadata and falls back for detached checkouts', function (): void {
    (new Filesystem)->deleteDirectory($this->projectDirectory.'/.git');
    (new Filesystem)->ensureDirectoryExists($this->projectDirectory.'/.metadata');
    file_put_contents($this->projectDirectory.'/.git', "gitdir: .metadata\n");
    file_put_contents($this->projectDirectory.'/.metadata/HEAD', "ref: refs/heads/feature/helpers\n");
    file_put_contents($this->projectDirectory.'/UPDATES-helpers.md', "# UPDATES\n\n## 0.1 !!!\n");

    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('App-Version: v3.49.7 · feature/helpers: v0.1');

    file_put_contents($this->projectDirectory.'/.metadata/HEAD', str_repeat('a', 40)."\n");
    expect(HomeVersionLabel::forProject($this->projectDirectory, '3.49.7'))->toBe('v3.49.7');
});
