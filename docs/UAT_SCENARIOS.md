# UAT Scenarios

UAT wajib dilakukan pada staging clone dengan data representatif. Isi kolom hasil; dokumen kosong bukan bukti PASS.

| Scenario | Tester | Date | Environment | Result | Issue reference |
|---|---|---|---|---|---|
| Tender -> Contract -> Project | _pending_ | _pending_ | staging | NOT RUN | - |
| MR -> RFQ -> PO -> GR -> Vendor Invoice -> Payment | _pending_ | _pending_ | staging | NOT RUN | - |
| Pile -> readiness -> drilling -> cleaning -> cage -> casting -> testing -> acceptance -> as-built -> handover | _pending_ | _pending_ | staging | NOT RUN | - |
| NCR -> CAPA -> effectiveness | _pending_ | _pending_ | staging | NOT RUN | - |
| Billing -> AR -> Receipt -> Journal | _pending_ | _pending_ | staging | NOT RUN | - |
| Period Closing dan period lock | _pending_ | _pending_ | staging | NOT RUN | - |
| Private storage upload/download, wrong-company denial, temporary URL fallback | _pending_ | _pending_ | staging | NOT RUN | - |
| Database backup -> verify -> staging restore rehearsal | _pending_ | _pending_ | staging | NOT RUN | - |

Setiap scenario harus memeriksa happy path, invalid transition, double submit/retry, role tanpa permission, dan company lain. Critical scenario gagal berarti release blocker.
