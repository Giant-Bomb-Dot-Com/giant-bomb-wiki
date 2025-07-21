# Prep work

- Add your api key to the .env file. The api key can be found at https://www.giantbomb.com/api when you're logged into the site
- Run `docker ps` and grab the container name for the wiki 

# pull_data.php
Retrieves the wiki entity details.

- Run `docker exec <container name> php /var/www/html/maintenance/run.php gb_api_scripts/pull_data.php <singular endpoint>`
  - options:
    - apikey: To use an apikey other than the one defined in your `.env` file.
    - id: Targets a specified entity.
    - max: The maximum amount of requests to make. Default is 200. Can not exceed 200.
    - offset: Tells the API the number of entities to skip before pulling.
    - nowait: When set the script will not wait for the 1 hour API request timeout and exit the program.
  - available endpoints:
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

# fill_relations.php
Retrieves the wiki entity relationships.

  - Run `docker exec <container name> php /var/www/html/maintenance/run.php gb_api_scripts/fill_relations.php <singular endpoint>`
  - Options:
    - apikey: To use an apikey other than the one defined in your `.env` file.
    - id: Targets a specified entity.
    - max: The maximum amount of requests to make. Default is 200. Can not exceed 200.
    - offset: Tells the API the number of entities to skip before pulling.

# wiki_entity_crawl.php
Crawls through a targeted entity to fill in its relationships and the relationship's relationships. Degree of depth is 1.

  - Run `docker exec <container name> php /var/www/html/maintenance/run.php gb_api_scripts/fill_relations.php <singular endpoint> <id>`
  - Options:
    - apikey: To use an apikey other than the one defined in your `.env` file.

# To dump the table data

  - Navigate to the `gb_api_db_init` folder
  - Run `docker exec <container name> mariadb-dump -u root -p<password> gb_api_dump <main_table> <relation_table1>... --no-create-info > <num>_<type>.sql`
    - The --no-create-info removes the query to drop/create the table.
  - Edit the resulting sql script add the line `USE gb_api_dump;`.
  - Gzip the file if you are pushing it up.
