--
-- add the mw_formatted_description field before the description field
--
USE gb_api_dump;

SET FOREIGN_KEY_CHECKS = 0;

-- add mw_formatted_description after the description field
ALTER TABLE `wiki_game_dlc` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;   
ALTER TABLE `wiki_franchise` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                 
ALTER TABLE `wiki_game_release` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;              
ALTER TABLE `wiki_game_theme` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                
ALTER TABLE `wiki_accessory` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                 
ALTER TABLE `wiki_platform` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                  
ALTER TABLE `wiki_location` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                  
ALTER TABLE `wiki_character` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                 
ALTER TABLE `wiki_thing` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                     
ALTER TABLE `wiki_game` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                      
ALTER TABLE `wiki_game_genre` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                
ALTER TABLE `wiki_person` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                    
ALTER TABLE `wiki_company` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;                   
ALTER TABLE `wiki_concept` ADD COLUMN `mw_formatted_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;   
ALTER TABLE `wiki_game_release_rating_board` ADD COLUMN `mw_formatted_description`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;  
ALTER TABLE `wiki_game_release_region` ADD COLUMN `mw_formatted_description`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `description`;  

-- move the description field to be after the mw_formatted_description field for future import purpose
ALTER TABLE `wiki_game_dlc` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;
ALTER TABLE `wiki_franchise` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                 
ALTER TABLE `wiki_game_release` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;              
ALTER TABLE `wiki_game_theme` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                
ALTER TABLE `wiki_accessory` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                 
ALTER TABLE `wiki_platform` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                  
ALTER TABLE `wiki_location` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                  
ALTER TABLE `wiki_character` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                 
ALTER TABLE `wiki_thing` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                     
ALTER TABLE `wiki_game` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                      
ALTER TABLE `wiki_game_genre` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                
ALTER TABLE `wiki_person` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                    
ALTER TABLE `wiki_company` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                   
ALTER TABLE `wiki_concept` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                   
ALTER TABLE `wiki_game_release_rating_board` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                   
ALTER TABLE `wiki_game_release_region` MODIFY COLUMN `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `mw_formatted_description`;                   

SET FOREIGN_KEY_CHECKS = 1;

