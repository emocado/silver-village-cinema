# Seat-selection surface

## Direction contract

THESIS: Match the user's supplied cinematic seat-booking reference while preserving CinéPop's existing film data, hall inventory, prices, preference controls and checkout actions.

OWN-WORLD: Dark image-backed scene, compact film details, chair-shaped seat controls, neutral available seats, red selection and a blue checkout action. Retain the shared CinéPop navigation; this is a scoped booking-page treatment.

STORY: Confirm film and screening, choose available seats, inspect their individual prices, set a shortlist preference or proceed to checkout.

FIRST VIEWPORT: Film title, real synopsis and screening information above a wide left-hand seat map; a film poster and booking summary sit on the right. On phones the summary follows the map without dropping content.

FORM: User-pinned image reference; code-led local refinement. No new raster artwork. Keep the actual rectangular row/seat inventory rather than inventing a theatre shape or extra availability states.

FINISH: Verify selection, deselection, summary removal, totals, preserved prefilled selections and responsive layout. Record the scoped visual review and updated design notes.

## Implemented scope

The base booking page implements the pinned scene in css/booking-seats.css. Shared navigation and global theme remain in place. The booking-prefixed tokens in DESIGN.md describe this page only: pale-white standard chairs, pale-blue premium chairs, red selected chairs, muted-blue booked chairs and a brighter blue checkout action. The red selection is an explicit exception to the global default blue selected-seat rule; the enhanced picker retains its existing palette.

The desktop composition uses the real seat grid beside the retained poster and summary, then stacks the summary below the map at 850px and below. Phone seat controls remain attached to the original rows and aisles; horizontal scrolling accommodates wider real inventory. The synopsis, individual selected-seat prices, removal controls, preference field, totals and checkout actions remain present. Existing artwork supplies the backdrop; no new raster assets were created.

## Verification disposition

Ship at the reviewed scope, with no remaining material correction. The implementation review reports scoped source plus desktop/mobile render verification, desktop selection and removal, mobile at 390px, documentation capture at 375px, and 37 passing backend tests. These are implementation-review results; the documentation refresh itself changed no UI or behavior. DESIGN.md and the schemaVersion 2 sidecar record the scoped exception and representative chair/action previews.
