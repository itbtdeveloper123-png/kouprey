-- Add language support to features table
-- Note: Assuming language column might already exist, skip if it does
ALTER TABLE features ADD COLUMN IF NOT EXISTS language VARCHAR(5) DEFAULT 'en' AFTER description;

-- Add base_feature_id column for linking translations
ALTER TABLE features ADD COLUMN IF NOT EXISTS base_feature_id INT DEFAULT NULL AFTER language;

-- Update existing features to set base_feature_id to their own id
UPDATE features SET base_feature_id = id WHERE base_feature_id IS NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_features_base_lang ON features (base_feature_id, language);