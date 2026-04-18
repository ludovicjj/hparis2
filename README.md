Liste extension available
```
frankenphp php-cli -r "echo 'ini: ' . (php_ini_loaded_file() ?: 'NONE') . PHP_EOL; echo 'extensions: ' . implode(', ', get_loaded_extensions()) . PHP_EOL;"
```

Run project locally with frankenphp
```
frankenphp php-server --listen :8000 --root public/
```

Run worker with frankenphp
```
frankenphp php-cli bin/console messenger:consume async -vv
```

Apply CORS config to Scaleway S3 bucket (after editing cors.json)
```
aws s3api put-bucket-cors --bucket h-paris --cors-configuration file://cors.json --endpoint-url https://s3.fr-par.scw.cloud --profile scaleway
```

Verify current CORS config on Scaleway S3 bucket
```
aws s3api get-bucket-cors --bucket h-paris --endpoint-url https://s3.fr-par.scw.cloud --profile scaleway
```