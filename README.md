# Reporting extras for Kimai

Additions to [Kimai](https://www.kimai.org/)'s **Reporting** section: quarterly reports, charts
and detail links on the user reports, and a summary report.

## Quarterly reports

**Quarterly view for one user** and **Quarterly view for all users** are Kimai's yearly
reports for one quarter: a column per month, the same filters, and the same spreadsheet
download. Quarters are calendar quarters (January to March, and so on), named **Q1 2026** to
**Q4 2026**. The period picker steps a quarter back or forward with its arrows, and its menu
offers the current quarter and the eleven before it. The report for one user needs
`report:user`; the report for all users needs `report:other`, like Kimai's own. In the report
for all users, a user's total opens their quarterly report and a month opens their monthly
report.

In the overview, each quarterly report sits between Kimai's monthly and yearly report of the
same kind. Kimai only lets plugins append reports, so the plugin reorders Kimai's private list
of reports; if a Kimai release changes that list, the quarterly reports move to the end
instead.

## Reporting overview

On Kimai's reporting overview, the icons of the weekly, monthly, quarterly and yearly views,
for one user and for all users, get a small letter for their period (**W**, **M**, **Q**,
**Y**; in Dutch **W**, **M**, **K**, **J**), and the first word of their name, which holds
the period in Kimai's English, Dutch and German names, is bold. The cards are found by their
link, so the other reports are left alone.

## Charts on the user reports

Kimai's **Weekly view for one user**, **Monthly view for one user** and **Yearly view for one
user**, and the **Quarterly view for one user**, get two charts above their table:

- a bar chart of the hours per day (per month in the quarterly and yearly view), stacked by
  project;
- a doughnut chart of each project's share of the period, with the total time in its centre.

The charts follow the period and user picked in the report, including a financial year when
one is configured, and show the same user as Kimai's table, also when a disabled user is asked
for. In the table, projects are indented under their customer, and customer and project names
link to their detail pages for users who may view them.

The **Customer** and **Total** headings of the table sort it by name (A to Z first) or by its
totals (largest first); a second click reverses the order. Projects and activities are
sorted within their customer and project. The choice is kept in the browser's local storage,
so it carries over to the next report; without storage it lasts until the page is left.

Each duration in the doughnut chart's legend links to the records it counts, the same address
as the project's total in Kimai's table. The **Other** item opens the records of all the
projects it combines. The legend only links projects that the table links.

Next to the period picker, buttons switch to the other three reports for the same user and
summing mode: the yearly view gets **Week**, **Month** and **Quarter**, and so on. The new
report opens on the period that contains today if the current period does, and otherwise on
the period that contains the current period's first day.

## Summary report

**Reporting** > **Summary** shows a period on one page:

- totals for the period: total time, billable time and, with permission, the amount;
- a stacked bar chart of the time per day (periods up to 62 days) or per month;
- a doughnut chart of each group's share, with the total time in its centre;
- a breakdown per project, customer, activity or user that opens to show the time per
  description: at most 50 per group, with one line for the rest.

The period comes from Kimai's own date range picker, with its presets for weeks, months,
quarters and years, plus arrows to step back and forward. Without a period ("all time") the
report covers everything from the first to the last record. Periods longer than 62 days are
summed per month in the database, so long periods stay quick.

It ships with the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai/blob/main/kimai/DOCS.md), but works in any Kimai
installation.

## Dashboard widget

**Your working hours by project per week** (`ReportingGraphsWorkingTime`) is a dashboard widget for the
logged-in user's week: the stacked bar chart per day and the doughnut chart of the projects'
shares, the same as on the user reports, with arrows to the previous and next week. The week
is passed in the dashboard's `working_time_week` query parameter. The totals of today, the
week, the month and the year (or the financial year, when one is configured) link to the
weekly, monthly and yearly user report, for users with `report:user`. It is a separate widget
next to Kimai's own **My working hours**, which stays available; users add it under the
dashboard's **Settings**. The widget needs `view_own_timesheet`, like Kimai's chart.

## Hours overview widget

**Hours overview** (`ReportingGraphsHoursOverview`) shows today, this week, this month and this
year (or the financial year) in Kimai's card style, under two headings: **My hours** for the
logged-in user, and **Everyone's hours** for users with `view_other_timesheet`, counted the way
Kimai's own `Duration*` cards count them. The whole card links to the user report
(`report:user`) or the report for all users (`report:other`); the today cards open the week.
It is meant to replace Kimai's four duration cards, whose titles do not say whose hours they
count. `Service/HoursTotals.php` sums the periods for this widget and the working hours
widget.

## Linked duration cards

Kimai's duration cards on the dashboard support a `route` option that turns their icon into a
link. `DashboardLinksSubscriber` sets it on the registered widgets for the dashboard request
only, before Kimai clones them per user, so no dashboard settings are stored. This relies on a
fresh service container per request, as with PHP-FPM or Apache; under a persistent worker
runtime the option would carry over to other requests:

| Cards | Open | Needs |
| ----- | ---- | ----- |
| `DurationToday`, `DurationWeek`, `DurationMonth`, `DurationYear` (everyone's hours) | `report_weekly_users`, `report_monthly_users`, `report_yearly_users` | `report:other` |
| `userDurationToday`, `userDurationWeek`, `userDurationMonth`, `userDurationYear` (own hours) | `report_user_week`, `report_user_month`, `report_user_year` | `report:user` |

The today cards open the week, as there is no daily report.

## Requirements

Kimai 2.67.0 or later. It uses the Chart.js build that Kimai ships, so it needs no assets of
its own beyond one script and one stylesheet, which the plugin serves itself. Any logged-in
user may load them, as the dashboard widget needs them without access to the reports.
Browsers keep them for a day, and a release changes the version in their address.

The quarterly reports build on Kimai's own report code: its user report controller base
class, its statistics service and its yearly report templates. A Kimai release that changes
those may need a matching plugin update; check the quarterly reports after upgrading Kimai.

## Installation

1. Download the zip of the latest [release](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle/releases)
   and unzip it into `var/plugins/` in your Kimai installation, so the plugin ends up in
   `var/plugins/ReportingGraphsBundle/`.
2. Rebuild Kimai's cache: `bin/console kimai:reload --env=prod`.

There are no database changes.

## Permissions

| Who                                          | Sees in the summary report                      |
| -------------------------------------------- | ----------------------------------------------- |
| Users with `view_reporting`                  | Their own time                                  |
| Users who may see other users' reports       | A user picker with every user they may see, and **All users** |
| Users with `view_rate_own_timesheet` / `view_rate_other_timesheet` | Amounts                    |

"Other users' reports" is Kimai's `report:other` check: `view_other_reporting` plus
`view_other_timesheet`. Team leads only see their team members, as in Kimai's own reports.
A team member's records on a project, customer or activity limited to teams the viewer is not
in count in the totals, as in Kimai's reports, but their descriptions are hidden: Kimai's lists
and exports hide those records from the viewer.

## Translations

English and Dutch, in `Resources/translations/reporting_graphs.*.xlf`.

## Other plugins

This plugin is one of six Kimai plugins made to work together: the quick start bar starts
and stops records, short time entries and inline timesheet editing make correcting them
quick, timesheet extras keep the record list tidy, reporting extras show where the time
went, and ODT templates turn the records into your own documents. Each one also works on its
own. The other five:

- [Quick start bar](https://github.com/FaliseDotCom/kimai-quick-timer-bar-bundle): start and stop time recording from one bar below the top bar, and start a record again with its play button.
- [Short time entries](https://github.com/FaliseDotCom/kimai-short-time-entries-bundle): type `945` for 9:45 or `10` for ten minutes in Kimai's forms.
- [Inline timesheet editing](https://github.com/FaliseDotCom/kimai-inline-timesheet-edit-bundle): edit your own records right in the record list.
- [Timesheet extras](https://github.com/FaliseDotCom/kimai-timesheet-extras-bundle): collapsible days in the record list and closable search filters.
- [ODT templates](https://github.com/FaliseDotCom/kimai-odt-templates-bundle): exports and invoices from your own LibreOffice Writer templates.

## Issues and contributing

Report problems and ideas in this repository's [issues](https://github.com/FaliseDotCom/kimai-reporting-graphs-bundle/issues). Pull requests are
welcome here too.

The plugin is developed in the [Kimai app for Home Assistant](https://github.com/FaliseDotCom/ha-kimai) repository,
in `kimai/bundles/ReportingGraphsBundle/`, and published from there to this repository with each
release. An accepted pull request is applied there, with its author kept, and arrives here
with the next release.

## License

MIT, see [LICENSE](LICENSE).
