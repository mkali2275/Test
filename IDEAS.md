# Ideas and notes

A working scratchpad for the solar calculator — what's decided, what's open, and
what we might do next. Nothing here is built unless it says so.

Add to it freely. Rough notes are fine; the point is that nothing gets lost
between sessions.

---

## Where things stand

- **Installed version:** 1.1.0
- **Branch with all the work:** `claude/solar-calculator-wordpress-05ayeb`
- **Live page:** https://sustainaportal.com/calculator/
- **Theme:** Solio
- **Visitor help:** written and ready in `docs/user-guide.html`, not yet pasted in

Built and working: appliance picker for home / office / travel, custom
appliances, DC loads, 14 regional sun-hour presets, four battery chemistries,
intermittent mains with grid charging, budget estimate, print/PDF, optional
email capture (off).

---

## Decisions already made

Recorded so we don't spend time re-opening them.

- Calculator lives on its **own page**, linked from services and blog, rather
  than embedded in either.
- **Email capture stays off** until an SMTP plugin is in place — WordPress mail
  fails silently on most hosts.
- The palette **follows the page background**, not the visitor's device dark-mode
  setting. Most WordPress themes ignore that setting, and following it was what
  made the mobile text unreadable in 1.0.0.
- Backup is expressed in **hours, not days**, so an intermittent-mains outage can
  be sized directly.
- Environmental figures count **solar generation**, not total consumption.

---

## Open questions

Things I'd want your answer on before building anything else.

- **Slug.** `/calculator/` works, but `/solar-calculator/` would rank better for
  the terms people actually search. Free to change now, needs a redirect later.
  Worth doing?
- **Prices.** The budget card still uses generic USD defaults ($0.90/W panels,
  $400/kWh lithium). What currency and what real local prices should it use? Or
  hide the card until we have good numbers?
- **Who is the main audience?** The regional defaults, appliance list and battery
  prices all follow from this. West Africa, Middle East, and South Asia would
  each be tuned differently.
- **What is the calculator for?** Educating readers, or generating enquiries? It
  changes whether we invest in the lead-capture side or the content side.

---

## Ideas to consider

Unvalidated — these are options, not plans. Yours to keep, cut or reorder.

### Likely worth it

- **Arabic and RTL support.** If Middle East visitors matter, the interface being
  English-only and left-to-right is the biggest barrier in the whole tool. See
  the breakdown below — it splits into a paid part and a free part.

  <details>
  <summary>How it would work, and what it would cost</summary>

  **Two separate jobs, and they are not equally hard.**

  *Right-to-left layout* is the real engineering. The interface has to mirror:
  table columns reverse, dropdown arrows flip sides, spacing written as "left"
  becomes "start", and figures with units (`3,300 W`) need care because they stay
  left-to-right inside right-to-left text.

  *The words* are about 60 appliance names, ~80 interface labels, the warning
  messages, and the 2,500-word help guide.

  **The cost-saving split.** WordPress has a built-in translation system. Make
  the plugin translation-ready — every string wrapped so it can be swapped — and
  the Arabic can then be typed in by a person using Loco Translate, free, inside
  WordPress admin. Wording can be corrected later at no cost.

  | Job | Who | Cost |
  |---|---|---|
  | RTL layout + make text translatable | Claude | The main spend |
  | Write the Arabic | A native speaker, in Loco Translate | Free, better quality |
  | Fix wording later | Us, anytime | Free |

  **Why not have Claude translate it.** Technical terms differ by region — the
  everyday word for *inverter* or *charge controller* is not the same in Gulf,
  Levant and Egyptian usage, and "peak sun hours" has no natural everyday
  equivalent. On a public tool meant to build trust, translation that reads
  slightly foreign undermines the whole thing.

  **Scale.** Bigger than the intermittent-mains feature, smaller than building
  the original calculator. Best split across two or three sessions: RTL layout
  first (working, still in English), then make the text translatable, then hand
  over for translation.

  **Check first.** Look at visitors by country in Google Analytics or Jetpack
  Stats after a month or two. Mostly English-speaking or West African traffic
  means the money is better spent elsewhere. A third from the Gulf or Levant
  makes this the highest-value item on this list.

  </details>
- **Run heavy loads while the sun is up.** The calculator currently averages the
  load across the day. Telling someone "shift your washing and ironing to
  daylight and you need two fewer batteries" is genuinely actionable advice.
- **Seasonal check.** One annual average for sun hours hides the problem month.
  A system sized on the annual figure can come up short in December.
- **Save or share results by link.** Right now closing the page loses everything.
  A shareable link would also let someone send their list to an installer.

### Maybe

- **Payback comparison.** Lithium vs lead-acid over ten years, counting
  replacements — the honest case for lithium that most buyers never see.
- **Generator sizing.** For people running one alongside solar rather than
  instead of it.
- **Quick-start presets.** "Two-bedroom flat", "small shop", "clinic" — a
  starting load list instead of a blank page.
- **WhatsApp share button.** Probably a bigger deal than email in the target
  markets.
- **Your branding on the PDF.** Logo and contact details on the printout.
- **Installer or supplier links.** Only makes sense if you have partners.

### Probably not worth it

- Live weather or satellite sun data — adds an API dependency and fragility for
  accuracy nobody sizing a home system needs.
- Wiring and cable-size calculations — that is installer territory, and being
  wrong there is a fire risk rather than an inconvenience.

---

## Watch for after going live

- Does anyone actually use the **mains hours** field, or do they miss it? It is
  the most valuable input for the target markets and the easiest to overlook.
- Do the **appliance wattages** match what people see on labels locally?
- Does anyone reach the **results** at all, or drop out during the load list?
- Which **profile** gets used most — that tells us where to add appliances.

---

## Next session

Say the branch name (`claude/solar-calculator-wordpress-05ayeb`) and I can pick
up from here.

The two most useful things to bring back:

1. What confused you or a visitor.
2. Any number that looked wrong, and what you expected instead.
