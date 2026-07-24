# The Giant Bomb Wiki

A MediaWiki-based wiki for Giant Bomb game data, powered by Semantic MediaWiki. The current LTS version of MediaWiki is 1.43.6.

## Quick Start (Recommended)

**Get started:**

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Copy `.env.example` to `.env` and configure it
3. Run:
   ```bash
   ./setup.sh
   ```
4. Access at: **http://localhost:8080**

This uses a data cut of up to 500 of each category and will build everything needed. It will take around 10-15 minutes to build.

**If you already have a working wiki and just want the data, run the following from your project root:**

```docker cp data/initial_import_data_xmls.zip $WIKI_CONTAINER:/data/initial_import_data_xmls.zip
docker cp installinitialwikidata.sh $WIKI_CONTAINER:/installinitialwikidata.sh
docker exec $WIKI_CONTAINER chmod 755 /installinitialwikidata.sh
docker exec $WIKI_CONTAINER /bin/bash /installinitialwikidata.sh
docker exec $WIKI_CONTAINER rm -rf /data/initial_import_data_xmls/
```

## What's Included

This will build a dev enviroment ready to work on from a working sample of production data.

1. Anything with File:xxx is due to the data cut not including images.
2. Some items in characters, companies, etc may have a number in the name. This is from the live data to prevent duplicates and is expected.
3. The images tabs won't have any data in them either.

## Common Commands

```bash
# Start wiki
./setup.sh

# Stop wiki
docker compose -f docker-compose.yml down

# View logs
docker compose -f docker-compose.yml logs -f

# Reset everything (delete all data)
docker compose -f docker-compose.yml down -v
./setup.sh
```

## Skins

- Everything for the skin is in the skins/GiantBomb path

## SemanticMediaWiki

- Add more notes
- Can add SMW attributes test by going to: http://localhost:8080/index.php?title=The_Legend_of_Zelda:\_Twilight_Princess and creating page with the following:

  ```
  {{#set:
  Has Name=Pitfall
  |Has Platform=Xbox
  |Has Platform=Playstation
  |Has Platform=iPhone
  |Has Release=Aug 09, 2012
  }}
  ```

  then go to: http://localhost:8080/index.php/Games and create with the following:

  ```
  {{#ask:
  [[Has Platform::Xbox]]
  |mainlabel=Game
  |?Has Release=Release Date
  }}
  ```

## Templates

- You will need to run the templates from import_templates if you haven't already:
  | - php maintenance/run.php import_templates/import_all_templates.php
- Note: This runs automatically as part of the ./setup.sh

## [Tests](#Tests)

### [Package Manager](#Package-Manager)

The package manager chosen is [pnpm](https://pnpm.io) for its speed.

With `pnpm` ready, install the configured packages with

```sh
pnpm install
```

This will install packages defined in the [pnpm workspace config file](pnpm-workspace.yaml).

### [PHP Testing](#php-testing)

#### Testing Mediawiki Tests

Install the wiki first

```
docker compose exec wiki composer install
docker compose exec wiki composer phpunit:unit
```

#### Testing GB Tests

```
composer phpunit -- gb-tests
```

To execute only a single file, pass the path the test after the double hyphen.

### E2E Testing

The end-to-end tests use the [Cypress](https://www.cypress.io) framework.

After setting up the [package manager](#Package-Manager), execute the `cypress` tests in headless mode with

```sh
pnpm cypess run
```

The tests should run within the terminal and end with the test results.

To open the cypress UI, run

```sh
pnpm cypress open
```

### Continuous Integration

A Github Action workflow will be added to execute a subset of the `cypress` tests as part of the pull request pipeline.

### Git Pre-commit Hook

A git commit will use [Husky](https://typicode.github.io/husky/) to execute hooks listed in [.husky](.husky). To skip them (if necessary), add the option `--no-verify` or `-n`.

### Category Page(s)

Go to /Games for the overall Games category page and then click on a letter / number to see the data being pulled from Semantic and how it gathers data.

### GBMETATAGS Extension

On a page or template the following can be used per tag to add meta tags to the header when the page is rendered:

For example on Games/Castlevania_Circle_of_the_Moon

```
{{#tag:gbmeta
|
|type=property
|name=og:description
|content=Castlevania: Circle of the Moon
}}
```

The type can be property or name
the name is what goes into the property or name value in the tag
and content will be the content section

The resulting tag is:

```
<meta property="og:description" content="Castlevania: Circle of the Moon">
```

Since it's in wikitext, wiki syntax / smw can be used, so the proper way to do the above would be:

```
{{#tag:gbmeta
|
|type=property
|name=og:description
|content={{PAGENAME}}
}}
```

renders as

```
<meta property="og:description" content="Games/Castlevania Circle of the Moon">
```

We wouldn't add these to game pages, but to the underlying templates.

### Images (GCS)

Update the following in your .env file before you run:
GCS_HMAC_ACCESS_KEY=
GCS_HMAC_SECRET=

A new local-GCS for images will start on port 9000 and you can get to the page at localhost:9001.
Once here create a bucket called: gb-wiki-mw (name in your .env)
The wiki will want to run things on https so you will have to visit the local-gcs at https://localhost:9001 and tell the browser it's ok to use.

If pages aren't saving, you may need to set the local-gcs files public by running the following:

```
docker exec giant-bomb-wiki-local-gcs-1 mc alias set local http://localhost:9000 dev test@test.com
docker exec giant-bomb-wiki-local-gcs-1 mc anonymous set download local/gb-wiki-mw
docker exec giant-bomb-wiki-local-gcs-1 mc anonymous set public local/gb-wiki-mw
```
