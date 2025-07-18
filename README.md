# The Giant Bomb Wiki

We should add stuff here.

## Running the wiki for the first time

- Have docker desktop installed and running.
- Copy `.env.example` to `.env`.
- Go to the GB Discord in the `wiki-street-team` channel to request a copy of the .env credentials.
- The GB_API_KEY can be retrieved from https://www.giantbomb.com/api when logged into the site.
- NOTE: If you are updating a previous install for the first time since getting the .env file, you will need to remove your volumes (`docker compose down -v`) so everything is built using the new credentials.
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

- ~~We should probably remove a lot of the stuff in this repo that could be generated/downloaded from MediaWiki via cli. Probably.~~
- Get started on proof of concept approaches to theming the wiki
- Get started building out some of the complex relationships between categories like we have on the current wiki
