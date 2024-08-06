## Event Manager
Event Calendar Manager with API endpoints for VATSIM events. Created by [Markus N.](https://github.com/Marko259) (1401513) using `Laravel 10`.

## Prerequisites

### Docker (Recommended)
- A Docker environment to deploy containers. We recommend [Portainer](https://www.portainer.io/).
- MySQL database to store data.
- Preferably a reverse proxy setup if you plan to host more than one website on the same server.

In the instructions where we use `docker exec`, we assume your container is named `events`. If you have named it differently, please replace this.

### Manual (Unsupported)
If you don't want to use Docker, you need:
- An environment that can host PHP websites, such as Apache, Ngnix or similar.
- MySQL database to store data.
- Comply with [Laravel 10 Requirements](https://laravel.com/docs/10.x/deployment)
- Manually build the composer, npm and setting up cron jobs and clearing all caches on updates.

## Setup and install

To setup your Docker instance simply follow these steps:
1. Pull the `ghcr.io/vatsim-scandinavia/events:v1` Docker image
2. Setup your MySQL database (not included in Docker image)
3. Configure the `.env` based on the provided example in `.env.example`
4. To ensure that event banners don't get deleted upon redeployment of the image, you need to create and store an application key in your environment and setup a shared volume. 
   ```sh
   docker exec -it events php artisan key:get
   docker volume create events_storage
   ```
   Copy the key and set it as the `APP_KEY` environment variable in your Docker configuration and bind the volume when creating the container with `events_storage:/app/storage`.
5. Start the container in the background.
6. Setup the database.
   ```sh
   docker exec -it --user www-data events php artisan migrate
   ```
7. Setup a crontab _outside_ the container to run `* * * * * docker exec --user www-data -i events php artisan schedule:run >/dev/null` every minute. This patches into the container and runs the required cronjobs.
8. Bind the 8080 (HTTP) and/or 8443 (HTTPS) port to your reverse proxy or similar.

## Updating

After recreating the docker container, remember to run the migration to make sure your database is up to date.
```sh
docker exec -it --user www-data events php artisan migrate
```

## Contribution and conventions
Contributions are much appreciated to help everyone move this service forward with fixes and functionalities. We recommend you to fork this repository here on GitHub so you can easily create pull requests back to the main project.