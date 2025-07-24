# The Giant Bomb Wiki

We should add stuff here.

## Running the wiki for the first time

- Have docker desktop installed and running
- NOTE: If you are updating a previous install for the first time since getting the .env file, you will need to remove your volumes so everything is built using the new credentials.
- Run `docker compose up -d` in the root of this repo.
- It will take a few minutes to fully install and start the repo. You can watch the wiki container log once the containers have started to watch progress, or just wait a few minutes.
- You should now be able to access the wiki at http://localhost:8080/.
- The default admin username is giantbomb
- The default admin password is gbwikipass
- By default the vector skin is loaded. To load the giantbomb vue skin change see instuctions in the Skins section below.
- You can visit http://localhost:8080/index.php/Special:Version to see the loaded features.

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

### Testing

The end-to-end tests are in `cypress`. With the wiki containers running, the tests can be executed without a browser opening (i.e. headless).
From the root of the repo, excute:
```sh
pnpm cypess run
```

To open the cypress UI, run
```sh
pnpm cypress open
```
