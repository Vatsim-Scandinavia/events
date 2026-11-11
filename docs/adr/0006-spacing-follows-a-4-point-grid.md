# Spacing follows a 4-point grid

Spacing in the UI had drifted: values like 4px and 6px sat side by side, and unrelated content was packed together. The front-page event cards were the clearest example, with the airport badges 4px from the title and summary because everything was placed in a `CardHeader` meant for a title and a one-line description.

Every margin, padding and gap is a multiple of 4px: 4, 8, 12, 16, 24, 32 and so on. In Tailwind that means whole spacing steps (`gap-2`, `p-4`) and no half steps (`gap-1.5`, `py-0.5`). Line-heights land on the grid too, so text rows line up with the gaps around them: a card title uses a 28px line-height rather than `leading-snug`.

Space inside a group is never larger than the space around it. Related items, such as a title and its summary, the badges in a row, or a date and a time, are 8px apart. Groups within a card, such as the header, the details and the footer, are 16px apart, which is the card padding as well. Content is placed in the card slot that matches it: the title and summary in `CardHeader`, supporting details in `CardContent`, and actions in `CardFooter`.

Forms are spaced a step wider: 24px between their parts (each fieldset, the notes, the buttons) and 8px inside them, because labelled groups of inputs need clearer separation than the details on a card. Rows people tap, such as checkboxes in a form or items in the phone menu, are 44px tall.

The one exception to the grid is optical: an icon may be nudged by 1–2px (`mt-px`, `mt-0.5`) so it sits on the first line of the text beside it. That is alignment, not spacing; the space between elements stays on the grid.

Cards shown side by side are read as a comparison, so the same detail sits at the same height on each one. Optional content, like an Event's Summary, must not move what comes after it: the details and actions are pinned to the bottom of the card, and any free space goes between the header and the details. A placeholder such as "No summary" is not added, since it is noise that looks like content.

## Considered Options

- **Widening the gap of `CardHeader` in `ui/card.tsx`.** Rejected: it would change about 30 cards at once, and it fixes the symptom while leaving details in a slot that isn't meant for them.
- **Overriding the gap card by card**, without moving content. Rejected: each card would pick its own number, which is how the drift started.

## Consequences

Shared `ui/` primitives such as `Badge` keep their own internal padding for now, including 2px values. The rule applies to the space between elements until the primitives get a pass of their own.

The rest of the UI is brought in line one area at a time, starting with the front page. Code that hasn't been reviewed yet may still use off-grid values.
