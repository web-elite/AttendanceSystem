-- 002_user_themes.sql — Per-user daisyUI theme preference
-- theme stores the daisyUI theme name ('' = default light)

ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT '';
