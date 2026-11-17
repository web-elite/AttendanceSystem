-- 003_user_profiles.sql — Optional employee profile fields (HR data)
-- All columns are nullable: fields are optional by design.

-- Contact / identity
ALTER TABLE users ADD COLUMN email         TEXT;   -- optional, unique-checked in app layer
ALTER TABLE users ADD COLUMN national_code TEXT;   -- کد ملی (10 digits, validated in app layer)
ALTER TABLE users ADD COLUMN father_name   TEXT;   -- نام پدر

-- Birth / identity document info
ALTER TABLE users ADD COLUMN birth_date    TEXT;   -- Gregorian YYYY-MM-DD converted from Jalali at input
ALTER TABLE users ADD COLUMN birth_city    TEXT;   -- شهر محل تولد
ALTER TABLE users ADD COLUMN id_card_no    TEXT;   -- شماره شناسنامه

-- Job info
ALTER TABLE users ADD COLUMN specialty     TEXT;   -- تخصص
ALTER TABLE users ADD COLUMN job_location  TEXT;   -- موقعیت شغلی (محل خدمت)
ALTER TABLE users ADD COLUMN position      TEXT;   -- پوزیشن / عنوان شغلی
ALTER TABLE users ADD COLUMN salary        INTEGER;-- مقدار حقوق دریافتی (تومان)

-- Free-form extra details (educations, notes, …)
ALTER TABLE users ADD COLUMN notes         TEXT;

CREATE INDEX IF NOT EXISTS idx_users_national_code ON users (national_code);
