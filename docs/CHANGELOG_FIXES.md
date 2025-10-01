# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-001 | src/Domain/Brevo/AutomationService.php | 441 | High | Normalize notification recipient settings into sanitized email arrays before sending survey alerts. | fix(functional): normalize survey alert recipients (ISSUE-001) |
| ISSUE-002 | src/Domain/Tracking/Manager.php | 102 | High | Gate attribution cookie storage behind ads consent before persisting UTM data. | fix(security): require ads consent before storing attribution (ISSUE-002) |
| ISSUE-003 | src/Core/Plugin.php | 60 | Medium | Align Plugin::VERSION constant with the 0.1.1 release header. | fix(release): sync Plugin::VERSION with header (ISSUE-003) |
| ISSUE-004 | tools/bump-version.php | 100 | Medium | Extend bump script to update Plugin::VERSION alongside the plugin header. | fix(release): update bump script to rewrite version constant (ISSUE-004) |

## Fix Phase Summary

- Completed fix phase on 2025-10-01, resolving 4 issues (High: 2, Medium: 2).
