# UI, Responsive & Accessibility QA

## Scope

This document records source-level responsive and accessibility QA for the e-Karamchari employee and admin portals. It covers all major employee/admin pages currently present in the repository plus the session-expired and unauthorized system pages.

The automated checks are conservative. They verify structural requirements and shared accessibility primitives without claiming browser/device behavior that has not been directly observed.

## Coverage

Employee major pages: employee-login.html, employee/dashboard.html, employee/apply-leave.html, employee/leave-status.html, employee/attendance.html, employee/submit-grievance.html, employee/grievance-status.html, employee/profile.html, employee/salary-slip.html, employee/service-record.html.

Admin major pages: admin-login.html, admin/dashboard.html, admin/add-employee.html, admin/edit-employee.html, admin/employees.html, admin/employee-services.html, admin/attendance.html, admin/leave-approvals.html, admin/grievances.html, admin/profile.html, admin/salary.html, admin/service-records.html, admin/reports.html, admin/settings.html.

System pages: employee/session-expired.html, employee/unauthorized.html, admin/session-expired.html, admin/unauthorized.html.

## Checks

### Keyboard navigation

The shared stylesheet provides visible focus outlines. The shared utility layer adds a keyboard-accessible Skip to main content link to pages containing a main element.

The login password visibility control is implemented as a real button and exposes aria-label and aria-pressed state.

### Screen-reader semantics

Common dashboard controls receive accessible names and state semantics from the shared Utils accessibility initializer:

- mobile navigation toggle: accessible name, aria-expanded and aria-controls
- notifications control: accessible name
- user dropdown: aria-haspopup, aria-expanded and aria-controls
- modal close controls: accessible label
- active modals: dialog semantics and aria-modal

### Responsive layout

The shared and dashboard styles include tablet/mobile breakpoints and responsive table containers. A defect was found in admin/attendance.html where an inline flex-wrap: nowrap override prevented the shared responsive stats grid from stacking. The override was removed.

### Touch targets

Compact dashboard controls receive a minimum 44px width and height on narrow or coarse-pointer interfaces.

### Tables

Table containers use horizontal scrolling on narrow widths. Mobile dashboard rules reduce table density while preserving access to the full table.

### Motion and contrast

The shared stylesheet supports reduced-motion and high-contrast preferences.

## Reproducible Defects Logged

Issue #49: four minimal session-expired/unauthorized pages lacked the responsive viewport declaration. Fixed by adding the standard responsive viewport metadata to all four pages.

Issue #50: common dashboard controls lacked consistent accessible names and state semantics. Fixed in frontend/js/utils.js.

Issue #51: admin attendance summary cards were forced into one row on mobile. Fixed by removing the inline non-wrapping layout override.

Issue #52: employee and admin password visibility controls were implemented as mouse-only spans. Fixed by replacing them with keyboard-focusable buttons and exposing their state.

## Automated Verification

Run:

    bash tests/ui-accessibility.sh

The Application Quality GitHub Actions workflow runs the UI source checks together with PHP syntax checks, Docker startup, static asset verification and the integration suite.

## Manual Browser Matrix

| Viewport | Width | Focus |
|---|---:|---|
| Desktop | 1440px | complete navigation and form/table layout |
| Laptop | 1024px | sidebar transition and content density |
| Tablet | 768px | grid stacking, filters and tables |
| Mobile | 390px | overflow, controls and dialogs |
| Small mobile | 320px | minimum-width edge cases |

Manual release validation should cover keyboard-only navigation, visible focus, dialog Escape handling, form validation/error messaging, table scrolling, loading and empty states, notification panel behavior, and touch target usability.

## Verification Boundary

The repository now contains automated structural/source checks for the complete page set plus Docker integration checks. These checks do not substitute for human evaluation with assistive technologies or every browser/device combination; those remain explicit release-validation activities.
