# The Giant Bomb Wiki

We should add stuff here.

# Running the project

1. Populate the .env file

[Follow the instructions in DEVELOPERS.md](DEVELOPERS.md#3-prepare-env-file) to setup your .env file.

2. Start the container
```
docker compose up -d
```

3. Install dependencies
```
docker compose exec mediawiki composer update
```

4. Configure your local settings by running the install script
```
docker compose exec mediawiki /bin/bash /docker/install.sh
```

You can now go to http://localhost:8080 and see the wiki!

## TODO's

- We should probably remove a lot of the stuff in this repo that could be generated/downloaded from MediaWiki via cli. Probably.
- Get started on proof of concept approaches to theming the wiki
- Get started building out some of the complex relationships between categories like we have on the current wiki
