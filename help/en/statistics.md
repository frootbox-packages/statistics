---
title: Statistics
about: [screen:frootbox.statistics.statistics]
permission: frootbox/statistics/report.show
order: 74
---
The screen **Statistics** shows how many people visit your website and
what they read: the visits and page views of the last 7, 30, 90 or 365
days, compared with the days before, the visits per day, the most
visited pages, where visitors come from and their devices.

## How visits are counted

A small script on every page reports the page view. It sets no cookies,
stores nothing in the browser and sends nothing that tells visitors
apart: no IP address, no fingerprint. That is why the website needs no
consent for it.

- A **page view** is every page that opens, also after a reload.
- A **visit** begins when someone comes from another website, a search
  engine, a link in an email or a bookmark. Moving on to other pages of
  your website, reloading and going back do not start a new visit.
- **Where visitors come from** is the website of the link they followed,
  e.g. a search engine. "Direct or unknown" means a bookmark, a typed
  address or a browser that does not say. A link with
  `?utm_source=newsletter` shows up as "newsletter".

Not counted are signed-in users, the editing mode, previews, search
engines and other bots, and browsers without JavaScript. The numbers are
therefore a little lower than the true ones, but they show well how
visits change.

## When numbers show up

Page views are collected first and added up on every run of the
scheduler (cron) and whenever you open this screen.
