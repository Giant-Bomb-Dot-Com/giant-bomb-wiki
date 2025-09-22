# Prep work

- Add your GiantBomb legacy API key (`GB_API_KEY`) to the .env file. It is retrieved from the [GB API page](https://www.giantbomb.com/api) when logged into the site.
- Run `docker ps` and grab the container name for the wiki and db

# generate_xml_categories.php

# generate_xml_forms.php

# generate_xml_properties.php

# generate_xml_templates.php

# generate_xml_pages.php

Generates the categories/forms/properties/templates/pagas xml file for import via importDump.php

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/generate_xml_<type>.php`

# generate_xml_resource.php

Generates the wiki content xml files for import via importDump.php
`<type>` can be accessory, character, company, concept, dlc, franchise, game, genre, location, person, platform, theme, thing

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/generate_xml_resource.php <type>`

# pull_data.php (OPTIONAL - the data is in /gb_api_db_init)

Retrieves the wiki entity details.

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/pull_data.php <resource>`
  - options:
    - apikey: To use an apikey other than the one defined in your `.env` file.
    - id: Targets a specified entity.
    - max: The maximum amount of requests to make. Default is 200. Can not exceed 200.
    - offset: Tells the API the number of entities to skip before pulling.
    - nowait: When set the script will not wait for the 1 hour API request timeout and exit the program.
  - available resources:
    - accessory (dumped)
    - character (dumped - missing relations: requires looping through one at a time)
    - company (dumped - missing relations: requires looping through one at a time)
    - concept (dumped - missing relations: requires looping through one at a time)
    - dlc (dumped)
    - franchise (dumped - missing relations: requires looping through one at a time)
    - game (dumped - missing relations: requires looping through one at a time)
    - game_rating (dumped)
    - genre (dumped)
    - location (dumped)
    - platform (dumped)
    - person (dumped - missing relations: requires looping through one at a time)
    - rating_board (dumped)
    - region (dumped)
    - release (dumped - missing relations: requires looping through one at a time)
    - theme (dumped)
    - thing (alias for object: dumped - missing relations: requires looping through one at a time)

# fill_relations.php (OPTIONAL - too slow)

Retrieves the wiki entity relationships.

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/fill_relations.php <resource>`
- Options:
  - apikey: To use an apikey other than the one defined in your `.env` file.
  - id: Targets a specified entity.
  - max: The maximum amount of requests to make. Default is 200. Can not exceed 200.
  - offset: Tells the API the number of entities to skip before pulling.

# target_crawl.php

Crawls through a targeted entity to fill in its relationships and the relationship's relationships. Degree of depth is 1.

- Check https://www.giantbomb.com/api to make sure the Current API Usage is clean.
- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/target_crawl.php <resource> <id>`
- Options:
  - apikey: To use an apikey other than the one defined in your `.env` file.

# convert_description.php

Pulls down descriptions from the db, converts it to MediaWiki format and updates the mw_formatted_description field

- Run `docker exec <container name> php /var/www/html/maintenance/run.php /var/www/html/maintenance/gb_api_scripts/convert_description.php <resource>`
- Options:
  - id: Targets a specified entity.
  - apikey: To use an apikey other than the one defined in your `.env` file.

# To dump the table data

- Navigate to the `gb_api_db_init` folder
- Run `docker exec <db container name> mariadb-dump -u root -p<password> gb_api_dump <main_table> <relation_table1>... > <num>_<type>.sql`
- Edit the resulting sql script add the line `USE gb_api_dump;`.
- Gzip the file if you are pushing it up.
