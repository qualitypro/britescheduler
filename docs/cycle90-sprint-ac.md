# Cycle 90AC — Compact appointment details modal

Scope CSS exclusively to #appointmentDetailsDialog. Use four-column definition-list layout (label/value, label/value) on desktop, and two-column label/value layout on small screens. Reduce padding, header, gaps, footer and button dimensions; widen the modal moderately to reduce wrapping. Hide the empty edit-status paragraph while preserving messages when populated. Preserve every existing field, role rule, action and keyboard behavior. Vertical overflow remains as an accessibility fallback for extremely small viewports or unusually long values, rather than clipping data.

No browser QA performed; test long addresses, contractor names, role variants, mobile and short viewport heights.
