-- ============================================
-- ATTIVARE/DISATTIVARE DEBUG BANNER MANAGER
-- ============================================

-- 🟢 ATTIVARE il debug banner (mostra errori del plugin)
INSERT INTO wp_options (option_name, option_value, autoload) 
VALUES ('fp_resv_debug', 'a:1:{s:19:"manager_debug_panel";b:1;}', 'yes')
ON DUPLICATE KEY UPDATE option_value = 'a:1:{s:19:"manager_debug_panel";b:1;}';

-- 🔴 DISATTIVARE il debug banner
UPDATE wp_options 
SET option_value = 'a:1:{s:19:"manager_debug_panel";b:0;}' 
WHERE option_name = 'fp_resv_debug';

-- 🧹 PULIRE tutti gli errori registrati
DELETE FROM wp_options WHERE option_name = 'fp_resv_error_log';

-- 📋 VEDERE gli errori registrati
SELECT option_value FROM wp_options WHERE option_name = 'fp_resv_error_log';

