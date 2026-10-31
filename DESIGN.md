---
name: CinéPop
description: Image-led navy and blue cinema booking interface, following the user's ODEON reference.
colors:
  primary: "#0068c8"
  primary-deep: "#0054a5"
  link: "#70c9ff"
  canvas: "#06162b"
  header: "#071a32"
  surface: "#0b203b"
  panel: "#0d2543"
  elevated: "#173b61"
  text: "#edf3fb"
  muted: "#bdcbdc"
  border: "#304865"
  white: "#ffffff"
  booking-canvas: "#030a14"
  booking-scene: "#030912"
  booking-seat-standard: "#edf2f8"
  booking-seat-premium: "#bdd8f1"
  booking-seat-selected: "#ee4658"
  booking-seat-booked: "#173c66"
  booking-action: "#3879ef"
  booking-action-hover: "#2768db"
typography:
  display:
    fontFamily: "Barlow Condensed, Arial, sans-serif"
    fontWeight: 700
    fontSize: "clamp(52px, 5.6vw, 80px)"
    lineHeight: 0.98
    letterSpacing: "0.005em"
  headline:
    fontFamily: "Nunito Sans, Arial, sans-serif"
    fontWeight: 800
    fontSize: "clamp(30px, 3.2vw, 42px)"
    lineHeight: 1.2
  body:
    fontFamily: "Nunito Sans, Arial, sans-serif"
    fontWeight: 400
    fontSize: "16px"
    lineHeight: 1.65
rounded:
  control: "0px"
  tag: "2px"
  panel: "3px"
spacing:
  small: "12px"
  medium: "24px"
  large: "36px"
  section: "64px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.white}"
    rounded: "{rounded.control}"
    padding: "11px 23px"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
    textColor: "{colors.white}"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.white}"
    rounded: "{rounded.control}"
    padding: "11px 23px"
  input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
---

## Overview
**Creative North Star: "The Navy Cinema"**

Preserve CinéPop's content and booking features while following the user's ODEON visual reference. The layout is cinematic, legible and oriented around finding and booking a film. This is original project code, not an imported ODEON template.

**Key Characteristics:**
- Navy surfaces and electric-blue actions.
- Film imagery, square posters and condensed feature lettering.
- Readable forms and task-focused navigation.

## Colors
Use navy at page scale, electric blue for primary actions, pale blue for links and white for text and editable form fields. Canvas is the page backdrop; header is the navigation band; surface, panel and elevated distinguish supporting containers. Muted text carries secondary information and border separates forms and tables. Green, red and amber retain meaningful status roles from the base styles rather than becoming decorative accents.

**The Blue Action Rule.** Blue identifies primary actions and default seat selection; the user-pinned base booking surface uses red selected chairs as a scoped exception. Status colors retain their existing meaning.

The booking-prefixed tokens apply only to the base booking page, as implemented in css/booking-seats.css. Standard chairs are pale white, premium chairs pale blue, selected chairs red and booked chairs muted blue. Its brighter blue checkout action remains distinct from the global primary action. Do not propagate this selection exception to the enhanced picker or other pages.

## Typography
Locally hosted Nunito Sans is the UI face. Barlow Condensed is used for the CinéPop wordmark, large film feature titles and the enhanced page's display-face override. Poster-list titles are uppercase; body text remains sentence case. Font licenses ship in fonts/.

The installed font faces are Nunito Sans regular (400), Nunito Sans bold (700), and Barlow Condensed bold (700). CSS also requests intermediate UI weights and extra-bold headings (800); there are no separately installed faces for those weights, so browser font matching or synthesis supplies them. Do not describe the font files as a variable family or a complete weight range.

The display token describes the desktop hero: its title becomes 64px at 850px and below and 56px at 600px and below, with a 58px short-desktop-viewport override. General page headings use the headline token; section and card headings have smaller contextual sizes. The homepage synopsis uses 16px text, 1.8 line height and an 80ch maximum width.

## Layout
The main container has a 1280px maximum outer width, including its padding under border-box sizing. Desktop side padding is 36px, so its maximum inner content width is 1208px. Padding reduces to 24px at 850px and below, then 18px at 600px and below. The header's maximum outer width is 1440px; the footer's inner container maximum is 1208px.

The homepage feature places its title, metadata and actions over imagery; the complete synopsis follows below the hero. A fixed white quick-book bar anchors the homepage and page bottom padding reserves its height (96px desktop, 123px on phones). At 600px and below the optional date field is hidden, leaving film selection and the booking action.

The movie catalog uses four columns, three at 850px and below, then two at 600px and below. The homepage film rows use six columns from 1100px. A listing containing exactly two films is centered in two columns, with each column capped at 260px outside the phone breakpoint. Header navigation wraps below 1150px. At 600px and below the customer menu toggle expands the customer navigation; admin section anchors remain directly visible and wrap. The separate enhanced view retains its own header layout.

The base booking page has its own 1440px maximum outer main container with 30px side padding. Its scene has 32px by 42px padding, and the seat-map/summary grid uses a flexible main column, 270px sidebar and 58px gap. At 1100px and below the sidebar is 240px, the gap is 30px and scene padding is 30px by 24px. At 850px and below the summary follows the map in a single column. At 600px and below the main padding is 18px 12px 32px and scene padding is 24px 14px. Real rows and aisles remain intact; the map scrolls horizontally when needed instead of inventing seats or changing inventory.

## Elevation & Depth
Flat navy surfaces and restrained borders provide most separation; film imagery supplies visual depth. Cards and actions have no decorative glow or hover lift. The seat screen retains a subtle blue shadow. The payment summary is sticky on desktop and static at 850px and below. Keep motion limited to short state changes: buttons use 180ms color transitions, poster brightness uses 200ms. The base stylesheet reduces animation and transition durations for reduced-motion users; the theme also disables smooth scrolling for that preference.

The booking scene layers existing film artwork under dark gradients at 0.8 opacity. Its subdued screen shadow replaces the global blue screen highlight on this page. The summary stays in normal document flow with a thin left divider on desktop and top divider after stacking; it is not a floating or sticky card.

## Shapes
Posters and controls have square edges. Primary actions have a 12px clipped lower-right corner. Form cards and payment summaries use a restrained 3px radius; tags, wishlist cards, tickets and alerts commonly use 2px. The base booking page uses chair silhouettes built from two CSS pseudo-elements, with red selected fills and white seat labels; other seat surfaces retain their existing blue selection treatment. Its scene has a 5px radius and sidebar actions have a 1px radius without the global clipped corner. Theme custom properties also retain 0px, 2px and 4px radius steps for inherited components, so the panel radius is a component choice rather than a universal token override.

## Components
### Actions
Primary buttons are blue with white bold text, a clipped corner and a darker blue hover state. Outline actions use a pale border, white text and an elevated navy hover fill. Ghost actions remain transparent until hover. Standard buttons have a 44px minimum height; small and large variants intentionally differ. Keep the visible pale-blue keyboard focus outline (3px with 4px offset).

### Forms
White square fields use dark text, a pale border and 48px minimum height. Focus adds a blue border and 2px blue ring. Error and valid states keep their existing pale field backgrounds; visible error messages remain pink. Checkout fields have their existing 50px minimum-height override.

### Film cards and tags
Poster cards are transparent, borderless and square, with title and metadata below the image. Hover brightens the image without scaling or lifting the card. Tags use elevated navy and a restrained 2px radius. Status, rating and preference badges retain their contextual treatments.

### Navigation and booking
The shared customer header has white links, pale-blue hover and active text, and a blue active underline. Its accessible menu toggle is a customer-header behavior; the admin header uses persistent section anchors. The homepage quick-book form uses normal GET navigation to the film schedule. No AJAX is introduced to the base version.

### Homepage featured-film rotation
The homepage extends the existing feature with up to four now-showing films, ordering Spider-Man first when present, then newest release date and movie ID. With two or more films, the feature rotates every seven seconds, synchronizing the title, rating, duration, genre, complete synopsis, artwork and booking destination. Spider-Man retains its existing wide backdrop; other entries use their retained poster artwork against the navy canvas. The synopsis remains below the hero.

Previous, next, position and pause/resume controls sit below the feature content and use the existing navy, white and elevated-hover palette. Controls have 40px minimum dimensions; keyboard focus uses a white 2px outline with 3px offset. Phone controls reduce side padding and gaps without changing the visual system.

Automatic advance suspends while the feature is hovered, while focus is inside it, or while the document is hidden. Reduced-motion preference starts rotation paused and changes to that preference update the paused state. Only manual previous/next changes announce the film title through the polite live region. The server-rendered first film and booking link remain usable without JavaScript; rotation controls stay hidden until the script initializes, and are omitted when fewer than two films exist.

### Base booking surface
The screenshot-led booking page retains its real film title, synopsis, screening details, inventory, prices, preference field and checkout actions. The heading uses Nunito Sans bold rather than the condensed homepage display face. Standard chairs have pale fills; premium chairs are pale blue, selected chairs red, and booked chairs muted blue at 0.75 opacity. Seat hover changes the chair outline to red and keyboard focus uses a white 2px outline with 3px offset. Chair controls are 38px by 36px on desktop, 31px wide at the intermediate 1100px breakpoint, restored to 38px wide at 850px, then 24px by 32px at 600px and below.

The sidebar shows the retained poster, individually priced selected-seat rows with removal controls and a live total. Its preference field is dark, with a 40px minimum height; its text grows from 12px to 16px on phones. Checkout uses the booking action tokens, 43px minimum height and unclipped corners. Selection, deselection, summary removal, totals and prefilled selections keep the existing booking behavior. The separate enhanced Vue/Tailwind picker does not inherit this page exception.

### Supporting surfaces
Shared footer, forms, tables, wishlist choices, tickets, payment summary and admin controls inherit the theme. The enhanced Vue/Tailwind page uses the same local fonts and mapped navy/blue palette. Preserve its existing interaction model and the base site's booking behavior.

## Do's and Don'ts
### Do:
- Do keep every current movie description, showtime, price, field and feature.
- Do use labels, visible focus, validation errors and reduced-motion support.
- Do retain a clear payment test-mode notice and never imply real charges are collected.

### Don't:
- Don't copy ODEON's logo, offers or commercial claims.
- Don't add dead selectors, decorative booking controls or new external links.
- Don't restore the previous gold, black-glass and glowing visual treatments.
