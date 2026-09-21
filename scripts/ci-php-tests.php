<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use Symfony\Component\Process\Process;

class CiPhpTests
{
    private const SqliteTests = ['tests/Feature/TeachingBackupCurriculumAttachmentsTest.php', 'tests/Feature/TeachingBackupEntryAreasTest.php'];

    private const SnapshotTests = ['tests/Feature/FeaturePreviewReadOnlySourceTest.php', 'tests/Feature/FeaturePreviewSnapshotTest.php'];

    /** @return list<list<string>> */
    public static function batches(string $root, string $mode): array
    {
        if (! in_array($mode, ['coverage', 'windows'], true)) {
            throw new InvalidArgumentException('Unknown CI test mode.');
        }

        $files = [];

        if ($mode === 'windows') {
            $files = ['tests/Unit/GitBranchWorkflowTest.php', 'tests/Unit/GitDeploymentSshTest.php', 'tests/Unit/WorkflowTestDatabaseTest.php', 'tests/Unit/PreviewDeploymentTest.php', 'tests/Unit/DeploymentLauncherTest.php'];
        } else {
            foreach (['tests/Unit', 'tests/Feature'] as $directory) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

                foreach ($iterator as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    }
                }
            }
        }

        sort($files, SORT_STRING);

        if ($files === []) {
            throw new RuntimeException('No PHP tests discovered.');
        }

        foreach ($files as $file) {
            if (! is_file($root.'/'.$file)) {
                throw new RuntimeException('A required test file is missing: '.$file);
            }
        }

        if ($mode === 'windows') {
            return array_chunk($files, 10);
        }

        $batches = array_chunk(array_values(array_diff($files, self::SqliteTests, self::SnapshotTests)), 10);

        foreach ([self::SnapshotTests, self::SqliteTests] as $dedicated) {
            $present = array_values(array_intersect($dedicated, $files));

            if ($present !== []) {
                $batches[] = $present;
            }
        }

        return $batches;
    }

    /**
     * @param  list<list<string>>  $batches
     * @param  callable(list<string>, int): int  $execute
     */
    public static function executeBatches(array $batches, callable $execute): void
    {
        foreach ($batches as $index => $files) {
            if ($files === [] || count($files) > 10) {
                throw new RuntimeException('CI test batches require one to ten files.');
            }

            if ($execute($files, $index + 1) !== 0) {
                throw new RuntimeException('PHP test batch '.($index + 1).' failed; later batches were not run.');
            }
        }
    }

    public static function mergeCoverage(?CodeCoverage $coverage, string $path): CodeCoverage
    {
        if (! is_file($path)) {
            throw new RuntimeException('A successful batch did not produce its required coverage report.');
        }

        $batchCoverage = require $path;

        if (! $batchCoverage instanceof CodeCoverage) {
            throw new RuntimeException('Invalid batch coverage report.');
        }

        if ($coverage === null) {
            return $batchCoverage;
        }

        $coverage->merge($batchCoverage);

        return $coverage;
    }

    /** @param list<string> $arguments */
    public static function main(array $arguments): int
    {
        try {
            $mode = $arguments[1] ?? '';
            $root = dirname(__DIR__);
            $batches = self::batches($root, $mode);

            if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('APP_ENV') !== 'testing') {
                throw new RuntimeException('This runner is restricted to the isolated GitHub Actions testing environment.');
            }

            if ($mode === 'coverage' && (getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_DATABASE') !== 'pest_test')) {
                throw new RuntimeException('The CI MySQL service must use its dedicated local pest_test database.');
            }

            $directory = $root.'/storage/logs/ci-php-'.bin2hex(random_bytes(8));
            mkdir($directory, 0700, true);
            $coverage = null;

            self::executeBatches($batches, function (array $files, int $number) use ($root, $directory, $mode, &$coverage): int {
                $prefix = $directory.'/batch-'.$number;
                file_put_contents($prefix.'.json', json_encode($files, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                $command = [PHP_BINARY, 'vendor/bin/pest', '--compact', '--exclude-group=integration'];
                $environment = ['TEST_TOKEN' => 'ci-batch-'.$number];

                if ($mode === 'coverage') {
                    $command[] = '--coverage-php='.$prefix.'.coverage.php';

                    if (array_intersect($files, self::SqliteTests) !== []) {
                        $environment += ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_DATABASE_TEST' => ':memory:'];
                        $command[] = '--fail-on-skipped';
                    }

                    if (array_intersect($files, self::SnapshotTests) !== []) {
                        $environment['SCHOOLTOOL_SNAPSHOT_MYSQL_TEST'] = '1';
                        $command[] = '--fail-on-skipped';
                    }
                }

                $process = new Process([...$command, ...$files], $root, $environment, timeout: null);
                $output = fopen($prefix.'.log', 'wb');

                try {
                    $exit = $process->run(function (string $type, string $text) use ($output): void {
                        fwrite($output, $text);
                        fwrite(STDOUT, $text);
                    });
                } finally {
                    fclose($output);
                }

                if ($exit !== 0) {
                    return $exit;
                }

                if ($mode === 'coverage') {
                    $coverage = self::mergeCoverage($coverage, $prefix.'.coverage.php');
                }

                return 0;
            });

            if ($mode === 'coverage') {
                if (! $coverage instanceof CodeCoverage) {
                    throw new RuntimeException('No merged coverage report was produced.');
                }

                (new Clover)->process($coverage, $root.'/storage/logs/clover.xml');
            }

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage().PHP_EOL);

            return 1;
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__).'/vendor/autoload.php';
    exit(CiPhpTests::main($argv));
}
