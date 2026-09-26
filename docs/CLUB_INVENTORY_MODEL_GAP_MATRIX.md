# Club Inventory Model Gap Matrix

Audit scope: inventory items, serial/resource objects, storage location, quantities, condition, responsibility, and department/team scope.

| Capability | Current contract | Gap | Action |
| --- | --- | --- | --- |
| Article identity | `club_inventory_items.sku`, `article_number`, `batch_number`; number-range fallback for missing `sku`. | No dedicated serial number per physical unit. Current model treats units as quantity plus optional child resources. | Keep as known gap; model needs a separate unit table before serial-level lifecycle can be exact. |
| Serial/resource objects | `parent_id`, `resource_type`, child resources, booking windows, opening hours and blackout rules. | Physical serial object history is only expressible as child items/resources, not normalized units. | No broad refactor in this pass. |
| Storage location | `location` on item plus hierarchy parent. | No normalized warehouse/bin table. | Keep simple string until multi-site stock movement needs formal locations. |
| Quantity | `quantity_total`, `quantity_available`, movements, window-capacity reservations. | Movement model does not identify serial units. | Covered for bulk stock; serial-unit gap remains. |
| Condition | Item `condition`; loan `return_condition`, `damaged_at`, `damage_description`. | No per-unit condition state. | Covered for item/loan contract; serial-unit gap remains. |
| Responsibility | Borrower, requester, checkout actor, return actor, transfer recipient and timestamps. | Active/current responsible user was inferred from borrower/transfer status and not indexed. | Added `responsible_user_id` on loans, populated on checkout/transfer and cleared on terminal return/loss. |
| Department/team scope | `club_department_id`, `team_id`, scoped permissions, mismatch validation. | No issue found in API contract. | Regression remains in `ClubInventoryApiTest`. |
| Handover/termination contract | Active loans block termination; handover snapshots include active borrower loans. | Handover should follow current responsible user after responsibility transfer. | Snapshot lookup now prefers `responsible_user_id` with borrower fallback for legacy rows. |
