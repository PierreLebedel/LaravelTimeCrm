<?php

namespace App\Support;

class NativeAppEnvBootstrapper
{
    public function bootstrap(?string $envPath = null, ?string $examplePath = null): void
    {
        $envPath ??= $this->nativeUserEnvPath();
        $examplePath ??= base_path('.env.example');

        if ($envPath === null || ! file_exists($examplePath)) {
            return;
        }

        $this->ensureDirectoryExists(dirname($envPath));

        $existingValues = $this->readEnvValues($envPath);
        $exampleLines = file($examplePath, FILE_IGNORE_NEW_LINES) ?: [];
        $exampleKeys = [];
        $renderedLines = [];
        $appKey = $existingValues['APP_KEY'] ?? $this->generateAppKey();

        foreach ($exampleLines as $line) {
            $assignment = $this->parseAssignment($line);

            if ($assignment === null) {
                $renderedLines[] = $line;

                continue;
            }

            $key = $assignment['key'];
            $exampleKeys[] = $key;

            if ($key === 'APP_KEY') {
                $renderedLines[] = 'APP_KEY='.$appKey;

                continue;
            }

            $renderedLines[] = $line;
        }

        foreach ($existingValues as $key => $value) {
            if (in_array($key, $exampleKeys, true)) {
                continue;
            }

            $renderedLines[] = $key.'='.$value;
        }

        file_put_contents($envPath, implode(PHP_EOL, $renderedLines).PHP_EOL);

        $this->applyRuntimeOverrides($this->readEnvValues($envPath));
    }

    protected function nativeUserEnvPath(): ?string
    {
        $userDataPath = env('NATIVEPHP_USER_DATA_PATH');

        if (! is_string($userDataPath) || trim($userDataPath) === '') {
            return null;
        }

        return rtrim($userDataPath, '\\/').DIRECTORY_SEPARATOR.'.env';
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0755, true);
    }

    /**
     * @return array<string, string>
     */
    protected function readEnvValues(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $values = [];

        foreach ($lines as $line) {
            $assignment = $this->parseAssignment($line);

            if ($assignment === null) {
                continue;
            }

            $values[$assignment['key']] = $assignment['value'];
        }

        return $values;
    }

    /**
     * @return array{key: string, value: string}|null
     */
    protected function parseAssignment(string $line): ?array
    {
        $trimmedLine = ltrim($line);

        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            return null;
        }

        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $matches) !== 1) {
            return null;
        }

        return [
            'key' => $matches[1],
            'value' => $matches[2],
        ];
    }

    protected function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * @param  array<string, string>  $envValues
     */
    protected function applyRuntimeOverrides(array $envValues): void
    {
        foreach ($envValues as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        if (isset($envValues['APP_KEY'])) {
            config(['app.key' => $envValues['APP_KEY']]);
        }
    }
}
