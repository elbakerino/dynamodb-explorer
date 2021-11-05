# DynamoDB Explorer

> API Endpoints to work with [DynamoDB Visualizer](https://dynamodb-visualizer.bemit.codes/)

🚧 Stability: **EXPERIMENTAL**

## Usage

### Public Host

> Use this host with caution, data may be lost any time.
>
> `https://dynamodb-explorer.bemit.codes`

### Docker / Own Hoster

There are 3 prebuild Docker images:

- [bemiteu/dynamodb-explorer](https://hub.docker.com/r/bemiteu/dynamodb-explorer), ready to use image to try out and run locally
- [bemiteu/dynamodb-explorer-api](https://hub.docker.com/r/bemiteu/dynamodb-explorer-api), only the php-fpm container to use with external nginx
- [bemiteu/dynamodb-explorer-worker](https://hub.docker.com/r/bemiteu/dynamodb-explorer-worker), a preconfigured queue worker container and for CLI stuff

```bash
docker run -it --rm -p 3333:80 \
  -e CORS_ORIGINS=http://localhost:3000 \
  -e APP_SALT=somesalt \
  -e AUTH_ISSUER=example.com \
  -e AUTH_SECRET=min12@igns!LONG \
  -e DYNAMO_DB_KEY=aws_api_key \
  -e DYNAMO_DB_SECRET=aws_api_secret \
  bemiteu/dynamodb-explorer
```

- env vars
    - `EXPLORER_NAME`
    - `APP_SALT`
    - `AUTH_ISSUER`
    - `AUTH_SECRET`
    - `AUTH_EXPIRE`, defaults to `3600`, how long an auth token is valid, in seconds
    - `DYNAMO_DB_KEY`
    - `DYNAMO_DB_SECRET`
    - `DYNAMO_DB_ENDPOINT=http://127.0.0.1:8001`, for usage with e.g. own public scylla endpoint, not needed for AWS
    - `CORS_ORIGINS`, comma separated list of CORS allowed domains, already configured for [dynamodb-visualizer.bemit.codes](https://dynamodb-visualizer.bemit.codes)

Create the needed table with a temporary worker:

```bash
docker run -ti --rm \
  -e CORS_ORIGINS=http://localhost:3000 \
  -e APP_SALT=somesalt \
  -e AUTH_ISSUER=example.com \
  -e AUTH_SECRET=min12@igns!LONG \
  -e DYNAMO_DB_KEY=aws_api_key \
  -e DYNAMO_DB_SECRET=aws_api_secret \
   bemiteu/dynamodb-explorer-worker sh -c "php cli dynamo:create dyn_explorer"
   
# or for custom name:
php cli dynamo:create dyn_explorer your_table_name
```

## Table

Use these exports to explore the used table in the [visualizer](https://dynamodb-visualizer.bemit.codes/)

- [`dynamo-dump--dyn_explorer--schema.json`](./dynamo-dump--dyn_explorer--schema.json), add as `Table Schema`
- [`dynamo-dump--dyn_explorer--data.json`](./dynamo-dump--dyn_explorer--data.json), add as `Example Data`

## Repository

This repository contains all backend files, using [orbiter/satellite](https://github.com/bemit/satellite-app).

### Initial Setup

Install after clone:

```bash
cd dynamodb-explorer

# run extra composer container on windows:
docker run -it --rm -v %cd%:/app composer install
# run extra composer container on unix:
docker run -it --rm -v `pwd`:/app composer install .

# create .env, can be empty
touch .env
```

### Start

```bash
docker-compose up
```

Check PHP CLI:

```bash
docker-compose run --rm worker php cli -h
```

### DynamoDB

```bash
# start persisting container for a bit faster runs:
docker-compose run --rm worker sh

php cli dynamo:create dyn_explorer
# or for custom name:
php cli dynamo:create dyn_explorer your_table_name

# where as `delete` takes any existing table name:
php cli dynamo:delete dyn_explorer
php cli dynamo:delete your_table_name

docker-compose run --rm worker sh -c "cd /var/www && wget -O phpunit https://phar.phpunit.de/phpunit-9.phar && chmod +x phpunit && cd html && /var/www/phpunit --testdox tests"
```

## License

This project is free software distributed under the [**MIT License**](LICENSE).

> Amazon DynamoDB® is a trademark of Amazon.com, Inc. No endorsements by Amazon.com, Inc. are implied by the use of these marks.

### Contributors

By committing your code to the code repository you agree to release the code under the MIT License attached to the repository.

***

Maintained by [Michael Becker](https://mlbr.xyz)
