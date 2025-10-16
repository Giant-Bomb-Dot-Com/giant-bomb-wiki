--
-- add mw_page_name to the tables with a name field
--
USE gb_api_dump;

UPDATE `wiki_game_dlc` SET `mw_formatted_description` = NULL;  
UPDATE `wiki_franchise` SET `mw_formatted_description` = NULL;                 
UPDATE `wiki_game_release` SET `mw_formatted_description` = NULL;              
UPDATE `wiki_game_theme` SET `mw_formatted_description` = NULL;                
UPDATE `wiki_accessory` SET `mw_formatted_description` = NULL;                 
UPDATE `wiki_platform` SET `mw_formatted_description` = NULL;                  
UPDATE `wiki_location` SET `mw_formatted_description` = NULL;                  
UPDATE `wiki_character` SET `mw_formatted_description` = NULL;                 
UPDATE `wiki_thing` SET `mw_formatted_description` = NULL;                     
UPDATE `wiki_game` SET `mw_formatted_description` = NULL;                      
UPDATE `wiki_game_genre` SET `mw_formatted_description` = NULL;                
UPDATE `wiki_person` SET `mw_formatted_description` = NULL;                    
UPDATE `wiki_company` SET `mw_formatted_description` = NULL;                   
UPDATE `wiki_concept` SET `mw_formatted_description` = NULL;                   
-- add mw_page_num column
ALTER TABLE `wiki_game_dlc` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_franchise` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_game_release` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                     
ALTER TABLE `wiki_game_theme` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                     
ALTER TABLE `wiki_accessory` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_platform` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_location` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_character` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_thing` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                          
ALTER TABLE `wiki_game` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                           
ALTER TABLE `wiki_game_genre` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                     
ALTER TABLE `wiki_person` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                         
ALTER TABLE `wiki_company` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                        
ALTER TABLE `wiki_concept` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
