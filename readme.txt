=== Moving & Storage Volume Calculator ===
Contributors: xaviermsd
Tags: moving calculator, removals, volume calculator, storage calculator, moving quote
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Room-by-room moving volume calculator with van, container and storage unit suggestions, and quote requests for removal companies.

== Description ==

Visitors list what they are moving, room by room, and see the total volume update as they go. The calculator then suggests the van or truck, the shipping container and the storage unit that fit, and lets them send you the full list as a quote request.

= For your visitors =

* **441 items in 21 rooms**, from sofas and wardrobes to pianos, hot tubs, shop fittings, salon chairs and pallets. Volumes are set from published industry figures.
* **Five clear categories:** Home, Garage & garden, Specialty items, Boxes & packing, and Business. Pick a category, then a room.
* **14 Quick Starts.** One tap fills a typical list for boxes only, a few items, a student room, a studio, a 1 to 5 bedroom home, a garage and shed, a storage unit, a small office, a shop or a café. Totals are checked against published moving-company figures.
* **Search in your own words:** UK, US, Canadian, Australian and New Zealand names work, for example "couch", "settee", "chesterfield", "hoover", "esky" or "chilly bin".
* **Box estimator.** Five quick questions suggest how many small, medium, large and wardrobe boxes to add.
* **Add your own item** by typing its length, width and height, and pick an icon for it.
* **Live total** in cubic metres or cubic feet, with a switch between them, an estimated weight and the number of boxes.
* **What fits.** The right van or truck, shipping container and storage unit, shown as animated drawings that fill up as items are added.
* **A packing margin** is added to the total for gaps when loading.
* **Share** the list with a link (it opens on any device and nothing is stored on your server), **print** it, or come back later: the list is remembered in the visitor's browser.
* **A forgotten-areas check** before the quote reminds visitors of areas they skipped, such as the garage or garden.
* **Undo** after clearing the list.
* Light, dark or automatic theme in your brand colours.
* **No credit links unless you want them.** An optional "Powered by" line can be turned on under Volume Calculator > Settings > Look & feel; it is off by default.

= For your business =

* **Quote requests** with the visitor's name, email, phone, addresses, moving date and service, floor and lift at each address, parking and extra services (packing, dismantling, storage, disposal, cleaning), plus the full item list and totals.
* **Email notification** to you, with an optional copy to the customer.
* **Quote requests are kept in WordPress** under Volume Calculator > Leads, with a status, follow-ups and notes for each, and can be exported as a CSV file or a PDF report.
* **Edit everything.** Change any room, item, volume or preset, and choose from more than 220 icons sorted into categories. Add your own vans, trucks and storage units. Export and import your item list as JSON.
* **Regional tables** for the UK, Europe, the United States and Australia.
* **Spam protection** with a hidden field, a security token and a limit on requests per visitor. No captcha and no third-party service.

= Built the WordPress way =

* A block and a shortcode. Scripts and styles load only on pages that show the calculator.
* No external fonts, scripts or services, and no tracking.
* Works with page caching: the security token is refreshed when needed.
* Privacy tools: quote requests are included in WordPress's personal data export and erase tools, and old requests can be deleted automatically.
* Translation ready.

== Installation ==

1. Go to Plugins > Add New, search for "Moving & Storage Volume Calculator", click Install Now, then Activate.
2. Open Volume Calculator > Settings. Choose your region and unit, and enter the email address that should receive quote requests.
3. Add the "Volume Calculator" block to any page, or paste the shortcode `[msvcalc_calculator]`.

== Frequently Asked Questions ==

= How do I show the calculator? =

Add the "Volume Calculator" block in the block editor, or use the shortcode `[msvcalc_calculator]` anywhere shortcodes work, including page builders.

= Can I change the settings for one page only? =

Yes. The shortcode accepts `units` (m3 or ft3), `theme` (light, dark or auto), `quote` (yes or no), `title`, `intro` and `accent` (a hex colour). For example: `[msvcalc_calculator units="ft3" theme="dark" quote="no"]`. The block has the same options in its sidebar.

= How accurate is the total? =

It is an estimate. Each item has a typical volume that includes normal wrapping, and a packing margin (10% by default) is added for gaps when loading. You can change every volume and the margin to match how your team packs.

= Where do the vehicle and storage sizes come from? =

The plugin ships with typical usable capacities for common vans, trucks, containers and storage units in the UK, Europe, the United States and Australia. Edit them under Volume Calculator > Vehicles & storage to match your own fleet and units.

= Does it send data anywhere? =

Only to you. A quote request is saved in your WordPress database and emailed to the address in the settings, using your site's normal email. Nothing is sent to us or to any other service.

= Emails are not arriving. What can I do? =

The plugin uses WordPress's own email function. If your site's other emails also go missing, install an SMTP plugin to send through a proper mail service. While "Store requests" is on, every request is also saved under Volume Calculator > Leads, and a failed email is flagged there with a "Send email again" button.

= What happens to my data when I delete the plugin? =

By default your settings, items and quote requests are kept, in case you reinstall. Turn on "Remove data" in Volume Calculator > Settings > Data & privacy to delete them with the plugin.

== Screenshots ==

1. Build a moving inventory room by room, with Quick Starts, a live total and the best-fit van, container and storage unit.
2. "How many boxes do I need?": five quick questions suggest a count for each box size and add them to the list in one click.
3. "Get my free quote" in one short form that reminds customers about rooms they may have missed.
4. Share the list with a link, or print a branded copy.
5. Every request lands in your inbox as a branded email.
6. Leads: every enquiry in one place, with follow-ups, filters and CSV or PDF export.
7. Follow each lead through to a booking with quick contact actions, a status, follow-up reminders and notes.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
