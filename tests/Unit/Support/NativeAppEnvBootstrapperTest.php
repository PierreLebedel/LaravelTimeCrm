<?php

use App\Support\NativeAppEnvBootstrapper;
use Illuminate\Support\Facades\File;

test('it creates a native user env file from the example and generates an app key', function () {
    $directory = base_path('.tmp/tests/'.str_replace('.', '-', uniqid('native-env-', true)));
    $examplePath = $directory.DIRECTORY_SEPARATOR.'.env.example';
    $envPath = $directory.DIRECTORY_SEPARATOR.'user'.DIRECTORY_SEPARATOR.'.env';

    File::ensureDirectoryExists($directory);

    file_put_contents($examplePath, implode(PHP_EOL, [
        'APP_NAME=TimeCRM',
        'APP_KEY=',
        'QUEUE_CONNECTION=database',
        'NATIVEPHP_APP_VERSION=1.2.3',
        '',
    ]));

    app(NativeAppEnvBootstrapper::class)->bootstrap($envPath, $examplePath);

    expect(file_exists($envPath))->toBeTrue();

    $contents = file_get_contents($envPath);

    expect($contents)
        ->toContain('APP_NAME=TimeCRM')
        ->toContain('QUEUE_CONNECTION=database')
        ->toContain('NATIVEPHP_APP_VERSION=1.2.3')
        ->toMatch('/APP_KEY=base64:[A-Za-z0-9+\/=]+/');
}
);

test('it updates env values from the example while preserving the existing app key and extra keys', function () {
    $directory = base_path('.tmp/tests/'.str_replace('.', '-', uniqid('native-env-', true)));
    $examplePath = $directory.DIRECTORY_SEPARATOR.'.env.example';
    $envPath = $directory.DIRECTORY_SEPARATOR.'user'.DIRECTORY_SEPARATOR.'.env';
    $existingKey = 'base64:existing-app-key';

    File::ensureDirectoryExists(dirname($envPath));

    file_put_contents($examplePath, implode(PHP_EOL, [
        'APP_NAME=TimeCRM',
        'APP_KEY=',
        'QUEUE_CONNECTION=database',
        'NATIVEPHP_APP_VERSION=2.0.0',
        '',
    ]));

    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_NAME=OldName',
        'APP_KEY='.$existingKey,
        'QUEUE_CONNECTION=sync',
        'CUSTOM_KEEP=yes',
        '',
    ]));

    app(NativeAppEnvBootstrapper::class)->bootstrap($envPath, $examplePath);

    $contents = file_get_contents($envPath);

    expect($contents)
        ->toContain('APP_NAME=TimeCRM')
        ->toContain('APP_KEY='.$existingKey)
        ->toContain('QUEUE_CONNECTION=database')
        ->toContain('NATIVEPHP_APP_VERSION=2.0.0')
        ->toContain('CUSTOM_KEEP=yes');

    expect(config('app.key'))->toBe($existingKey);
});
