# frootbox/statistics

Statistics for the Frootbox CMS without cookies or personal data: visits
and page views per day, the most visited pages, where visitors come from
and their devices, on the screen "Statistics" of the backend.

## How it counts

A small script (`assets/count.js`) on every page that visitors see sends
the target and language of the page to `POST /_statistics/hit`, and for
the first page of a visit the referring host (or `utm_source`) and the
kind of device. No cookies, no storage in the browser, no IP address, no
hash of anything: nothing tells visitors apart, so no consent is needed.

- A **page view** is every page that opens.
- A **visit** starts with a page opened from another website or none,
  not on a reload, with the back button or from a page of the website.

Signed-in users, the editing mode, previews, the design page, error pages,
bots (by user agent) and requests from other websites count not.

Hits go into files below `var/statistics/` (one line each, so a visit
costs no write to the database) and are added to the tables
`frootbox_statistics_pages`, `…_sources` and `…_devices` on every run of
the scheduler and before every report. Only targets with a current
address count. A day's file stops growing at 20 MB.

## Query

`frootbox/statistics/report.show` with `days` (1 to 366, default 30)
returns the report, also over the REST API. The role "editor" gets
`frootbox/statistics/*` on installation.
