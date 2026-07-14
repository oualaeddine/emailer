# 21 — Open & Click Tracking

## 21.1 Tracking Pixel

A 1x1 transparent GIF/PNG served from a dedicated tracking route/subdomain (configurable "Tracking Domain" in Settings, e.g. `track.mailer.example.com`, so tracking traffic is distinguishable and can be routed/CDN-cached independently of the main app). Each outbound message's rendered HTML gets a unique pixel URL injected at send time (not in editor previews, per [11-email-composer.md §11.8](11-email-composer.md#118-html-processing-pipeline-pre-send)):

```
https://{tracking_domain}/t/o/{message_uuid}.gif
```

`TrackingPixelController`: on hit, records a `message_events` row (`event_type = opened`, metadata = ip/user-agent), increments `messages.open_count`, sets `messages.status = opened` and `opened_at` if not already set (never downgrades a later status like `clicked` back to `opened`), then returns the static 1x1 image (cached response, `Cache-Control: no-store` on the tracking endpoint itself but the image asset is a static file served efficiently).

## 21.2 Click Redirect Service

At send time, every `<a href>` in the message body is rewritten to a tracking redirect URL, and the original destination is recorded in `click_links` (see [04-database-design.md §4.10](04-database-design.md#410-tracking-module)):

```
https://{tracking_domain}/t/c/{tracking_token}
```

`ClickRedirectController`: looks up `click_links` by `tracking_token`, records a `message_events` row (`event_type = clicked`, metadata includes `link_id`, ip, user-agent), increments both `click_links.click_count` and `messages.click_count`, sets `messages.status = clicked`/`clicked_at` if not already set, then issues an HTTP 302 redirect to the original URL. Unsubscribe links are a special-cased `click_links` entry whose destination is the internal unsubscribe confirmation page rather than an external URL (so unsubscribe clicks are tracked the same way but handled distinctly, see [23-suppression-list.md](23-suppression-list.md)).

## 21.3 Unique Recipient Tracking

Because pixel/redirect URLs are keyed by `message_uuid`/`tracking_token` which are 1:1 with a specific `messages` row (itself 1:1 with a specific recipient+send), every open/click is inherently attributable to a single recipient without needing cookies or additional identifiers.

## 21.4 Campaign & Link Analytics

Aggregated from `message_events`/`click_links` joined through `messages.campaign_id`:
- **Campaign analytics**: unique opens (distinct messages with `open_count > 0`) vs. total opens (sum of `open_count`), same for clicks; open rate = unique opens / delivered; click rate = unique clicks / delivered; click-to-open rate = unique clicks / unique opens.
- **Link analytics**: per-`click_links` row click counts within a campaign, ranked, to identify top-performing CTAs — surfaced in Campaign Detail → Analytics tab ([15-campaign-management.md §15.8](15-campaign-management.md#158-campaign-detail-tabs)) and [25-reporting.md](25-reporting.md).

## 21.5 Bot Filtering

Many mailbox providers (notably Apple Mail Privacy Protection, and corporate security scanners) pre-fetch tracking pixels and/or links automatically, inflating open counts. Mitigations:
- **User-agent heuristics**: known scanner/bot user-agent patterns (configurable list in Settings) are recorded but flagged `is_likely_bot = true` in `message_events.metadata` and excluded from headline "unique opens" metrics (still visible in raw event data for transparency).
- **Timing heuristics**: an open event firing within a few seconds of `sent_at` for many recipients simultaneously (characteristic of Apple MPP proxy pre-fetching) is flagged similarly.
- **Click validation**: click events are trusted more than opens generally (a click requires deliberate interaction with a rendered link), though the same bot-flagging metadata approach applies for automated link-scanning security tools.
- Reporting explicitly labels metrics as "opens (bot-filtered)" vs. "raw opens" so users understand the adjustment rather than hiding it silently.

## 21.6 Privacy Considerations

- Tracking pixel/redirect endpoints are unauthenticated by necessity (recipients aren't logged in) but rate-limited per IP to mitigate abuse/scraping of the tracking endpoints themselves.
- IP addresses captured in `message_events.metadata` are treated as personal data: retention follows the same policy as `messages` generally, but Administrators can configure IP truncation/anonymization (e.g. zero out last octet) in Settings if required by organizational privacy policy.
- Tracking can be disabled entirely per-campaign (a `campaigns` toggle, e.g. `tracking_enabled boolean default true` — noted as a minor schema addendum to §4.8) for sensitive transactional sends where open/click tracking is undesirable; when disabled, no pixel/link-rewrite occurs and the message is sent as-authored.

Continue to [22-email-verification.md](22-email-verification.md).
