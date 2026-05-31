<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    protected int $timeout = 900;

    public function dumpToTempFile(): string
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException(
                'Backup download is only supported for MySQL/MariaDB connections.'
            );
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mrbackup_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create a temporary file for the backup.');
        }

        $gz = gzopen($tmp, 'wb9');
        if ($gz === false) {
            @unlink($tmp);
            throw new RuntimeException('Could not open the temporary backup file for writing.');
        }

        $command = [
            'mysqldump',
            '--no-tablespaces',
            '--single-transaction',
            '--default-character-set=utf8mb4',
            '--host=' . ($db['host'] ?? '127.0.0.1'),
            '--port=' . ($db['port'] ?? 3306),
            '--user=' . $db['username'],
            $db['database'],
        ];

        $process = new Process(
            $command,
            base_path(),
            ['MYSQL_PWD' => (string) ($db['password'] ?? '')],
        );
        $process->setTimeout($this->timeout);

        try {
            $process->run(function (string $type, string $buffer) use ($gz): void {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
        } finally {
            gzclose($gz);
        }

        if (! $process->isSuccessful()) {
            @unlink($tmp);
            throw new ProcessFailedException($process);
        }

        return $tmp;
    }

    public function suggestedFilename(): string
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        return $database . '-' . now()->format('Y-m-d-His') . '.sql.gz';
    }
}
