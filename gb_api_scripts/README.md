# pull_data.php

- Add your api key to the .env file. The api key can be found at https://www.giantbomb.com/api when you're logged into the site
- Run `docker ps` and grab the container name for the wiki - most likely its `giant-bomb-wiki-wiki-1`
- Run `docker exec giant-bomb-wiki-wiki-1 php /var/www/html/maintenance/run.php gb_api_scripts/pull_data.php <singular endpoint>`
	- available endpoints: 
		- accessory (dumped)
		- character
		- dlc
		- genre (dumped)
		- location