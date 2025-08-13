--
-- add mw_page_name to the tables with a name field
--
USE gb_api_dump;

ALTER TABLE `wiki_game_dlc` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_franchise` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_game_release` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                   
ALTER TABLE `wiki_game_theme` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                     
ALTER TABLE `wiki_game_dlc_type` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                  
ALTER TABLE `wiki_accessory` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_game_release_sound_system` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;      
ALTER TABLE `wiki_points_rule` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                    
ALTER TABLE `wiki_platform` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_game_release_company_code_type` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL; 
ALTER TABLE `wiki_location` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                       
ALTER TABLE `wiki_character` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_thing` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                          
ALTER TABLE `wiki_game_release_feature` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;           
ALTER TABLE `wiki_game_release_rating_board` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;      
ALTER TABLE `wiki_game` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                           
ALTER TABLE `wiki_game_release_rating` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;            
ALTER TABLE `wiki_game_genre` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                     
ALTER TABLE `wiki_person` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                         
ALTER TABLE `wiki_game_role` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                      
ALTER TABLE `wiki_game_release_region` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;            
ALTER TABLE `wiki_company` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;                        
ALTER TABLE `wiki_game_release_product_code_type` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL; 
ALTER TABLE `wiki_concept` ADD COLUMN `mw_page_name` varchar(255)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;    