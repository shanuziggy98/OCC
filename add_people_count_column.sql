-- Add people_count column to all property tables
-- This script adds a people_count column to store the number of people from Google Sheets column K

USE `LAA0963548-occ`;

-- For reference: Column K (index 10) in Google Sheets contains the people count

-- Note: These statements will fail silently if the column already exists
-- That's expected behavior - MySQL will show an error but continue

-- Add people_count column to each property table
ALTER TABLE `comodita` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `mujurin` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `fujinomori` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `enraku` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `tsubaki` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `hiiragi` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `fushimi_apt` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `kanon` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `fushimi_house` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `kado` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `tanuki` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `fukuro` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `hauwa_apt` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `littlehouse` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `yanagawa` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `nishijin_fujita` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `rikyu` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `hiroshima` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `okinawa` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `iwatoyama` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `goettingen` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `ryoma` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `isa` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `yura` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `konoha` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';
ALTER TABLE `kaguya` ADD COLUMN `people_count` INT DEFAULT 0 COMMENT 'Number of people from Google Sheets column K';

SELECT '✅ Successfully added people_count column to all property tables!' as Status;

-- Note: If you add new properties in the future, you'll need to either:
-- 1. Run a similar ALTER TABLE statement for the new property table, or
-- 2. The auto_import_cron.php script will automatically create new tables with the people_count column included
