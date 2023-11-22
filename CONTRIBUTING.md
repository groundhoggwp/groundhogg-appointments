# Contributing

## Tools

[WP-CLI](https://wp-cli.org/) is used to generate the i18n `.pot` file and its [dist-archive-command](https://github.com/wp-cli/dist-archive-command/) creates the plugin archive. 
```
brew install wp-cli
wp package install wp-cli/dist-archive-command:dev-main
```

[Strauss](https://github.com/BrianHenryIE/strauss) is used to prefix Composer packages' namespaces. It will also be automatically downloaded by the Composer script.
```
curl -o strauss.phar -L -C - https://github.com/BrianHenryIE/strauss/releases/latest/download/strauss.phar;
chmod +x strauss.phar;
```

## Develop

Install the Composer dependencies, display each one's description, and list the available scripts.
```bash
composer install

composer show --installed

composer run-script --list
```

## Release

Run Composer with `--no-dev` to exclude development dependencies, then run the defined script to create the plugin archive.
```
composer install --no-dev
composer create-plugin-archive
```