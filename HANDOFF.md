# Handoff Document

Date: 2026-03-30
Project: saltshore.net V2 portal

## Summary
A portal sidebar visibility issue was addressed where users were only seeing CalGen, Management, and Logout. The access and menu rendering logic was updated so management-allowlisted users can see the full owner page navigation set.

## Issue Reported
- Sidebar tabs disappeared except for CalGen, Management, and Logout.

## Root Cause
- Sidebar items and route access checks for key pages were gated behind owner-only role checks.
- Management users could authenticate and see Management, but were blocked from owner-gated menu visibility and routes.

## Changes Implemented
1. Updated owner-page route access control to allow either owner role or management-allowlisted users.
2. Introduced a shared visibility variable for owner-page menu items and switched the sidebar checks to use it.
3. Kept Management tab visibility restricted to management-allowlisted users.

## Files Updated
- portal/includes/header.php

## Current Management Allowlist
- CEO1
- Admin

Configured in:
- portal/config.php

## Validation Performed
- Static error check on portal/includes/header.php: no PHP/template errors reported.
- Confirmed CEO1 is present in MANAGEMENT_ALLOWLIST.

## Known Open Issue
- Credential Manager still needs work: clicking Lock or Suspend in Management is not responding as expected in the UI.
- Treat credential lock/suspend/revoke flow as unresolved until click behavior is verified end-to-end in browser testing.

## Next Feature Request (Demo Mode)
- Add a "Demo Mode" button on the portal login screen.
- Demo Mode should route to a new selection page where the user chooses:
	- Employee Experience
	- Manager Experience
- The selected demo path should bypass normal authentication and open the corresponding role-specific portal view directly.
- Demo Mode should be read-only relative to real data, but writable within the demo session:
	- Users can enter/edit data while in Demo Mode.
	- Those changes must never persist to real user/production records.
- On leaving Demo Mode (logout, exit, timeout, or session end), all demo-entered changes must be discarded and data reset to the standard demo baseline.
- Demo views must be fully populated with mock data, including:
	- Mock employee rosters
	- Fully completed employee profiles (all fillable fields populated)
	- Individual employee KPI histories and trend data
	- Company-wide KPI dashboards and historical snapshots
	- Sales pipeline and closed sales records
	- Mock expenses and income
	- Reimbursements and reimbursement approval history
	- Invoices with realistic lifecycle states (draft, sent, paid, overdue)
	- Receipt records linked to expenses/reimbursements
	- Mock KPI metrics/charts
	- Mock pay data
	- Mock LedgerPro transaction/reconciliation data
- Demo seed data should represent an actively used, successful Saltshore business with believable volume, cadence, and cross-linked records across modules.
- For demo completeness, every field that can be filled in should be filled in with coherent mock values.
- Demo dataset should be isolated from production/user data and safe to reset between sessions.

## Expected Behavior After Fix
- For owner users: full sidebar tabs visible.
- For management-allowlisted users: full owner-page sidebar tabs visible plus Management.
- For non-allowlisted employee users: restricted access behavior remains in place.

## Recommended Follow-Up Checks
1. Log in as owner and verify Dashboard, CalGen, FinPro, LedgerPro, KPIs, Reports, Settings, and Logout are visible.
2. Log in as CEO1 (or another allowlisted management login) and verify the same plus Management.
3. Log in as a non-allowlisted employee and verify restricted visibility/access still matches policy.
4. Confirm direct URL access to owner pages follows expected role rules.

## Notes for Next Contributor
- Sidebar and route gate logic are centralized in portal/includes/header.php.
- Management allowlist is configured in portal/config.php via MANAGEMENT_ALLOWLIST.
- If role policy changes, update both menu rendering and route-level checks together.
- Prioritize debugging Management credential action forms/events first (lock/suspend not firing from user report).
