---
paths:
    - resources/css/app.css
---

# Css

## Use the landscape-phone variant for sideways phones

`@custom-variant landscape-phone` is bounded by `max-height: 540px` as well as orientation. Do not drop the height bound: desktops and tablets are landscape too and would inherit the compact layout.

Tailwind's `sm:` breakpoint only knows width, so a wide-but-short screen matches it and gets desktop-sized cards it has no vertical room for. Anything sized for the viewport's _height_ — card metrics above all — needs an explicit `landscape-phone:` override, not just an `sm:` value.
