# The Giant Bomb Wiki

A MediaWiki-based wiki for Giant Bomb game data, powered by Semantic MediaWiki.

## Quick Start (Recommended)

**Get started in ~30 seconds:**

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Copy `.env.example` to `.env` and configure it
3. Run:
   ```bash
   ./setup.sh
   ```
4. Access at: **http://localhost:8080**

This uses a pre-built database snapshot with 5 wiki pages ready for development!

## What's Included

- **5 wiki pages** ready for development (working on skins, templates, etc.)
- **MediaWiki 1.43.5** with Semantic MediaWiki
- **Custom GiantBomb skin** with game landing page
- **Optional: 86,147 games** from Giant Bomb API (for generating new wiki pages)

## Setup Methods

### Method 1: Quick Setup (Recommended - Uses Snapshot)

```bash
./setup.sh
```

**Requirements:**
- `gb_wiki.sql.gz.data` in `docker/db-snapshot/` (included in repo)
- `.env` file configured

**Time:** ~30 seconds first run, ~5 seconds subsequent runs

**What you get:**
- 5 wiki pages for testing skins/templates
- Full MediaWiki + Semantic MediaWiki setup
- Perfect for frontend/UI development

### Method 2: Include API Database (Optional)

If you want to generate **new wiki pages** from the 86,147 games in the API:

1. Uncomment this line in `docker/db-snapshot/.dockerignore`:
   ```
   # !gb_api_dump.sql.gz.data
   ```

2. Make sure you have `gb_api_dump.sql.gz.data` (89MB) in `docker/db-snapshot/`

3. Run `./setup.sh` as normal

**Time:** First run takes 1-2 minutes to load the API database

### Method 3: Manual Setup (Legacy)

<details>
<summary>Click to expand manual setup instructions</summary>

1. Prepare the environment by first installing [Docker Desktop](https://www.docker.com/products/docker-desktop/) and running it.
2. Configure the wiki by copying `.env.example` to `.env` and filling out the missing values accordingly.
   - (optional) If you need services that will use the Giant Bomb legacy API, see the readme for [gb_api_scripts](gb_api_scripts/README.md).
3. Start the wiki services from the terminal, with
   - `docker compose up -d`
   - This will download, install, and start the database and the mediawiki services.
   - (optional) Run `docker compose build --no-cache` if you can see the version of mediawiki is not the expected one (currently Mediawiki 1.43.5).
4. Install the wiki in two steps
   1. Find the wiki container with `docker ps`. By default it should be `giant-bomb-wiki-wiki-1`
   2. Install with
      - `docker exec <wiki-container-name> /bin/bash /installwiki.sh`
      - This is a one time action to configure Mediawiki and install the necessary Mediawiki extensions/skins as seen in `/config/LocalSettings.php`.
      - It also performs some web-centric configurations.
5. Verify the Special Version page http://localhost:8080/index.php/Special:Version loads in a browser and see the installed extensions/skins and their versions.
6. (optional) To tear down everything and remove the Volumes, `docker compose down -v`
7. (optional) Execute all end-to-end tests with `pnpm test:e2e`. See the [Tests](#Tests) section for the set-up.

</details>

## Common Commands

```bash
# Start wiki
./setup.sh

# Stop wiki
docker compose -f docker-compose.snapshot.yml down

# View logs
docker compose -f docker-compose.snapshot.yml logs -f

# Reset everything (delete all data)
docker compose -f docker-compose.snapshot.yml down -v
./setup.sh
```

## Skins

- You can disable/enable skins by editing the `LocalSettings.php` file. See https://www.mediawiki.org/wiki/Manual:LocalSettings.php
- To start working on the new Giant Bomb Skin add the following to your `LocalSettings.php` file:
  - `wfLoadSkin( 'GiantBomb' );`
  - `$wgDefaultSkin = "giantbomb";`
- Make sure your editor of choice is setup with [Prettier](https://prettier.io/docs/install) as a default formatter. We're relying on Prettier to enforce our [`.editorconfig`](https://editorconfig.org/) rules.

### Building Vue Components

#### Javascript Resource Module

- Vue components can be defined as a `.js` file using the Vue [Single File Component](https://vuejs.org/api/sfc-spec.html) syntax.
- Create new Vue Component in `/skins/GiantBomb/resources/components` as a `.js` file.
  - See `/skins/GiantBomb/resources/components/VueExampleComponent.js` as an example.
- Add component to `skin.json` as a separate Resource Module.
  - See `skin.giantbomb.vueexamplecomponent` for example.

#### Vue Single File Component

- Vue components can be defined as a `.vue` file using the Vue [Single File Component](https://vuejs.org/api/sfc-spec.html) syntax.
  - Supports styling component via the `<style>` tag.
- Create new Vue Component in `/skins/GiantBomb/resources/components` as a `.vue` file.
  - See `/skins/GiantBomb/resources/components/VueSingleFileComponentExample.vue` as an example.
- Add component to `skin.json` within the `skins.giantbomb` Resource Module as a `packageFile`.
  - See `skin.giantbomb` for example.

### Binding Vue Components

- To allow Vue Component to be bound to the DOM within a `.php` template, components must then be loaded via the components object in `/skins/GiantBomb/resources/components/index.js`.
- In any `.php` template use the attribute `data-vue-component=` on any DOM element.
  - See `/skins/GiantBomb/includes/GiantBombTemplate.php` as an example.
- Vue Component will be added to DOM as a child of that element.
- Props are fully functional by prefixing with `data-my-prop=` pattern, where `my-prop` is the name of your prop in kebab case, see `VueExampleComponent.js` for example.

#### Binding Vue Components within other Vue components.

- As long as the component has been included as per [Building Vue Components](#building-vue-components), it can be added to another Vue component via the `require` syntax.
  - See `/skins/GiantBomb/resources/components/VueSingleFileComponentExample.vue` as an example.

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

## [Tests](#Tests)

### [Package Manager](#Package-Manager)

The package manager chosen is [pnpm](https://pnpm.io) for its speed.

With `pnpm` ready, install the configured packages with

```sh
pnpm install
```

This will install packages defined in the [pnpm workspace config file](pnpm-workspace.yaml).

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

## TODO's

### Core

1. Update to use firebase for auth. ( https://github.com/Giant-Bomb-Dot-Com/giant-bomb-wiki/issues/15 )
2. Add maintenance scripting / cron jobs to handle things like cache updates, popup text scraping, image refresh, link / semantic link refreshing.

### Research

1. Need to see if the templates and mustache in the skins can be used instead of the php templates for mediawiki, while keeping all of the interconnecting page functionality mediawiki has. While it looks like we can for sure replace the templates, we need everything to still function in the wiki core / syntax, so we don't have a bespoke system we have to maintain.
2. Need to map out the schema from current GB to categories / pages / templates in mediawiki.
3. See what is needed for full i18n support for multi-language

### Templates

1. The initial php template for infobox needs to be updated to match the GB games template in both data and actions.
2. Templates will need to be made for every major page / category. i.e. Games, Publishers, People, etc.
3. The templates will need to make sure they are tied into the semantic mediawiki tags so they can be called on later by other pages. The starcitizen.tools vehicle template works as a good analog for what the GB games template should be. Note: The top level vehicle template uses / requires 69 other templates / modules to function. The GB game page will be similar. https://starcitizen.tools/index.php?title=100i&action=edit

### Skins

1. The skin needs to be updated to style mediawiki while using it's data syntax. The sctools has their skin public here: https://github.com/StarCitizenTools/mediawiki-skins-Citizen
2. We can use translatewiki.net and i18n to allow / help with multi-language support.
