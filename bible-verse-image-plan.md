


Invite team members

I have a bible website. I want to include a Bible Verse card with picture generator. For this I have the following plan, suggest the usable free and royalty free API based services:
1. As an administrator on the site I will define typical themes: safety, glory, prayer etc.
2. I will assign picture themes to these themes, like glory will be sunshine and majestic nature etc. 
3. When someone asks for a verb card with a picture, I will take the vector embedding of the given scripture part (1 or two verses), and find the closes typical themes, to find out the bible verse is about glory e.g.
4. Based on this I will look up the image provider site API for themes related to prayer and sunshine and majestic nature. I will generate 4 pictures using PHP each containing the image and the text. The users chooses what he or she likes the most.

Thought for 23s
Here are usable, free (at least free-tier), “royalty-free / free-to-use” image APIs that fit your flow (theme → keywords → search → pick 4 → render verse card). I’ll also flag the license/attribution gotchas so you don’t accidentally violate terms.

Best fits for your use case
1) Openverse API (Creative Commons / Public Domain search)
Why it fits: It’s a meta-search for openly licensed media (CC + public domain) and returns attribution + license info so you can stay compliant automatically. Great for “majestic nature / sunshine / prayer” style queries. 

Key benefit for you: You can filter by license type and display proper attribution on the card (or in a “credits” drawer).

Watch-outs:

“Openly licensed” ≠ “no strings attached.” Some results may require attribution, ShareAlike, or have NonCommercial restrictions depending on the CC license. (So you’ll want to filter licenses you can support.)

2) Pixabay API (royalty-free content license + simple API)
Why it fits: Straightforward REST API for “royalty-free images/videos” and very easy to query by keywords. 

Watch-outs:

Pixabay’s API docs ask that if you use the API, you show users where results come from when displaying search results. 

(In your UI, a small “Image: Pixabay / Author” line solves this.)

3) Pexels API (free stock photos/videos + clean results)
Why it fits: High-quality photography, simple keyword search, free API key. 

Watch-outs:

Follow their API/credit rules (they provide guidance and terms). 

4) Unsplash API (high-quality photos; strong API rules)
Why it fits: Excellent for “nature / light / landscapes” themes.
Big watch-outs (important):

Unsplash has API Guidelines (attribution guidance, “trigger download,” etc.). You should implement these to stay compliant. 

Attribution isn’t always legally required by the license, but Unsplash explicitly recommends it for API uses and provides a standard format. 

If you want minimal compliance complexity, prioritize Openverse + Pixabay + Pexels, and add Unsplash only if you’re comfortable implementing their required API behaviors.

Practical recommendation: use a “provider aggregator” approach
In your backend, create a simple interface:

searchImages(provider, query, orientation, safeSearch, pageSize)

Normalize results into:
image_url, photographer_name, source_name, source_url, license, attribution_text, download_tracking_url(optional)

Then your “theme → query” step can hit 2 providers and merge results, giving you plenty of options to pick 4.

How this plugs into your 4-step plan
Step 1–2 (Admin themes)
Keep your theme catalog like:

glory → keywords: sunrise, rays of light, mountains, majesty, heavens, golden sky

prayer → keywords: hands praying, candle, quiet, church, solitude, sunset

safety → keywords: shelter, calm sea, home light, refuge, valley, gentle landscape

Add optional negative keywords to avoid mismatches (e.g., for “glory” avoid “concert lights” if you want nature-only).

Step 3 (Embedding → closest theme)
To keep this free, consider running embeddings locally (no paid API):

Use a small sentence-transformers model on your server (fast + no per-request cost).

Store theme embeddings and verse embeddings, cosine similarity → pick top theme(s).

(If you must use an API for embeddings, free tiers exist on various platforms, but they change often; self-hosting is the most stable “free forever” option.)

Step 4 (Look up images + render 4 cards in PHP)
Implementation notes:

Always store attribution + license alongside the chosen image.

Cache the image (or at least the chosen image URL + metadata) so you don’t re-query every time.

Add “safe search” / “family friendly” filters where supported.

For Unsplash specifically, follow their “trigger download” requirement when a user selects an image. 

My “start simple” stack
If you want the best blend of free + easy + licensing clarity:

Openverse API (open licenses + attribution fields) 

Pexels API (quality + simple) 

Pixabay API (large catalog, easy) 

Add Unsplash API later once you’re ready to implement their API guideline requirements. 

If you tell me whether your site is commercial (ads/subscriptions) and whether you’re okay showing a small “Photo credit” line on the verse card (or only on a details page), I can suggest the safest license filters (especially for Openverse/CC) and a concrete “provider priority + fallback” strategy.


I already have embeddings for all the verses, so I will just prepare the embeddings with openai for the theme words, almost zero cost. I cannot really decide between Pexels and Pixabay.

Thought for 44s
If your main question is “which one will be less painful to run long-term for a verse-card generator?”, the decision usually comes down to (a) rate limits, (b) attribution/link requirements for API use, and (c) filtering controls (safe search, categories).

The practical differences (Pexels vs Pixabay)
Rate limits / scaling
Pexels: default limit is 200 requests/hour + 20,000/month (and they can remove limits for eligible apps, but you must request it). 

Pixabay: default is 100 requests per 60 seconds (per API key). 

Implication: if your site can grow (many users generating cards), Pixabay is easier to scale without special approval.

“You must show credit / link” requirements (API-specific)
Pexels API guidelines: “show a prominent link to Pexels” and “always credit our photographers when possible.” 

(Even though the general Pexels license says attribution isn’t required, the API guidelines add stronger requirements when you use the API.) 

Pixabay API docs: if you use the API, “show your users where the images and videos are from, whenever search results are displayed.” 

Implication for your UX:

If you already have a 4-image chooser screen, Pixabay’s requirement is easy: add a small “Source: Pixabay” + author line on that chooser.

Pexels’ wording (“prominent link”) can be stricter to satisfy aesthetically.

Caching / hotlinking rules
Pixabay: requires requests to be cached for 24 hours, and permanent hotlinking (using their image URLs directly in your app long-term) is not allowed; download to your server if you’ll use the images. 

Implication: Pixabay pushes you toward exactly what you’re likely doing anyway for verse cards: download → render card → serve from your CDN.

(I didn’t see an equivalent “no permanent hotlinking” rule in the Pexels doc excerpt we pulled, but Pexels does emphasize attribution/linking and not abusing the API.) 

Content + filtering for “Bible verse themes”
Pixabay has a built-in category list including “religion” and also a safesearch parameter. 

Pexels has strong orientation/size/color/locale controls, but the docs don’t show a safesearch flag (at least not in the main photo search section). 

Implication: Pixabay gives you slightly better “guardrails” for a faith site (family-friendly filtering + “religion” category).

My recommendation for your exact workflow
For “generate 4 candidates → user picks one → you render/store the final verse-card”:

Pick Pixabay if:
You want fewer worries about default quotas at scale. 

You want explicit safe search and a religion category. 

You’re fine adding a small source label on the chooser UI (not necessarily on the final downloadable image). 

You’re already okay downloading/caching images server-side (which you should be for card rendering anyway). 

Pick Pexels if:
Your traffic will stay modest OR you’re willing to apply for higher limits. 

You’re comfortable with a prominent “Photos provided by Pexels” link and photographer credit on the chooser page (and possibly elsewhere). 

Net: If you can’t decide, choose Pixabay first for the rate-limit + safesearch + category advantages, and keep Pexels as a fallback provider later.

A “best of both” approach (minimal extra work)
Implement one interface and plug in both providers:

Query Pixabay first (q=theme keywords, category=religion|nature, safesearch=true). 

If results < N, query Pexels with the same keywords. 

Show credits per tile on the chooser UI:

“Pixabay — Author”

“Pexels — Photographer” + a “Photos provided by Pexels” link in the footer 

That way you’re not betting your whole feature on one provider’s policy changes.

If you tell me roughly your expected traffic (e.g., “100 cards/day” vs “50k/day”), I’ll suggest a caching strategy (how aggressively to cache searches vs caching chosen images) that keeps you comfortably inside both providers’ rules.


Yes, I'm ok with the quotas, Pixabay seems to have bigger library, and adding religion helps a lot. I'm ok to add small source. So let's design the solution for Pixabay.

Thought for 6s
Great — Pixabay is a solid match for your flow (big library, category=religion, safesearch=true, generous rate limit), and their API rules line up well with your “show 4 options → user picks → render card” UX. 

Below is a concrete design you can implement in PHP.

1) What Pixabay expects from you (design constraints)
Build these into your solution from day 1:

Show source when displaying search results (your 4-up chooser is perfect): e.g. “Pixabay • by {user}”. 

Cache identical API requests for 24h (store results keyed by query params). 

No permanent hotlinking: use returned image URLs only temporarily; if you’ll use an image in a verse-card, download it to your server/CDN first. 

Avoid “systematic mass downloads” / automated scraping behavior. Your “user asks → fetch a few → pick one” is fine. 

2) Data model (minimal but complete)
Themes table (admin-defined)
id

slug (e.g. glory)

label (Glory)

keywords (CSV or JSON array): ["sunrise","rays of light","mountains","majestic","heavens"]

pixabay_category (optional): religion, nature, backgrounds, etc.

orientation_pref (optional): horizontal for cards

Pixabay search cache (24h)
cache_key (hash of request params)

created_at

response_json (store the entire hits payload)

expires_at (= created_at + 24h)

Image choice (when user picks)
id

verse_ref (e.g. PSA.23.1-2)

theme_slug

pixabay_id

pixabay_page_url (for “source” link)

pixabay_user (author username)

download_url (the URL you will fetch once)

local_path (where you saved it)

chosen_at

This keeps you compliant and lets you regenerate cards without re-querying Pixabay.

3) Query-building strategy (theme → Pixabay query)
For each request you already have: verse embedding → top theme(s).

From theme → Pixabay query params:

q: join 2–5 keywords (URL-encoded). Example for Glory:
sunrise + rays of light + mountains + majestic

category: use your admin-mapped category if you have it; use religion when appropriate.

safesearch=true (always, for a Bible site). 

image_type=photo (usually best for verse cards; avoid vectors unless you want that look)

orientation=horizontal (good default for 16:9-ish cards)

per_page=50 (fetch a decent pool once, then choose 4 locally)

order=popular (often yields nicer “card-ready” images)

Then in your own code, filter hits for:

minimum width (e.g. imageWidth >= 1920)

avoid busy scenes (optional heuristic: prefer “nature/backgrounds”, or later use a simple “text contrast check”)

4) Picking 4 candidates without hammering the API
One API call per request (or even per theme per day via caching), then pick 4:

Take cached response if present and fresh (<24h). 

Randomize within a “quality subset”:

sort by favorites/likes or use Pixabay’s ranking and then sample from top 30

Ensure diversity:

don’t pick 4 from same user

don’t pick 4 nearly identical aspect ratios (optional)

Return to UI:

thumbnail URL (for quick load)

author + “Pixabay” + link to the image page (fulfills “show users where from”). 

5) “No hotlinking” implementation (download-on-select)
On the chooser screen you can use Pixabay URLs temporarily. 

When user clicks one:

Server receives {pixabay_id}

Look up that hit in cached results (or do a single “get by id” style search if needed)

Download largeImageURL (or another size you choose) to your storage

Render the verse card using the local file

Store attribution metadata (pixabay id, user, pageURL)

This directly matches Pixabay’s “don’t permanently hotlink” guidance. 

6) PHP request example (core pieces)
Pixabay search (with caching)
Build a canonical params array

cache_key = sha1(json_encode(params_sorted))

If cache hit and created_at < now-24h, return cached

Else call:

GET https://pixabay.com/api/?key=API_KEY&q=...&category=religion&safesearch=true&image_type=photo&orientation=horizontal&per_page=50&order=popular

Rate limit is generous by default (100/60s), but caching still required. 

Download on select
Use curl/file_get_contents with timeouts, save to /uploads/pixabay/{pixabay_id}.jpg, verify MIME, enforce max size.

7) Attribution UX (simple + compliant)
On the 4-image chooser tiles:

“Pixabay • {username}” (link “Pixabay” to pageURL, link username if you want)

Optionally add an ℹ️ “About image licensing” link in footer

Pixabay explicitly asks you to show users where results are from when displaying search results. 

For the final generated image (the PNG/JPG card), you can:

either include a tiny footer “Photo: Pixabay/{username}”

or keep the generated image clean and store attribution in the download page / metadata screen
(Your call; Pixabay’s ask is about displaying search results, but crediting on the final card is still a nice touch.)

8) Edge cases you should handle
No results for category=religion: fallback to category=nature with the same keywords, still safesearch=true.

Busy photos where verse text becomes unreadable: add a rendering step that applies a soft dark/blur overlay behind text.

Repeated user requests: cache by (theme + keyword-set + orientation + image_type + category + safesearch + order).

If you want, I can sketch a concrete endpoint set like:

GET /api/verse-card/candidates?verseId=... → returns 4 candidate tiles + attribution

POST /api/verse-card/render with {candidateId, verseId, style} → downloads image, renders PNG, returns URL

…and a recommended card rendering pipeline (font sizing, line wrapping, contrast, watermark/attribution placement).


Ok, now continue planning:
I will run everything via Laravel Horizon. That will help keeping the rate limits. It will have a dedicated queue to download images. We store downloaded images only while process it, so I need a mechanism to free storage at the end. This will be the user flow:
- the user selects a bible verse. From this moment we do the following automatically:
1. we assign automatically a theme and photo keywords (this is already implemented)
2. We look up 4 images from Pixabay, download, show a preview, and let the user choose. If she doesn't like any of the 4, she presses "more", and we download an other 4.
3. When the user selected the image (we no longer need the previous images), we generate the bible verse card for preview: ie. we automatically put the text on the image. The user will be able to download this final image. We delete the selected and generated images when the session ends.

Nice. With Horizon + “ephemeral images only”, you mainly need: (a) deterministic caching for Pixabay search, (b) an ephemeral storage lifecycle (TTL + cleanup job), and (c) safe concurrency so multiple “More” clicks don’t explode downloads.

Here’s a concrete plan that fits your flow and Pixabay’s rules (24h request caching, no permanent hotlinking).

Architecture overview
Queues (Horizon)
Create 3 queues with priorities:

pixabay-search (fast, rate-limited)

Calls Pixabay API (or returns cached response)

Writes candidate list (metadata + remote URLs) to DB/Redis

image-download (bounded concurrency)

Downloads only the 4 images you’re going to show

Stores in ephemeral disk (local or S3 with lifecycle)

Generates small previews/thumbnails

card-render (CPU-bound)

Composites text onto the selected image

Writes final file (ephemeral)

Returns preview + download token

You can throttle each queue separately in Horizon (number of workers, process count). That’s usually enough to avoid rate spikes.

Data model for “ephemeral session”
verse_card_sessions
id (uuid)

user_id nullable (allow guests)

verse_ref

theme_slug

keywords_json

status (searching, ready_to_choose, rendering, ready, expired)

expires_at (sliding TTL, e.g. now + 30 min)

verse_card_assets
id

session_id

type (candidate, selected_source, final_card)

pixabay_id nullable

pixabay_page_url nullable

pixabay_user nullable

remote_url nullable

storage_disk (e.g. ephemeral)

path

bytes

created_at

expires_at

state (queued, downloading, ready, deleted, failed)

pixabay_search_cache (24h)
cache_key

params_json

response_json

created_at

expires_at (= created_at + 24h)

This satisfies “cache identical requests for 24h” and keeps your user experience fast.

Storage strategy (ephemeral + safe cleanup)
Option A (simple): local disk + scheduled cleanup
Use storage/app/ephemeral/{sessionId}/...

A scheduled command runs every 5–10 minutes:

delete files where expires_at < now

mark state=deleted

delete empty session folders

Also run cleanup on “session end” explicitly.

This is easy and reliable.

Option B (best if you use S3/R2): bucket lifecycle rules
Upload to ephemeral/… prefix

Set lifecycle policy: delete objects older than X hours

Still keep DB cleanup, but storage cleanup becomes automatic.

Either way, keep the DB as source of truth and delete files idempotently.

User flow mapped to jobs
When user picks a verse (session start)
Controller:

create verse_card_session (TTL e.g. 30 minutes)

dispatch SearchPixabayCandidates(sessionId, page=1, per_page=50)

return {sessionId, status: searching}

Job SearchPixabayCandidates:

build params from theme/keywords:

q, category, safesearch=true, image_type=photo, orientation=horizontal, order=popular, per_page=50

check pixabay_search_cache with cache_key = sha1(sorted_params)

if hit and not expired: use cached response

else call Pixabay; store response in cache for 24h

select 4 hits not used yet in this session

create 4 verse_card_assets rows (type=candidate, state=queued, expires_at=session.expires_at)

dispatch 4 download jobs (or one batch job)

Job DownloadCandidateImage(assetId) on image-download:

download from Pixabay URL to storage/app/ephemeral/{sessionId}/candidates/{assetId}.jpg

generate thumbnail …thumb.jpg for fast preview

set state=ready

UI polls GET /sessions/{id} until candidates are ready, then shows 4 thumbs with “Pixabay • username” + source link on each tile (that meets their “show users where results are from when search results are displayed”).

If user clicks “More”
Important: don’t download infinitely; you want paging + reuse of cached search.

Controller:

ensure session valid

dispatch ProvideMoreCandidates(sessionId)

Job ProvideMoreCandidates:

pick next 4 from the cached hits list for that session

if exhausted, dispatch SearchPixabayCandidates(sessionId, page=2…) (still cached for 24h per params+page)

create assets + dispatch downloads

Also add a short debounce on the client (disable button while loading) + server-side guard:

store session.loading_more = true with a short TTL lock (Redis lock) so double-clicks don’t create 8–12 downloads.

If user selects an image
Controller:

mark selected asset as type=selected_source

delete other candidate assets immediately (best-effort):

delete files + mark deleted in DB

dispatch RenderVerseCard(sessionId, selectedAssetId, styleParams)

return {status: rendering}

Job RenderVerseCard on card-render:

open selected local image

apply overlay (gradient/blur rectangle) for readability

typeset verse text (wrapping + font scaling)

write final PNG/JPG to ephemeral/{sessionId}/final/{assetId}.png

create asset row type=final_card, state=ready

set session status=ready

Now user sees final preview and gets a download link.

“Session ends” semantics (and deletion)
You have two reliable triggers:

Sliding TTL: every user interaction extends expires_at by e.g. 15 minutes.

Explicit end:

When user downloads the final image, you can optionally end session immediately (or keep it for a short grace period).

Cleanup mechanisms (do both)
Scheduled cleanup: delete anything expired (covers abandoned sessions).

End-session endpoint: called when user navigates away / closes modal (best effort).

Delete candidate + selected + final assets

Mark session expired

This guarantees “we delete images when the session ends” and you don’t leak storage if they just close the tab.

Compliance + safety notes (Pixabay)
Cache identical requests 24h (your pixabay_search_cache does this).

Avoid permanent hotlinking: you’re downloading locally for previews + final rendering, and deleting later — good.

Show “where results are from” on the chooser screen: add the small “Pixabay • author” label + link.

Implementation details that prevent pain
1) Storage lock + idempotency
Use a Redis lock per assetId while downloading

Jobs should be idempotent:

if file exists and DB says ready → no-op

if session expired → delete partial file and exit

2) Avoid downloading originals
Use a “good enough” size for preview + rendering:

download largeImageURL for rendering

generate your own thumbnail for UI

3) Don’t keep re-hitting Pixabay for “More”
Fetch per_page=50 once, then paginate locally for several “More” clicks.

Only request next page when you’ve consumed the current list.

API endpoints (suggested)
POST /verse-card/sessions (verseRef) → {sessionId}

GET /verse-card/sessions/{id} → candidates + status

POST /verse-card/sessions/{id}/more → enqueue next 4

POST /verse-card/sessions/{id}/select (assetId) → enqueue render

GET /verse-card/sessions/{id}/final → final preview + download token

POST /verse-card/sessions/{id}/end → cleanup now

If you want, I can also propose:

a concrete Redis key scheme for locks and per-session “used pixabay_ids”

a robust font-sizing/wrapping algorithm for verse text (so it looks good on any background)

an S3/R2 lifecycle config if you prefer object storage over local disk


Why should I use a verse_card_session entity? There is already Session in Laravel, no?

Laravel’s Session (cookie/Redis/file-backed) is great for small bits of state (“current verse ref”, “current theme”, “candidate ids”), but it’s not a great place to manage an asset lifecycle with queues, retries, cleanup, and guest users across devices. A verse_card_sessions entity solves problems Laravel Session doesn’t.

Here’s the practical breakdown.

What Laravel Session is good for
Keeping UI state for one browser: current verse, last page, “more” button debounce token.

Temporary values that can disappear without harming correctness.

Fast reads/writes tied to a single client.

Where Laravel Session falls short for your workflow
1) Queue workers don’t “own” the user’s HTTP session
Horizon jobs run outside the request cycle. They can’t reliably read/modify a browser session the way controllers do (especially if you have multiple session drivers / stateless API / guests). You’ll end up passing a lot of session data into jobs anyway.

2) You need durable coordination across many jobs
You’ll have:

Search job

4 download jobs (maybe more with “More”)

Render job

Cleanup jobs

Those jobs need a shared “source of truth” for:

which candidate assets exist,

which are ready,

which pixabay ids were already used,

expiration time,

which files to delete.

Laravel Session is not designed to be that coordination layer.

3) Cleanup and auditing are much easier with DB records
You explicitly need: “delete selected and generated images when the session ends.”
With a DB entity, cleanup is:

“delete all assets where session_id = X”

and/or “delete where expires_at < now()”

With Laravel Session, you’d be hoping the session key still exists to tell you what to delete. If the session expires first, you lose the index of files you must remove.

4) Guests + multiple devices + refresh/back button
If a user refreshes, opens another tab, or comes back later:

Browser session might change or be cleared.

But you still want to know what to delete and what state the pipeline is in.

A verse_card_session gives you a stable id (uuid) that you can pass around and resume safely.

5) “More” clicks + concurrency control
You’ll want server-side guarding like:

“don’t download 8 images because of double click”

“don’t reuse same image”

“don’t render if session expired”

That’s much cleaner when you can atomically update DB fields / use row locks keyed by session_id.

A good compromise: use both
Laravel Session: store current_verse_card_session_id and maybe last-used style settings.

verse_card_sessions table: the job-visible, durable state machine.

verse_card_assets table: the cleanup index + attribution metadata + storage paths.

That way, your browser session can vanish and you still:

clean up storage,

handle job retries safely,

avoid orphan files,

remain compliant with “show source” and caching rules for Pixabay.

If you really don’t want a DB entity
You can do it with just Redis keys (basically reinventing the entity):

vcs:{id}:meta (hash: expires_at, status)

vcs:{id}:assets (set/list)

vcs:{id}:used_pixabay_ids (set)

vcs:{id}:lock_more (lock)

TTL on all keys

But that’s conceptually the same as verse_card_sessions, just harder to query/debug.

If you tell me your current stack (session driver: Redis? database? cookie?) and whether you already use Redis heavily for Horizon, I’ll suggest the simplest “session entity” implementation (DB vs Redis-only) that matches what you have.


Session driver is cookie/file. Horizon uses redis. But I'm ok now the DB-only implementation for this feature.

Perfect — with cookie/file sessions you definitely don’t want to rely on Laravel Session for coordinating Horizon jobs. A DB-backed “verse_card_session” + “assets” model is the cleanest approach, and Redis stays purely for Horizon + locks.

Here’s a DB-first implementation blueprint that you can drop into Laravel.

Tables (migrations)
verse_card_sessions
id (uuid, PK)

user_id (nullable FK)

verse_ref (string)

theme_slug (string)

keywords (json)

status (string: searching|choosing|rendering|ready|expired|failed)

expires_at (timestamp, indexed)

created_at, updated_at

verse_card_assets
id (bigint PK)

session_id (uuid FK, indexed)

kind (string: candidate|selected|final)

state (string: queued|downloading|ready|deleted|failed)

pixabay_id (bigint nullable)

pixabay_user (string nullable)

pixabay_page_url (string nullable)

remote_url (text nullable)

disk (string, default ephemeral)

path (string nullable)

thumb_path (string nullable)

bytes (bigint nullable)

expires_at (timestamp, indexed)

created_at, updated_at

pixabay_search_cache
cache_key (string PK)

params (json)

response (json)

expires_at (timestamp, indexed)

created_at, updated_at

Why cache table: satisfies Pixabay “cache identical requests 24h” and reduces API calls.

Disks & storage layout
config/filesystems.php add:

disk ephemeral → local storage storage/app/ephemeral

Paths:

candidates: ephemeral/verse-cards/{sessionId}/c/{assetId}.jpg

thumbs: .../c/{assetId}_t.jpg

final: ephemeral/verse-cards/{sessionId}/final/{finalAssetId}.png

Queue setup (Horizon)
Queues:

pixabay-search (1–2 workers)

image-download (small concurrency, e.g. 2–5 workers)

card-render (1–2 workers)

Plus Redis locks:

lock:verse-card:{sessionId}:more

lock:verse-card:{sessionId}:render

End-to-end flow (exactly your UX)
0) Start session when user selects verse
POST /api/verse-cards/sessions

Controller:

Create VerseCardSession with TTL (e.g. now + 20 minutes)

Dispatch SearchAndPrepareCandidates(sessionId, page=1)

Return {sessionId}

Also store sessionId in normal Laravel Session if you want (optional), but DB is the authority.

1) Search Pixabay + create 4 candidates
Job SearchAndPrepareCandidates(sessionId, page)

Load session, abort if expired

Build Pixabay params: q, category, safesearch=true, image_type=photo, orientation=horizontal, order=popular, per_page=50, page

cache_key = sha1(canonical_json(params))

Load cache row not expired; else call Pixabay and upsert cache with expires_at = now+24h

Select 4 new hits not yet used in this session:

easiest: store used pixabay_ids by querying verse_card_assets where pixabay_id not null

Insert 4 rows in verse_card_assets as kind=candidate, state=queued, expires_at=session.expires_at

Dispatch DownloadCandidateImage(assetId) for each

Set session status=choosing once at least 4 are queued

2) Download images for preview (and generate thumbs)
Job DownloadCandidateImage(assetId) (queue: image-download)

Load asset + session, abort if expired

Set state=downloading

Download remote_url → disk=ephemeral, path=...jpg

Generate thumbnail thumb_path

Set state=ready, fill bytes, path, thumb_path

UI:
GET /api/verse-cards/sessions/{id} returns the 4 ready candidates:

thumb_url

pixabay_user

pixabay_page_url

label: “Pixabay • {user}”

This satisfies Pixabay “show where results are from” at search results display time.

2b) “More” button
POST /api/verse-cards/sessions/{id}/more

Controller:

Acquire Redis lock lock:verse-card:{id}:more (10–15s)

Dispatch ProvideMoreCandidates(sessionId)

Return 202

Job ProvideMoreCandidates(sessionId)

Same logic as above, but:

Try to take next 4 from existing cached hits first (you can store “cursor” in session row as pixabay_page + pixabay_offset, or just keep selecting “new” ids from cache response; if exhausted, move to next page).

Create 4 assets, dispatch downloads.

Also: delete older candidates if you want to cap disk usage (e.g. keep last 12 thumbs max).

3) User selects one image → purge others → render final
POST /api/verse-cards/sessions/{id}/select with {asset_id}

Controller:

Mark chosen asset kind=selected

Immediately delete other candidate assets for that session (DB + disk best-effort)

Dispatch RenderVerseCard(sessionId, selectedAssetId)

Return 202

Job RenderVerseCard(sessionId, selectedAssetId) (queue: card-render)

Lock lock:verse-card:{id}:render

Load selected asset (local file must exist)

Render final PNG/JPG into session folder

Insert verse_card_assets row kind=final, state=ready, path=...

Set session status=ready

UI:
GET /api/verse-cards/sessions/{id}/final returns final preview + download url.

Deleting images when session ends (reliable)
Session TTL + sliding expiration
Every user action (GET session, more, select, final) can extend expires_at = now + 20 minutes.
This makes “active sessions” not expire mid-use.

Cleanup command (Scheduler)
Create php artisan verse-cards:cleanup running every 5 minutes:

Find sessions where expires_at < now() and status != expired

For each:

delete all assets on disk (Storage::disk(asset->disk)->delete(asset->path) and thumb)

mark assets state=deleted

set session status=expired

This covers tab-close and crashes.

Optional explicit end
If you also want immediate cleanup on “close modal”:
POST /api/verse-cards/sessions/{id}/end → do the same deletion and mark expired.
(But don’t depend on it; browsers are flaky.)

Small but important details
Prevent orphan files
Put the sessionId in the folder path so cleanup can also do a “delete directory” fallback:

Storage::disk('ephemeral')->deleteDirectory("verse-cards/$sessionId")

Idempotency
Jobs should be safe to retry:

If asset already ready and file exists → no-op

If session expired → delete partial files and stop

Rate safety
Only one Pixabay API call per “batch” (per theme/params) thanks to cache table.

Downloads are your main load; cap image-download workers.

If you want, I can write:

the actual Laravel migrations + Eloquent models,

the three jobs (SearchAndPrepareCandidates, DownloadCandidateImage, RenderVerseCard),

and the cleanup Artisan command (with chunking to avoid memory spikes).


I'm a little bit anxious that the horizon workers will each take a lot of memory. Do they? Or the one horizon (it's a docker container) doesn't have all those 100s of MBs of memory usage per worker

They can take a noticeable amount of memory per worker process, but it depends heavily on what your jobs do. Horizon itself is mostly a supervisor/UI layer; the real memory cost is the PHP worker processes running queue:work.

What actually consumes memory
Each worker is a separate PHP process → has its own memory footprint.

Baseline Laravel boot + Composer autoload + your app service providers commonly lands somewhere like tens of MB per worker (varies a lot by app, extensions, opcache, what you load).

Your specific jobs can add spikes:

Image downloads: not too bad if you stream to disk.

Image rendering (GD/Imagick): this is where memory can jump, because decoding a large JPEG into raw pixels is expensive.

Rule of thumb: a “large” 4000×3000 image decoded to raw RGBA is ~4000×3000×4 bytes ≈ 48 MB just for one in-memory bitmap, plus overhead and extra buffers. Imagick can use even more depending on operations. So rendering workers should be few and controlled.

The good news: one container won’t magically “share” memory across workers
If you run, say, 10 workers, you should expect roughly:

10 × (baseline worker memory) + spikes while doing image work.

So yes, it can become “hundreds of MB” total if you crank worker counts.

How to keep memory low (recommended settings)
1) Separate your queues and keep render workers low
pixabay-search: 1 worker

image-download: 2–4 workers (streaming)

card-render: 1 worker (maybe 2 later)

This gives you throughput without multiplying the expensive work.

2) Stream downloads; never load full image into PHP just to save it
In the download job:

stream HTTP response directly to a file handle

avoid file_get_contents($url) for big images

That keeps download workers quite lean.

3) Put hard caps on worker lifecycle
Even well-behaved jobs can leak memory (libraries, static caches). Use:

--max-jobs (restart workers after N jobs)

--max-time (restart after seconds)

--memory (restart if memory exceeds MB)

In Horizon, this maps to supervisor config options like maxProcesses, memory, maxTime, maxJobs depending on your version. The key idea: recycle workers.

4) Reduce image size before heavy processing
For the render job, don’t render from a 6K original if you only output e.g. 1200×630 or 1080×1080:

download “large enough” (e.g. 1920px wide)

or downscale as the first step in Imagick using efficient methods
This is the biggest lever for memory.

5) Prefer Imagick with resource limits (if available)
Imagick lets you set limits (memory/map/disk). If you can, set conservative limits so a single render can’t OOM the container.
(Exact settings depend on your Imagick build/config.)

6) Use OPcache in the worker container
OPcache reduces repeated parsing/compilation overhead. It doesn’t make memory “free”, but it often improves CPU and can stabilize per-worker usage.

A sane starting point (Docker)
If you’re anxious, start with:

1 search worker

2 download workers

1 render worker

--memory=256 (or lower/higher depending on your output size)

restart workers every ~200 jobs or every ~30 minutes

Then watch actual usage and adjust.

How to measure it quickly (so you stop guessing)
Inside the Horizon container:

look at ps -o pid,rss,cmd -C php (RSS in KB)

or top and see each php artisan queue:work process

You’ll see exactly what each worker costs in your app, which is what matters.

If you tell me which library you’ll use to render text onto images (GD, Intervention Image, Imagick, etc.) and your target output size (e.g. 1080×1080 vs 1920×1080), I’ll suggest worker counts + memory caps that are very unlikely to OOM.


If a worker uses 160MB of memory. Will it give back to the container for other workers to take if needed?

Mostly no — not in the way you’re hoping.

How it works in practice
In Docker, the “container memory” is just the sum of what all processes inside it are using.

If one worker process grows to 160 MB RSS, that memory is owned by that process.

If that worker later “frees” objects in PHP, Linux typically can reclaim some memory, but:

PHP’s allocator often keeps freed memory inside the process heap for reuse by that same process.

So the worker’s RSS often doesn’t drop much, even after the job ends.

Other workers usually cannot reuse that memory unless the first worker actually returns pages to the OS (which PHP often doesn’t do aggressively).

So you shouldn’t assume “it’ll give it back to the container so other workers can take it.” Usually it just means that worker will hover around that high-water mark.

What does reliably give memory back?
Restarting the worker process (the OS frees everything).
That’s why the standard pattern with queue workers is:

run with a --memory limit (or Horizon’s memory setting) so the worker restarts when it gets too big

also restart after max-jobs / max-time to avoid gradual bloat

Practical guidance for your case (image rendering)
If image processing makes a worker spike to ~160 MB, assume:

it will likely stay near that level after a few jobs

budget container memory as roughly:
(render workers × 160MB) + (download workers × baseline) + overhead

Then keep render workers low (1–2) and recycle them.

If you tell me whether you’re using Imagick or GD/Intervention, I can give you a concrete Horizon supervisor config (process counts + memory/max-jobs) that keeps container memory stable.


What should I use, imagick or GD to keep the memory footprint low?

If your priority is low, predictable memory per worker, pick GD (via Intervention Image’s GD driver, or plain GD) as long as you keep your input/output sizes capped. Imagick can be great, but it’s easier for it to balloon memory unless you’re careful with limits and resizing strategy.

Why GD tends to be smaller/simpler
GD is a simpler in-process bitmap library. You generally know what you’ll pay: decoded image in RAM + a bit of overhead.

For a fixed output size (e.g. 1200×630), you can downscale early and keep memory stable.

Why Imagick can use more memory
Imagick wraps ImageMagick, which may use additional buffers and can keep multiple representations during operations.

It can spill to disk, but that still means higher resource usage and more tuning.

It’s powerful (better text shaping options, color profiles, etc.), but you’ll want to set resource limits and be disciplined about resizing first.

The real lever is: control the pixel count
Whichever you choose, memory is dominated by “how many pixels are decoded at once”.

If you download a 6000×4000 image and fully decode it, you’re already in “big” memory territory:

6000×4000×4 bytes ≈ 96 MB for raw RGBA, plus overhead.
If you instead work with ~2000px wide max, it’s much smaller.

So the best practice for both GD and Imagick:

choose a target card size (e.g. 1200×630, 1080×1080, 1920×1080)

ensure your source image is not massively larger than needed (or downscale immediately)

Recommendation for your verse-card generator
Use GD if:
You only need: resize/crop + simple overlay + basic text with TTF font

You want the most predictable, low footprint workers

You can accept simpler typography (no advanced shaping/kerning beyond what GD gives)

How to keep it low:

Download “large enough” only (e.g. 1920px wide)

Immediately resize to output size

Render text and save

Use Imagick if:
You want higher-quality text rendering and more complex effects (blurred background, better antialiasing, more robust compositing)

You’re willing to run 1 render worker and set strict resource limits

How to keep it under control:

Limit the input size you download

Resize as the very first operation

Set ImageMagick policy/resource limits (if your environment allows)

My pick for you (given your memory anxiety + Horizon)
Start with GD for rendering and keep card-render queue at 1 worker initially anyway. You’ll likely get:

lower baseline

fewer “mystery spikes”

easier capacity planning

If later you want prettier typography/effects, you can switch render queue to Imagick without changing your overall pipeline.

One extra practical tip
If you want “pretty” text while staying on GD:

Always render onto a semi-transparent dark/light overlay (rectangle or gradient) to avoid needing heavy blur effects.
That avoids expensive image operations and keeps memory/CPU low.

If you tell me your target final image size (e.g. 1200×630 or 1080×1080) and whether you need blur behind text, I’ll recommend a specific rendering approach that stays within a chosen per-worker memory cap.


Target final image size is 1600x1200 at most. I would like to have some nice effects.

Thought for 4 seconds
At 1600×1200 max and “nice effects”, you’ve got two good paths:

Best balance: use Imagick, but constrain it hard
Imagick will make “nice effects” (blur, vignette, soft light overlays, shadowed text) much easier and better-looking than GD, and 1600×1200 is small enough to keep it stable if you control input size and worker recycling.

How to keep Imagick’s memory footprint predictable
Never process huge originals

When you download from Pixabay, pick a URL that’s “large enough” but not gigantic.

Practical rule: cap the working image to around 2000–2400 px on the long side before doing anything else.

First operation in the render job: resize down to your working size.

Do the resize as the very first step

Load image → immediately thumbnailImage() or resizeImage() to your target canvas (or a modest oversize), then do effects.

Set resource limits per process
In the render job, set limits (memory/map/disk) so a single job can’t blow up your container. If it hits limits, the job fails fast and you can retry with a smaller source.

Keep render workers low + recycle

Run 1 render worker to start.

Configure worker recycling: restart after N jobs / max time / memory threshold. This is the most reliable way to “give memory back”.

What memory to expect
At 1600×1200, a single decoded RGBA frame is roughly:

1600×1200×4 ≈ 7.7 MB
Even with a couple of intermediate buffers (blur, compositing), you’re usually well under 100–200MB if you resized early. The big spikes happen when you decode/process a 4000–6000px original.

Lower memory, fewer effects: use GD + “cheap” nice effects
GD can look great if you avoid true blur and lean on overlays:

gradient overlay behind text

vignette (precomputed PNG overlay)

subtle noise/texture overlay

drop shadow text
These look “designed” and keep memory usage very predictable.

But true background blur (especially localized blur behind text) is where GD starts to get ugly/slow (and you’ll end up writing more code or using tricks).

My recommendation for your target and goals
Use Imagick for the card-render queue only, and keep everything else lightweight.

pixabay-search jobs: tiny memory, 1 worker

image-download jobs: stream-to-disk, 2–4 workers

card-render jobs: Imagick, 1 worker (maybe 2 later), strict limits + recycling

This way only one process is doing the “heavy” stuff.

“Nice effects” that are cheap and look great (Imagick)
Here’s an effects stack that looks premium and is memory-safe:

Fit/crop to 1600×1200

Subtle vignette (darken edges a bit)

Text readability panel:

Either a bottom gradient (transparent → 50% black)

Or a rounded rectangle behind text with slight blur

Text with shadow

Optional: very light background blur behind the text area only

Crop the text region, blur it, composite it back (keeps cost low)

If you do only one “fancy” thing, make it the gradient + shadow + vignette combo—high impact, low compute.

Operational settings I’d start with
Render worker count: 1

Worker recycling: restart render worker after e.g. 50–200 jobs or 15–30 minutes, and enforce a memory limit restart.

Input cap: long edge ≤ 2400px before effects.

Output: 1600×1200 JPG/PNG (JPG is smaller/faster; PNG if you need crisp text, but JPG at high quality is usually fine).

If you tell me which Laravel image library you prefer (plain Imagick, Intervention Image, or Spatie image), I can suggest the exact implementation pattern to: resize first, apply vignette/gradient/blur panel, draw wrapped text, and keep the memory stable.


I don't have a preferred Laravel image library yet. I use Laravel 12.

Given Laravel 12, max size 1600×1200, and “nice effects”, I’d choose:

Spatie Image (wraps Imagick/GD cleanly, great ergonomics) for resizing/cropping/filters, plus

Direct Imagick for the “special sauce” (gradient panel + localized blur + high-quality text + shadow).

Why this combo: Spatie keeps the common stuff tidy; Imagick gives you better effects and typography. If you want to keep dependencies minimal, you can skip Spatie and go pure Imagick—that’s also totally fine.

Below is a concrete design that’s stable in Horizon and keeps memory predictable.

Recommended library choices
Option A (my pick): Spatie Image + Imagick for overlays/text
Pros: clean API for fit/crop/optimize, easy integration, good maintenance.

Cons: still need Imagick for the more advanced compositing/text.

Option B: Intervention Image v3 (Imagick driver)
Pros: popular, lots of examples.

Cons: for “nice” effects you’ll still drop to Imagick operations; v3 changed APIs and can be a bit more verbose.

Option C: Pure Imagick
Pros: most control, best effects, few abstractions.

Cons: more code, you own all the edge cases.

If you’re starting fresh: Option C (Pure Imagick) is actually the simplest for your specific pipeline because you’re already doing job-based rendering and want predictable behavior.

The rendering pipeline (pure Imagick, stable + pretty)
Inputs
Local selected image file (downloaded)

Verse text + reference

Style params (font, alignment, margins)

Steps (safe order)
Load

Immediately downscale if long edge > 2400px

Crop/cover to exactly 1600×1200

Apply subtle vignette (cheap and elegant)

Create a bottom gradient overlay (text safe area)

Optional: localized blur behind text area

Draw verse text with shadow

Save final JPG/PNG into your ephemeral folder

Effects that look great but don’t blow memory
Vignette + gradient overlay + text shadow = “designed” look without expensive global blur.

Local blur only on a cropped band (e.g. bottom 35% of image) is manageable.

Horizon memory strategy
Keep card-render at 1 worker initially.

Set worker recycling: restart the render worker after maxJobs and/or maxTime, and enforce a memory cap so it restarts before it becomes sticky.

The biggest memory risk is decoding huge originals; your “downscale first” rule is what keeps you safe.

Concrete implementation sketch (Laravel 12 + Imagick)
Composer + extension
Install PHP Imagick extension in your worker container.

Ensure ImageMagick is present in the container OS.

Render Job outline (pseudo-code)
public function handle()
{
    $session = VerseCardSession::findOrFail($this->sessionId);
    abort_if($session->expires_at->isPast(), 0);

    $asset = VerseCardAsset::where('id',$this->selectedAssetId)->where('kind','selected')->firstOrFail();

    $im = new \Imagick($asset->absolutePath());

    // 1) Downscale early (cap long edge)
    $this->downscaleIfNeeded($im, 2400);

    // 2) Cover crop to 1600x1200
    $this->coverCrop($im, 1600, 1200);

    // 3) Vignette (subtle)
    $this->applyVignette($im);

    // 4) Gradient overlay (bottom)
    $this->applyBottomGradient($im, height: 480 /* ~40% */);

    // 5) Optional: blur only behind text band
    $this->blurBand($im, yStart: 720, height: 480, sigma: 8);

    // 6) Draw verse with shadow
    $this->drawText($im, $verseText, $refText);

    // 7) Save
    $path = "verse-cards/{$session->id}/final/{$finalAssetId}.jpg";
    Storage::disk('ephemeral')->put($path, $im->getImageBlob());

    ...
}
Important: do not call getImageBlob() for huge images before resizing; but at 1600×1200 it’s fine.

Fonts
Use a small set of bundled fonts (e.g. Inter, Lora, Merriweather) inside your container so rendering is deterministic.

Cleanup: DB-driven, storage deletion by folder
When session ends/expires:

Storage::disk('ephemeral')->deleteDirectory("verse-cards/{$sessionId}")

mark assets deleted, session expired

This is simple and robust.

If you want “nicer typography”
Imagick text is decent, but if you later want perfect line breaking/kerning:

keep your pipeline, and swap the text rendering step to something like SVG → raster, or use a dedicated text layout library.
Not needed at first; gradient+shadow does 80% of the “nice” feel.

Final recommendation
Start with pure Imagick in the card-render queue, keep it at 1 worker, cap input size early, and use gradient+vignette+shadow + optional localized blur. That gives you high-quality effects without unpredictable memory blowups.

If you want, I can provide a complete Laravel 12-ready implementation for:

the RenderVerseCard job (Imagick helpers: coverCrop, vignette, gradient, blurBand, wrapText),

the download job (streaming),

and the cleanup command + scheduler entry.


Actually I have already ext-imagick for generating qr codes

Perfect — then you’re already 90% set. If ext-imagick is in your containers, you can go pure Imagick for the verse cards and keep the dependency surface small.

Here’s the concrete plan I’d implement given your constraints (1600×1200, nice effects, Horizon, ephemeral storage).

What to do (high level)
Use Imagick only in the card-render queue (1 worker to start).

Use stream-to-disk downloads in the image-download queue.

Enforce early downscale (cap long edge) before any effects to avoid memory spikes.

Use cheap-but-premium effects: vignette + bottom gradient + localized blur behind text band + text shadow.

Imagick settings to keep memory stable
In your render job, set these at the start (best-effort; some builds ignore parts, but usually works):

$im = new \Imagick();
$im->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256); // MB-ish unit depends on build
$im->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256);
$im->setResourceLimit(\Imagick::RESOURCETYPE_DISK, 1024); // allow spill
Even if the exact units vary by ImageMagick build, the intent is correct: limit runaway usage.

Then: load → downscale → crop immediately.

A render pipeline that looks good
1) Load + downscale early (cap to 2400px long edge)
If source is huge, downscale first.

This is the #1 memory saver.

2) Cover-crop to 1600×1200
Similar to CSS background-size: cover.

3) Vignette
Subtle edge darkening.

4) Bottom gradient overlay
Makes text readable without “ugly blocks”.

5) Optional localized blur behind text area
Only blur the bottom 35–45% band (cheap, classy).

6) Text + shadow
Verse text + reference.

Auto font sizing + wrapping.

Minimal helper algorithms (practical)
Cover-crop math
Compute scale = max(targetW/srcW, targetH/srcH)

Resize to scaled dims

Crop center to target dims

Text wrapping + font sizing
Start at a max font size (e.g. 60)

Wrap words into lines based on measured width (queryFontMetrics)

If too tall / too wide, decrement font size and retry

Stop at a min size (e.g. 28), then fallback to smaller padding if needed

This is deterministic and works great for 1–2 verses.

Horizon config guidance (memory anxiety friendly)
card-render: 1 process, memory cap restart (e.g. 256–384MB), maxJobs ~50–200

image-download: 2–4 processes, low memory

pixabay-search: 1 process

The render worker is the only one that can “stick” at a high-water mark, so you keep it few and recyclable.

Ephemeral storage cleanup (DB-driven)
Since you want to delete everything at session end:

store everything under storage/app/ephemeral/verse-cards/{sessionId}/...

cleanup command deletes the directory for expired sessions:

Storage::disk('ephemeral')->deleteDirectory("verse-cards/$sessionId");

That’s fast and avoids tracking individual files perfectly.

One important download tip (avoid hotlinking + keep memory low)
In the download job:

stream HTTP to file (Guzzle sink)

also write a thumb file (e.g. 480px wide) for the chooser UI

store attribution metadata (Pixabay user + page URL) in DB so you can display “Pixabay • user”

If you want, I can provide a complete, copy-pasteable RenderVerseCard job in Laravel (pure Imagick) that implements:

cover-crop,

vignette,

bottom gradient,

optional blur band,

wrapped text with shadow,

and writes to your ephemeral disk.

Just tell me whether you want the final output as JPG (smaller, fast) or PNG (crisper text, bigger).


I want JPG, provide the renderversecard job in laravel (pure imagemagick) the way you say.

Thought for a few seconds
<?php

namespace App\Jobs;

use App\Models\VerseCardAsset;
use App\Models\VerseCardSession;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Render a Bible verse card as JPG (1600x1200 max) using pure Imagick.
 *
 * Requirements:
 * - ext-imagick installed
 * - A local "ephemeral" disk configured (storage/app/ephemeral)
 * - Selected source image already downloaded locally (asset->path)
 * - Font files accessible inside container (TTF/OTF)
 *
 * DB assumptions (adjust field names as needed):
 * - VerseCardSession: id (uuid), verse_ref, expires_at (timestamp), status
 * - VerseCardAsset: id, session_id, kind (selected/final), path, disk, state, bytes,
 *                   pixabay_user, pixabay_page_url
 */
class RenderVerseCard implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $sessionId;
    public int $selectedAssetId;

    // Target output
    private const OUT_W = 1600;
    private const OUT_H = 1200;

    // Limit the working image to prevent memory spikes
    private const MAX_LONG_EDGE = 2400;

    // Bottom panel height for gradient + (optional) blur band
    private const PANEL_H = 520; // ~43% of 1200

    // Fonts (ship these with your app/container)
    // Put your fonts e.g. in storage/app/fonts/ or resources/fonts/
    private const FONT_VERSE = 'fonts/Lora-Regular.ttf';
    private const FONT_REF   = 'fonts/Inter-SemiBold.ttf';

    // Text layout
    private const PADDING_X = 90;
    private const PADDING_BOTTOM = 80;
    private const LINE_GAP = 10;

    // Visual style
    private const SHADOW_OPACITY = 0.55;
    private const SHADOW_OFFSET_X = 2;
    private const SHADOW_OFFSET_Y = 3;

    // JPG output
    private const JPG_QUALITY = 88;

    // Optional localized blur behind text
    private bool $enableBlurBand = true;

    public function __construct(string $sessionId, int $selectedAssetId)
    {
        $this->sessionId = $sessionId;
        $this->selectedAssetId = $selectedAssetId;

        // Put this job on your render queue
        $this->onQueue('card-render');
    }

    public function handle(): void
    {
        try {
            /** @var VerseCardSession $session */
            $session = VerseCardSession::query()->whereKey($this->sessionId)->firstOrFail();

            if ($session->expires_at && CarbonImmutable::parse($session->expires_at)->isPast()) {
                $session->status = 'expired';
                $session->save();
                return;
            }

            /** @var VerseCardAsset $selected */
            $selected = VerseCardAsset::query()
                ->where('id', $this->selectedAssetId)
                ->where('session_id', $session->id)
                ->where('kind', 'selected')
                ->firstOrFail();

            $disk = $selected->disk ?: 'ephemeral';
            $sourcePath = $selected->path;

            if (!$sourcePath || !Storage::disk($disk)->exists($sourcePath)) {
                throw new Exception("Selected source file missing: disk={$disk}, path={$sourcePath}");
            }

            // Load verse text: adjust to your actual verse retrieval
            // Here we assume you have these on the session (or fetch them elsewhere).
            $verseText = $this->getVerseTextForSession($session);
            $refText   = $this->getReferenceTextForSession($session);

            $session->status = 'rendering';
            $session->save();

            $absSource = Storage::disk($disk)->path($sourcePath);

            $im = new \Imagick();

            // Best-effort: constrain resources (units depend on build; still useful)
            $im->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256);
            $im->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256);
            $im->setResourceLimit(\Imagick::RESOURCETYPE_DISK, 1024);

            $im->readImage($absSource);
            $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $im->stripImage(); // remove EXIF, profiles (smaller output)

            // Ensure correct orientation if EXIF exists (stripImage may remove it, so do before strip ideally)
            // If you want to be safer, move stripImage after autoOrientImage.
            if (method_exists($im, 'autoOrientImage')) {
                $im->autoOrientImage();
            }

            // 1) Downscale early if needed
            $this->downscaleIfNeeded($im, self::MAX_LONG_EDGE);

            // 2) Cover-crop to output size
            $this->coverCrop($im, self::OUT_W, self::OUT_H);

            // 3) Subtle vignette
            $this->applyVignette($im);

            // 4) Bottom gradient overlay for readability
            $this->applyBottomGradient($im, self::PANEL_H);

            // 5) Optional blur band behind text panel (localized)
            if ($this->enableBlurBand) {
                $this->blurBottomBand($im, self::PANEL_H, 0, 8.0);
            }

            // 6) Draw verse + reference
            $this->drawVerseAndReference($im, $verseText, $refText);

            // 7) Export JPG
            $im->setImageFormat('jpeg');
            $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(self::JPG_QUALITY);
            $im->setInterlaceScheme(\Imagick::INTERLACE_JPEG); // progressive

            $finalAssetId = null;

            // Create a final asset record
            /** @var VerseCardAsset $final */
            $final = new VerseCardAsset();
            $final->session_id = $session->id;
            $final->kind = 'final';
            $final->state = 'ready';
            $final->disk = 'ephemeral';
            $final->expires_at = $session->expires_at;
            $final->pixabay_user = $selected->pixabay_user;
            $final->pixabay_page_url = $selected->pixabay_page_url;
            $final->save();

            $finalAssetId = $final->id;

            $finalPath = "verse-cards/{$session->id}/final/{$finalAssetId}.jpg";
            $blob = $im->getImageBlob();

            Storage::disk('ephemeral')->put($finalPath, $blob);

            $final->path = $finalPath;
            $final->bytes = strlen($blob);
            $final->save();

            $session->status = 'ready';
            $session->save();

            // free memory
            $im->clear();
            $im->destroy();
        } catch (ModelNotFoundException $e) {
            // Session or asset deleted/expired; silently stop
            Log::info('RenderVerseCard: missing model', [
                'sessionId' => $this->sessionId,
                'selectedAssetId' => $this->selectedAssetId,
            ]);
        } catch (Exception $e) {
            Log::error('RenderVerseCard failed', [
                'sessionId' => $this->sessionId,
                'selectedAssetId' => $this->selectedAssetId,
                'error' => $e->getMessage(),
            ]);

            // Mark session failed (optional)
            VerseCardSession::query()->whereKey($this->sessionId)->update(['status' => 'failed']);
            throw $e; // let Horizon retry based on your retry policy
        }
    }

    /**
     * Replace with your own verse lookup.
     */
    private function getVerseTextForSession(VerseCardSession $session): string
    {
        // Example: store verse text on session or fetch by verse_ref
        // return $session->verse_text;

        // Placeholder:
        return (string)($session->verse_text ?? 'For God so loved the world, that he gave his only Son...');
    }

    /**
     * Replace with your own reference string.
     */
    private function getReferenceTextForSession(VerseCardSession $session): string
    {
        return (string)($session->verse_ref ?? 'John 3:16');
    }

    private function downscaleIfNeeded(\Imagick $im, int $maxLongEdge): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $long = max($w, $h);

        if ($long <= $maxLongEdge) {
            return;
        }

        $scale = $maxLongEdge / $long;
        $newW = (int)max(1, floor($w * $scale));
        $newH = (int)max(1, floor($h * $scale));

        // thumbnailImage keeps aspect ratio, high quality
        $im->thumbnailImage($newW, $newH, true, true);
    }

    /**
     * CSS-like cover crop: scale up to cover target, then center crop.
     */
    private function coverCrop(\Imagick $im, int $targetW, int $targetH): void
    {
        $srcW = $im->getImageWidth();
        $srcH = $im->getImageHeight();

        $scale = max($targetW / $srcW, $targetH / $srcH);
        $newW = (int)ceil($srcW * $scale);
        $newH = (int)ceil($srcH * $scale);

        $im->resizeImage($newW, $newH, \Imagick::FILTER_LANCZOS, 1.0, true);

        $x = (int)max(0, floor(($newW - $targetW) / 2));
        $y = (int)max(0, floor(($newH - $targetH) / 2));

        $im->cropImage($targetW, $targetH, $x, $y);
        $im->setImagePage(0, 0, 0, 0);
    }

    private function applyVignette(\Imagick $im): void
    {
        // Subtle vignette: darken edges. Parameters are intentionally mild.
        // (radius, sigma, x, y). Using moderate sigma gives a soft falloff.
        if (method_exists($im, 'vignetteImage')) {
            $im->vignetteImage(0.25, 1.5, -10, -10);
        } else {
            // Fallback: do nothing (older builds)
        }
    }

    private function applyBottomGradient(\Imagick $im, int $panelHeight): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $panelHeight = min($panelHeight, $h);

        // Create a vertical gradient: transparent at top of panel -> semi-black at bottom
        $gradient = new \Imagick();
        $gradient->newPseudoImage($w, $panelHeight, 'gradient:transparent-rgba(0,0,0,0.62)');
        $gradient->setImageFormat('png');

        // Composite at bottom
        $im->compositeImage($gradient, \Imagick::COMPOSITE_OVER, 0, $h - $panelHeight);

        $gradient->clear();
        $gradient->destroy();
    }

    /**
     * Blur only the bottom band (panel area). This keeps cost low.
     * $radius=0 lets ImageMagick choose based on sigma.
     */
    private function blurBottomBand(\Imagick $im, int $bandHeight, float $radius = 0.0, float $sigma = 8.0): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $bandHeight = min($bandHeight, $h);
        $y = $h - $bandHeight;

        $band = clone $im;
        $band->cropImage($w, $bandHeight, 0, $y);
        $band->setImagePage(0, 0, 0, 0);

        // Blur the band
        $band->gaussianBlurImage($radius, $sigma);

        // Composite back with partial opacity (so it's subtle)
        // Create an alpha mask by lowering opacity of the blurred band
        $band->evaluateImage(\Imagick::EVALUATE_MULTIPLY, 0.80, \Imagick::CHANNEL_ALPHA);

        $im->compositeImage($band, \Imagick::COMPOSITE_OVER, 0, $y);

        $band->clear();
        $band->destroy();
    }

    private function drawVerseAndReference(\Imagick $im, string $verseText, string $refText): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $fontVerse = $this->fontPath(self::FONT_VERSE);
        $fontRef   = $this->fontPath(self::FONT_REF);

        $maxTextWidth = $w - (2 * self::PADDING_X);

        // Layout area is mainly in bottom panel
        $panelTopY = $h - self::PANEL_H;
        $textTopY = $panelTopY + 60; // start a bit below panel top
        $textBottomY = $h - self::PADDING_BOTTOM;

        // 1) Find font size + wrapped lines that fit
        $best = $this->fitWrappedText(
            $verseText,
            $fontVerse,
            $maxTextWidth,
            $textBottomY - $textTopY - 70, // reserve space for reference line
            maxSize: 64,
            minSize: 28
        );

        $lines = $best['lines'];
        $fontSize = $best['fontSize'];
        $lineHeight = $best['lineHeight'];

        // 2) Draw shadow + text
        $draw = new \ImagickDraw();
        $draw->setFont($fontVerse);
        $draw->setFontSize($fontSize);
        $draw->setTextAntialias(true);
        $draw->setGravity(\Imagick::GRAVITY_NORTHWEST);

        // White text
        $fill = new \ImagickPixel('white');

        // Shadow: rgba(0,0,0,opacity)
        $shadow = new \ImagickPixel(sprintf('rgba(0,0,0,%.3f)', self::SHADOW_OPACITY));

        $x = self::PADDING_X;
        $y = $textTopY;

        foreach ($lines as $line) {
            // Shadow
            $draw->setFillColor($shadow);
            $im->annotateImage($draw, $x + self::SHADOW_OFFSET_X, $y + self::SHADOW_OFFSET_Y, 0, $line);

            // Text
            $draw->setFillColor($fill);
            $im->annotateImage($draw, $x, $y, 0, $line);

            $y += $lineHeight + self::LINE_GAP;
        }

        // 3) Reference line (smaller, semi-bold)
        $refDraw = new \ImagickDraw();
        $refDraw->setFont($fontRef);
        $refDraw->setFontSize((int)max(22, floor($fontSize * 0.55)));
        $refDraw->setTextAntialias(true);
        $refDraw->setGravity(\Imagick::GRAVITY_NORTHWEST);

        $refY = min($y + 18, $textBottomY - 20);

        // Slightly dimmer white for reference
        $refFill = new \ImagickPixel('rgba(255,255,255,0.92)');

        // Shadow
        $refShadow = new \ImagickPixel(sprintf('rgba(0,0,0,%.3f)', self::SHADOW_OPACITY));

        $refDraw->setFillColor($refShadow);
        $im->annotateImage($refDraw, $x + self::SHADOW_OFFSET_X, $refY + self::SHADOW_OFFSET_Y, 0, $refText);

        $refDraw->setFillColor($refFill);
        $im->annotateImage($refDraw, $x, $refY, 0, $refText);

        // Cleanup
        $draw->clear();
        $refDraw->clear();
    }

    /**
     * Fit wrapped text into a width/height box by decreasing font size until it fits.
     * Returns: lines, fontSize, lineHeight
     */
    private function fitWrappedText(
        string $text,
        string $fontPath,
        int $maxWidth,
        int $maxHeight,
        int $maxSize = 64,
        int $minSize = 28
    ): array {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $probe = new \Imagick();
        $draw = new \ImagickDraw();
        $draw->setFont($fontPath);
        $draw->setTextAntialias(true);

        for ($size = $maxSize; $size >= $minSize; $size--) {
            $draw->setFontSize($size);

            $lines = $this->wrapTextByWidth($probe, $draw, $text, $maxWidth);

            // Estimate height
            $metrics = $probe->queryFontMetrics($draw, 'Ag');
            $lineHeight = (int)ceil($metrics['textHeight'] ?? ($size * 1.2));

            $totalH = (count($lines) * $lineHeight) + ((count($lines) - 1) * self::LINE_GAP);

            // Also ensure each line fits width
            $fitsWidth = true;
            foreach ($lines as $line) {
                $m = $probe->queryFontMetrics($draw, $line);
                if (($m['textWidth'] ?? 0) > $maxWidth) {
                    $fitsWidth = false;
                    break;
                }
            }

            if ($fitsWidth && $totalH <= $maxHeight) {
                return [
                    'lines' => $lines,
                    'fontSize' => $size,
                    'lineHeight' => $lineHeight,
                ];
            }
        }

        // Fallback at min size
        $draw->setFontSize($minSize);
        $lines = $this->wrapTextByWidth($probe, $draw, $text, $maxWidth);
        $metrics = $probe->queryFontMetrics($draw, 'Ag');
        $lineHeight = (int)ceil($metrics['textHeight'] ?? ($minSize * 1.2));

        return [
            'lines' => $lines,
            'fontSize' => $minSize,
            'lineHeight' => $lineHeight,
        ];
    }

    /**
     * Greedy word-wrapping using Imagick font metrics.
     */
    private function wrapTextByWidth(\Imagick $probe, \ImagickDraw $draw, string $text, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = ($line === '') ? $word : ($line . ' ' . $word);

            $m = $probe->queryFontMetrics($draw, $candidate);
            $w = (float)($m['textWidth'] ?? 0);

            if ($w <= $maxWidth) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
                $line = $word;
                continue;
            }

            // Single very long word: hard-break
            $lines[] = $this->hardBreakWord($probe, $draw, $word, $maxWidth);
            $line = '';
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function hardBreakWord(\Imagick $probe, \ImagickDraw $draw, string $word, int $maxWidth): string
    {
        // Break long tokens (rare for verses, but safe)
        $out = '';
        $buf = '';

        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $candidate = $buf . $ch;
            $m = $probe->queryFontMetrics($draw, $candidate);
            $w = (float)($m['textWidth'] ?? 0);

            if ($w <= $maxWidth) {
                $buf = $candidate;
                continue;
            }

            if ($buf === '') {
                // Can't fit even one char (extreme) — just append
                $out .= $ch;
                continue;
            }

            $out .= $buf . "–\n"; // soft hyphenation via en dash + newline
            $buf = $ch;
        }

        $out .= $buf;

        // Return only first line chunk; caller stores as its own line.
        // If you want to support multi-line here, split by \n and merge externally.
        return Str::before($out, "\n") ?: $word;
    }

    private function fontPath(string $relative): string
    {
        // Adjust where you keep fonts. Common choices:
        // - storage_path('app/fonts/...')
        // - resource_path('fonts/...')
        $p1 = storage_path('app/' . ltrim($relative, '/'));
        if (file_exists($p1)) return $p1;

        $p2 = resource_path(ltrim($relative, '/'));
        if (file_exists($p2)) return $p2;

        // Last resort: assume relative path is absolute in container
        return $relative;
    }
}
Notes you’ll likely want to adjust (quick checklist)
Verse text source: replace getVerseTextForSession() with your real verse retrieval (you said embeddings already exist; you probably have verse text in DB).

Font files: put Lora-Regular.ttf and Inter-SemiBold.ttf into storage/app/fonts/… (or change paths).

DB field names: match your VerseCardSession / VerseCardAsset columns (I used kind, state, disk, path, etc. as we discussed).

Blur: toggle $enableBlurBand to false if you want faster rendering.

If you want, paste your actual VerseCardSession + VerseCardAsset schema (column names) and I’ll adapt the job to match exactly.





