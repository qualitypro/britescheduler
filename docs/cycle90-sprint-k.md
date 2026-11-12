# Cycle 90K — CRM Overview Accuracy

CRM overview cards now use tenant-wide SQL counts for customers, open leads, open opportunities, and outstanding due follow-ups. Pipeline stage totals are grouped SQL counts across all tenant opportunities. The follow-up queue retrieves the earliest 10 due uncompleted activities rather than sampling the most recent 150 records. Existing quote and appointment counts remain tenant-wide.

The lead/activity lists and form selectors retain capped legacy arrays; they are not addressed by this sprint. No migrations or secrets. Validate PHP syntax, tenant isolation, date boundaries, and completion actions with runtime tests before merge; no CI or database tests were executed.
