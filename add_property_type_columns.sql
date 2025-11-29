-- Add property_type columns to property_sheets table
-- This is SAFE - it only adds columns, doesn't delete any data
USE `LAA0963548-occ`;

-- Add property_type column
ALTER TABLE property_sheets
ADD COLUMN property_type ENUM('hostel', 'guesthouse') DEFAULT 'guesthouse'
    COMMENT 'hostel=has multiple rooms, guesthouse=single unit'
    AFTER sheet_description;

-- Add room_list column
ALTER TABLE property_sheets
ADD COLUMN room_list TEXT
    COMMENT 'Comma-separated list of room names for hostels'
    AFTER property_type;

-- Add total_rooms column
ALTER TABLE property_sheets
ADD COLUMN total_rooms INT DEFAULT 1
    COMMENT 'Total number of rooms in this property'
    AFTER room_list;

-- Add index for faster queries
ALTER TABLE property_sheets
ADD INDEX idx_property_type (property_type);

-- Verify the columns were added
SELECT 'Columns added successfully!' as Status;
DESCRIBE property_sheets;
