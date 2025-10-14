# Prep work

- Add your GiantBomb legacy API key (`GB_API_KEY`) to the .env file. It is retrieved from the [GB API page](https://www.giantbomb.com/api) when logged into the site.
- Run `docker ps` and grab the container name for the wiki and db

# convert_descriptions.php

Pulls down descriptions from the db, converts it to MediaWiki format and updates the mw_formatted_description field

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/convert_descriptions.php`
- Options:
  - id: Targets a specified entity.
  - apikey: To use an apikey other than the one defined in your `.env` file.

# convert_names.php

Pulls down names from the db, converts it to MediaWiki format and updates the mw_page_name field

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/convert_names.php`
- Options:
  - id: Targets a specified entity.
  - apikey: To use an apikey other than the one defined in your `.env` file.

# generate_xml_categories.php

# generate_xml_forms.php

# generate_xml_properties.php

# generate_xml_templates.php

# generate_xml_pages.php

Generates the categories/forms/properties/templates/pagas xml file for import via importDump.php

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/generate_xml_<type>.php`

# generate_xml_resource.php

Generates the wiki content xml files for import via importDump. Can also include a resource for targetted xml file generation.
`<resource>` can be accessory, character, company, concept, dlc, franchise, game, genre, location, person, platform, release, theme, thing

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/generate_xml_resource.php --resource=<resource>`

# import.sh

Run this after going through the above commands so MediaWiki can rebuild the index, go through its recent changes and compile stats. Follow the steps to execute the queued jobs, refresh the links to fill in the category pages and execute the queued jobs again.

- Run `./import.sh`

# runjobs.sh

Activates the job queue in multiple processes. To stop the processes, run `ps` to list out the active processes than `kill #` the php ones.

- Run `./runjobs.sh`
