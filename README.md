# Flysystem adapter for the Firebase Storage API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/galihlprakoso/flysystem-firebase-storage.svg?style=flat-square)](https://packagist.org/packages/galihlprakoso/flysystem-firebase-storage)
[![Total Downloads](https://img.shields.io/packagist/dt/galihlprakoso/flysystem-firebase-storage.svg?style=flat-square)](https://packagist.org/packages/galihlprakoso/flysystem-firebase-storage)


This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for Firebase Storage.

## Installation

You can install the package via composer:

``` bash
composer require galihlprakoso/flysystem-firebase-storage
```
## Usage

### PHP Usage

```php
use galihlprakoso\Adapters\FirebaseStorageAdapter;
use Kreait\Firebase\Factory;

$factory = (new Factory())->withServiceAccount('<path to your service account json file>');
$storageClient = $factory->createStorage();

$adapter = new FirebaseStorageAdapter($storageClient, '<bucket name>');
```
### Laravel Usage
Define the config in your `filesystems.php` file.
```php
[
  'disks' => [
    //... another configuration    
    'firebase-storage' => [
        'driver' => 'firebase-storage',
        'service_account_json_name' => env('FIREBASE_STORAGE_SERVICE_ACCOUNT_JSON_NAME'),
        'bucket_name' => env('FIREBASE_STORAGE_BUCKET_NAME'),
    ],
  ]
]
```
Add this Storage extension in your Laravel's `AppServiceProvider.php` file, inside the `boot()` method:
```php
Storage::extend('firebase-storage', function (Application $app, array $config) {
    $factory = (new Factory())->withServiceAccount(base_path('/' . $config['service_account_json_name']));
    $storageClient = $factory->createStorage();

    $adapter = new FirebaseStorageAdapter($storageClient, $config['bucket_name']);

    return new FilesystemAdapter(
        new Filesystem($adapter, $config),
        $adapter,
        $config
    );
});
```
