# pull_data.php

- Run `docker ps` and grab the container name for the wiki - most likely its `giant-bomb-wiki-wiki-1`
- Run `docker exec giant-bomb-wiki-wiki-1 php /var/www/html/maintenance/run.php gb_api_scripts/pull_data.php <apikey> <singular endpoint>`
	- the api key can be found at https://www.giantbomb.com/api when you're logged into the site
	- an example of a singular endpoint would be `accessory` which works for both accessory and accessories endpoints